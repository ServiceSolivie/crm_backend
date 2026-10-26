<?php

namespace App\Exceptions;

/**
 * Raised when the Ringover API is not configured, unreachable, or rejects a request.
 */
class RingoverException extends ApiException
{
    public static function notConfigured(): self
    {
        return new self('Ringover n\'est pas configuré (RINGOVER_API_KEY manquant).', 503);
    }

    public static function requestFailed(int $status, ?string $detail = null): self
    {
        $message = match (true) {
            $status === 401 || $status === 403 => 'Clé API Ringover refusée. Vérifiez la clé et ses droits.',
            $status === 429 => 'Limite de requêtes Ringover atteinte, réessayez dans un instant.',
            default => 'Erreur de l\'API Ringover ('.$status.').',
        };

        return new self($message, 502, $detail ? ['ringover' => $detail] : null);
    }

    public static function unreachable(string $detail): self
    {
        return new self('Impossible de joindre l\'API Ringover.', 502, ['ringover' => $detail]);
    }
}
