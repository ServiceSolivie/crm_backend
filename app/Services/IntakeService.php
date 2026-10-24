<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\IntakeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * The marketing intake endpoint, test phase: every request is recorded as
 * it arrived so the super admin can see what the sender's tool produces.
 * No lead is created yet.
 *
 * The key is kept encrypted in app_settings (not hashed): the super admin
 * must be able to show and copy it again.
 */
class IntakeService
{
    private const KEY_SETTING = 'intake_api_key';

    /** Received requests are personal data: they are not kept longer */
    public const RETENTION_DAYS = 30;

    /** Bigger bodies are cut (the full size is still recorded) */
    public const MAX_BODY = 262144;

    /** Never stored: credentials and cookies */
    private const HIDDEN_HEADERS = ['authorization', 'cookie', 'x-api-key', 'php-auth-user', 'php-auth-pw'];

    public function key(): ?string
    {
        $stored = AppSetting::read(self::KEY_SETTING);

        return $stored ? Crypt::decryptString($stored) : null;
    }

    /**
     * A new key replaces the old one at once: the sender must use the new one.
     */
    public function regenerateKey(): string
    {
        $key = 'ik_'.Str::random(40);
        AppSetting::write(self::KEY_SETTING, Crypt::encryptString($key));

        return $key;
    }

    /**
     * Record a request. The key may come in X-Api-Key, as a Bearer token or
     * as ?key= (whatever the sender's tool allows). With a wrong or missing
     * key only the outline is kept (time, method, format, size, IP), not
     * the content.
     */
    public function record(Request $request): IntakeRequest
    {
        [$provided, $via] = match (true) {
            $request->hasHeader('X-Api-Key') => [(string) $request->header('X-Api-Key'), 'header'],
            (bool) $request->bearerToken() => [(string) $request->bearerToken(), 'bearer'],
            $request->query->has('key') => [(string) $request->query('key'), 'query'],
            default => ['', null],
        };
        $key = $this->key();
        $valid = $key !== null && $provided !== '' && hash_equals($key, $provided);

        $body = $this->body($request);
        // The key itself is never stored
        $query = collect($request->query->all())->except('key')->all();

        $intake = IntakeRequest::create([
            'received_at' => now(),
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'size' => strlen($body),
            'body' => $valid ? $this->cut($body) : null,
            'query' => $valid && $query !== [] ? $query : null,
            'headers' => $this->headers($request),
            'ip' => $request->ip(),
            'key_valid' => $valid,
            'key_via' => $via,
        ]);

        $this->prune();

        return $intake;
    }

    public function prune(): void
    {
        IntakeRequest::query()->where('received_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
    }

    /**
     * The body as received. A multipart form is parsed by PHP before we see
     * it, so it is rebuilt from its fields (files: name, type and size only).
     */
    protected function body(Request $request): string
    {
        $raw = (string) $request->getContent();
        if ($raw !== '' || ($request->request->count() === 0 && $request->allFiles() === [])) {
            return $raw;
        }

        $files = collect($request->allFiles())->map(fn ($file) => $file instanceof UploadedFile
            ? ['name' => $file->getClientOriginalName(), 'type' => $file->getClientMimeType(), 'size' => $file->getSize()]
            : '(plusieurs fichiers)')->all();

        return json_encode(
            [...$request->request->all(), ...($files ? ['_files' => $files] : [])],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        );
    }

    protected function cut(string $body): string
    {
        return strlen($body) > self::MAX_BODY ? substr($body, 0, self::MAX_BODY) : $body;
    }

    /**
     * @return array<string, string>
     */
    protected function headers(Request $request): array
    {
        return collect($request->headers->all())
            ->except(self::HIDDEN_HEADERS)
            ->map(fn (array $values) => Str::limit(implode(', ', $values), 500))
            ->all();
    }
}
