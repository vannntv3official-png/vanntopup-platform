<?php

namespace App\Enums;

enum OrderStatus: string
{
    case DRAFT = 'draft';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case PAYMENT_VERIFYING = 'payment_verifying';
    case PAID = 'paid';
    case FULFILLING = 'fulfilling';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case NEEDS_REVIEW = 'needs_review';
    case REFUNDED = 'refunded';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::AWAITING_PAYMENT, self::CANCELLED],
            self::AWAITING_PAYMENT => [self::PAYMENT_VERIFYING, self::PAID, self::CANCELLED, self::EXPIRED, self::FAILED],
            self::PAYMENT_VERIFYING => [self::PAID, self::FAILED, self::EXPIRED, self::NEEDS_REVIEW],
            self::PAID => [self::FULFILLING, self::NEEDS_REVIEW, self::REFUNDED],
            self::FULFILLING => [self::COMPLETED, self::FAILED, self::NEEDS_REVIEW],
            self::FAILED => [self::FULFILLING, self::NEEDS_REVIEW, self::REFUNDED],
            self::NEEDS_REVIEW => [self::FULFILLING, self::COMPLETED, self::FAILED, self::REFUNDED],
            self::COMPLETED => [self::REFUNDED],
            self::CANCELLED, self::EXPIRED, self::REFUNDED => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
