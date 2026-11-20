<?php

namespace App\Services\Security;

use RuntimeException;

class ProductionSafetyGuard
{
    public function assertSafeProvider(string $providerCode, string $providerType): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if (config('topup.allow_mocks_in_production', false)) {
            return;
        }

        $isMock = str_starts_with(strtolower($providerCode), 'mock');

        if ($isMock) {
            throw new RuntimeException(
                "Mock {$providerType} provider [{$providerCode}] is forbidden in production environment."
            );
        }
    }
}
