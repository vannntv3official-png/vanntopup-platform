<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case VERIFYING = 'verifying';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::VERIFYING, self::PAID, self::FAILED, self::EXPIRED],
            self::VERIFYING => [self::PAID, self::FAILED, self::EXPIRED],
            self::PAID => [self::PARTIALLY_REFUNDED, self::REFUNDED],
            self::PARTIALLY_REFUNDED => [self::REFUNDED],
            self::FAILED, self::EXPIRED, self::REFUNDED => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
