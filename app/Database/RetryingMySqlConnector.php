<?php

namespace App\Database;

use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;

/**
 * Opens the MySQL connection like Laravel does, but when the server refuses
 * it for an instant (« SQLSTATE[HY000] [2002] Operation not permitted »,
 * « Connection refused »…), waits a little and tries again before failing.
 *
 * Each refusal is written to storage/logs/db-connection-*.log with the state
 * of the server at that moment (does the MySQL socket exist, does MySQL
 * answer on TCP, server load, PHP processes…), to show where it comes from.
 */
class RetryingMySqlConnector extends MySqlConnector
{
    /** Waits before each new attempt, in milliseconds */
    protected const RETRY_DELAYS_MS = [150, 400, 1000];

    public function connect(array $config): PDO
    {
        $failures = [];

        foreach ([0, ...self::RETRY_DELAYS_MS] as $attempt => $delay) {
            if ($delay > 0) {
                usleep($delay * 1000);
            }

            try {
                $pdo = parent::connect($config);

                if ($failures !== []) {
                    $this->report('warning', 'Connexion MySQL réussie après '.count($failures).' refus', $config, $failures, $attempt + 1);
                }

                return $pdo;
            } catch (PDOException $e) {
                if (! $this->isTransient($e)) {
                    throw $e;
                }
                $failures[] = ['essai' => $attempt + 1, 'erreur' => $e->getMessage(), 'diagnostic' => $this->diagnose($config)];
                $last = $e;
            }
        }

        $this->report('error', 'Connexion MySQL refusée à chaque essai', $config, $failures, count($failures));

        throw $last;
    }

    /** Refusals that last an instant: worth another try */
    protected function isTransient(PDOException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '[2002]')
            || str_contains($message, '[2006]')
            || str_contains($message, 'Operation not permitted')
            || str_contains($message, 'Connection refused')
            || str_contains($message, 'Too many connections')
            || str_contains($message, 'Resource temporarily unavailable');
    }

    /**
     * The server's state when MySQL refuses the connection.
     *
     * @return array<string, mixed>
     */
    protected function diagnose(array $config): array
    {
        $socket = $config['unix_socket'] ?? '' ?: (ini_get('pdo_mysql.default_socket') ?: '/var/lib/mysql/mysql.sock');
        $host = (string) ($config['host'] ?? '');
        $port = (int) ($config['port'] ?? 3306);

        $tcpHost = $host === 'localhost' ? '127.0.0.1' : $host;
        $started = microtime(true);
        $tcp = @fsockopen($tcpHost, $port, $errno, $errstr, 0.5);
        $tcpResult = $tcp ? 'répond' : "refusé ({$errno} {$errstr})";
        if ($tcp) {
            fclose($tcp);
        }

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;

        return [
            'heure' => now()->timezone('Europe/Paris')->format('d/m/Y H:i:s.v'),
            'mode' => $host === 'localhost' ? 'socket (localhost)' : "TCP ({$host})",
            'socket' => $socket,
            'socket_existe' => $socket ? file_exists($socket) : null,
            'mysql_en_tcp' => $tcpResult.' en '.round((microtime(true) - $started) * 1000).' ms',
            'charge_serveur' => $load ? implode(' / ', array_map(fn ($v) => round($v, 2), $load)) : null,
            'memoire_php' => round(memory_get_usage(true) / 1048576, 1).' Mo',
            'processus_php' => $this->phpProcessCount(),
            'pid' => getmypid(),
            'requete' => app()->runningInConsole() ? 'console' : request()->method().' '.request()->path(),
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ];
    }

    protected function phpProcessCount(): ?int
    {
        try {
            $dirs = @glob('/proc/[0-9]*/comm', GLOB_NOSORT) ?: [];
            $count = 0;
            foreach ($dirs as $file) {
                $name = @file_get_contents($file);
                if ($name !== false && str_contains($name, 'php')) {
                    $count++;
                }
            }

            return $dirs ? $count : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $failures
     */
    protected function report(string $level, string $message, array $config, array $failures, int $attempts): void
    {
        try {
            Log::channel('db_connection')->{$level}($message, [
                'base' => $config['database'] ?? null,
                'essais' => $attempts,
                'refus' => $failures,
            ]);
        } catch (\Throwable) {
            // never let the diagnostic break the request
        }
    }
}
