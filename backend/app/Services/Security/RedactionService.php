<?php

namespace App\Services\Security;

class RedactionService
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'secret',
        'api_key',
        'apikey',
        'token',
        'authorization',
        'signature',
        'hash',
        'mfa_secret',
        'private_key',
        'webhook_secret',
        'credentials',
        'tracking_token',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function redact(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if ($this->isSensitiveKey($lowerKey)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->redact($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    public function maskPlayerIdentifier(array $playerFields): string
    {
        $parts = [];
        foreach ($playerFields as $key => $value) {
            $str = (string) $value;
            $len = strlen($str);
            if ($len <= 4) {
                $masked = str_repeat('*', $len);
            } else {
                $masked = substr($str, 0, 2).str_repeat('*', max(2, $len - 4)).substr($str, -2);
            }
            $parts[] = "{$key}:{$masked}";
        }

        return implode(' | ', $parts);
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
