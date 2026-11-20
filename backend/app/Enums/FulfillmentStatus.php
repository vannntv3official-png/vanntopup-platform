<?php

namespace App\Enums;

enum FulfillmentStatus: string
{
    case NOT_STARTED = 'not_started';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case UNCERTAIN = 'uncertain';
    case NEEDS_REVIEW = 'needs_review';
    case CANCELLED = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NOT_STARTED => [self::QUEUED, self::CANCELLED],
            self::QUEUED => [self::PROCESSING, self::NEEDS_REVIEW, self::CANCELLED],
            self::PROCESSING => [self::SUCCEEDED, self::FAILED, self::UNCERTAIN, self::NEEDS_REVIEW],
            self::UNCERTAIN => [self::SUCCEEDED, self::FAILED, self::NEEDS_REVIEW],
            self::FAILED => [self::QUEUED, self::NEEDS_REVIEW, self::CANCELLED],
            self::NEEDS_REVIEW => [self::QUEUED, self::SUCCEEDED, self::FAILED, self::CANCELLED],
            self::SUCCEEDED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
