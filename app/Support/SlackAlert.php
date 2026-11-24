<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A short message on the CRM's Slack channel (the same webhook as the error
 * alerts) for sensitive actions: a user deleted, deactivated, his role
 * changed. Never blocks the action: a Slack failure is only logged.
 */
class SlackAlert
{
    /**
     * @param  array<string, string|int|null>  $details
     */
    public static function send(string $title, array $details = []): void
    {
        $url = config('logging.channels.slack.url');
        if (! $url || app()->runningUnitTests()) {
            return;
        }

        $by = auth()->user();
        $lines = collect([
            'Par' => $by instanceof User ? "{$by->name} (#{$by->id})" : 'système',
            'IP' => request()?->ip(),
            ...$details,
            'Quand' => now()->timezone('Europe/Paris')->format('d/m/Y H:i'),
        ])->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v, $k) => "• *{$k}* : {$v}")
            ->implode("\n");

        try {
            Http::timeout(5)->post($url, [
                'username' => config('logging.channels.slack.username', 'CRM'),
                'icon_emoji' => ':shield:',
                'text' => ":shield: *{$title}*\n{$lines}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('Slack alert failed', ['title' => $title, 'error' => $e->getMessage()]);
        }
    }
}
