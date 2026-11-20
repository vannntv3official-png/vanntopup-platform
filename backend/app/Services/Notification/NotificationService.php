<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\Order;

class NotificationService
{
    public function notifyOrderEvent(Order $order, string $eventType): void
    {
        [$subject, $body] = match ($eventType) {
            'order_created' => [
                "Order {$order->order_number} Created",
                "Your top-up order {$order->order_number} ({$order->masked_player_identifier}) is awaiting payment.",
            ],
            'payment_confirmed' => [
                "Payment Confirmed — {$order->order_number}",
                "Payment verified for order {$order->order_number}. Automatic top-up is now queued.",
            ],
            'topup_processing' => [
                "Top-Up Processing — {$order->order_number}",
                "We are delivering your game currency for order {$order->order_number}.",
            ],
            'topup_completed' => [
                "Top-Up Completed — {$order->order_number}",
                "Your order {$order->order_number} has been delivered successfully.",
            ],
            'order_requires_support' => [
                "Order Under Review — {$order->order_number}",
                "Order {$order->order_number} requires verification by our support team. No duplicate charge has been made.",
            ],
            'refund_completed' => [
                "Refund Completed — {$order->order_number}",
                "Your refund for order {$order->order_number} has been processed.",
            ],
            default => [
                "Order Update — {$order->order_number}",
                "Status updated for order {$order->order_number}.",
            ],
        };

        // Always create idempotent in-app notification
        $this->createIdempotent(
            order: $order,
            channel: 'in_app',
            eventType: $eventType,
            recipientMasked: $order->user_id ? 'account_user' : 'guest_order',
            subject: $subject,
            body: $body,
            deliveryStatus: 'sent',
        );

        // Queue email notification when customer email is provided
        if ($order->customer_email) {
            $maskedEmail = $this->maskEmail($order->customer_email);
            $this->createIdempotent(
                order: $order,
                channel: 'email',
                eventType: $eventType,
                recipientMasked: $maskedEmail,
                subject: $subject,
                body: $body,
                deliveryStatus: app()->environment('testing', 'local') ? 'sent' : 'queued',
            );
        }
    }

    private function createIdempotent(
        Order $order,
        string $channel,
        string $eventType,
        string $recipientMasked,
        string $subject,
        string $body,
        string $deliveryStatus,
    ): void {
        $idempotencyKey = "notif:{$order->id}:{$channel}:{$eventType}";

        Notification::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'channel' => $channel,
                'event_type' => $eventType,
                'recipient_masked' => $recipientMasked,
                'subject' => $subject,
                'body' => $body,
                'delivery_status' => $deliveryStatus,
                'sent_at' => $deliveryStatus === 'sent' ? now() : null,
            ],
        );
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }
        $local = $parts[0];
        $visible = strlen($local) <= 2 ? substr($local, 0, 1) : substr($local, 0, 2);

        return $visible.'***@'.$parts[1];
    }
}
