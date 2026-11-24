<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use App\Support\ActivityLogger;
use App\Support\SlackAlert;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses every request coming from an address of the « IP bloquées » list,
 * before anything else (login included). Each refusal is counted; the
 * activity log and Slack get at most one line per address every 10 min /
 * hour, so an address hammering the CRM can't flood them.
 */
class BlockListedIps
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $entry = BlockedIp::matching($request->ip());
        } catch (\Throwable) {
            // The list can't be read (e.g. table not migrated yet): never block the CRM for that
            return $next($request);
        }

        if ($entry === null) {
            return $next($request);
        }

        $ip = (string) $request->ip();
        BlockedIp::query()->where('ip', $entry)->update([
            'blocked_attempts' => DB::raw('blocked_attempts + 1'),
            'last_attempt_at' => now(),
        ]);

        if (Cache::add('blocked_ip.logged.'.$ip, true, now()->addMinutes(10))) {
            ActivityLogger::as('—', fn () => ActivityLogger::log('auth', 'auth.ip_refused', null, "Accès refusé à une IP bloquée ({$ip})", meta: [
                'ip bloquée' => $entry,
                'page demandée' => $request->method().' '.$request->path(),
            ]));
        }
        if (Cache::add('blocked_ip.alerted.'.$ip, true, now()->addHour())) {
            SlackAlert::send('Accès refusé à une IP bloquée', ['IP' => $ip, 'Règle' => $entry, 'Page demandée' => $request->method().' '.$request->path()]);
        }

        return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
    }
}
