<?php

namespace App\Services\Order;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Game;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\PriceQuote;
use App\Services\Notification\NotificationService;
use App\Services\Pricing\PriceQuoteService;
use App\Services\Security\RedactionService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly PriceQuoteService $quoteService,
        private readonly RedactionService $redaction,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array<string, string>  $playerFields
     * @return array{order: Order, tracking_token: string, reused: bool}
     */
    public function createFromQuote(
        string $quoteId,
        string $quoteSignature,
        array $playerFields,
        string $idempotencyKey,
        ?string $userId = null,
        ?string $customerEmail = null,
        ?string $customerPhone = null,
        ?string $clientIp = null,
        string $locale = 'km',
    ): array {
        return DB::transaction(function () use (
            $quoteId,
            $quoteSignature,
            $playerFields,
            $idempotencyKey,
            $userId,
            $customerEmail,
            $customerPhone,
            $clientIp,
            $locale,
        ): array {
            $existing = Order::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return [
                    'order' => $existing->load(['game', 'gamePackage', 'items', 'statusHistory']),
                    'tracking_token' => '',
                    'reused' => true,
                ];
            }

            /** @var PriceQuote $quote */
            $quote = PriceQuote::query()->lockForUpdate()->findOrFail($quoteId);
            $this->quoteService->verifyQuoteForConsumption($quote, $quoteSignature);

            /** @var Game $game */
            $game = Game::query()->with(['fields', 'regions'])->findOrFail($quote->game_id);
            $package = $quote->gamePackage()->firstOrFail();

            if (! $game->is_active || $game->is_in_maintenance) {
                throw new DomainException('Game is currently unavailable.');
            }

            $this->validatePlayerFields($game, $playerFields);

            $plainTrackingToken = bin2hex(random_bytes(32));
            $trackingTokenHash = hash('sha256', $plainTrackingToken);
            $orderNumber = 'KTU-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
            $paymentTtl = (int) config('topup.order_payment_ttl_seconds', 900);

            $order = Order::query()->create([
                'order_number' => $orderNumber,
                'tracking_token_hash' => $trackingTokenHash,
                'idempotency_key' => $idempotencyKey,
                'user_id' => $userId,
                'price_quote_id' => $quote->id,
                'game_id' => $game->id,
                'game_package_id' => $package->id,
                'region_code' => $quote->region_code,
                'status' => OrderStatus::AWAITING_PAYMENT,
                'payment_status' => PaymentStatus::PENDING,
                'fulfillment_status' => FulfillmentStatus::NOT_STARTED,
                'currency' => $quote->currency,
                'subtotal_minor' => $quote->subtotal_minor,
                'discount_minor' => $quote->discount_minor,
                'payment_fee_minor' => $quote->payment_fee_minor,
                'total_minor' => $quote->total_minor,
                'supplier_cost_minor' => $quote->supplier_cost_minor,
                'supplier_cost_currency' => $quote->supplier_cost_currency,
                'exchange_rate_usd_to_khr' => $quote->exchange_rate_usd_to_khr,
                'player_fields_encrypted' => $playerFields,
                'masked_player_identifier' => $this->redaction->maskPlayerIdentifier($playerFields),
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'client_ip' => $clientIp,
                'locale' => $locale,
                'expires_at' => now()->addSeconds($paymentTtl),
                'financial_snapshot' => [
                    'quote_id' => $quote->id,
                    'quote_pricing_snapshot' => $quote->pricing_snapshot,
                    'package_version' => $quote->package_version,
                ],
            ]);

            OrderItem::query()->create([
                'order_id' => $order->id,
                'game_package_id' => $package->id,
                'internal_sku' => $package->internal_sku,
                'name_en' => $package->name_en,
                'name_km' => $package->name_km,
                'quantity' => 1,
                'unit_price_minor' => $quote->subtotal_minor,
                'line_total_minor' => $quote->subtotal_minor,
                'currency' => $quote->currency,
            ]);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => OrderStatus::AWAITING_PAYMENT->value,
                'actor_type' => $userId ? 'customer' : 'guest',
                'actor_id' => $userId,
                'reason' => 'Order created from server-signed price quote.',
                'created_at' => now(),
            ]);

            $quote->consumed_at = now();
            $quote->save();

            if ($quote->coupon_id && $quote->discount_minor > 0) {
                Coupon::query()->where('id', $quote->coupon_id)->increment('used_count');
                CouponRedemption::query()->create([
                    'coupon_id' => $quote->coupon_id,
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'discount_minor' => $quote->discount_minor,
                    'currency' => $quote->currency,
                    'redeemed_at' => now(),
                ]);
            }

            $this->notifications->notifyOrderEvent($order, 'order_created');

            return [
                'order' => $order->load(['game', 'gamePackage', 'items', 'statusHistory']),
                'tracking_token' => $plainTrackingToken,
                'reused' => false,
            ];
        });
    }

    public function verifyTrackingAccess(Order $order, ?string $trackingToken, ?string $authenticatedUserId): bool
    {
        if ($authenticatedUserId !== null && $order->user_id !== null && hash_equals($order->user_id, $authenticatedUserId)) {
            return true;
        }

        if ($trackingToken !== null && $trackingToken !== '') {
            return hash_equals($order->tracking_token_hash, hash('sha256', $trackingToken));
        }

        return false;
    }

    /**
     * @param  array<string, string>  $playerFields
     */
    private function validatePlayerFields(Game $game, array $playerFields): void
    {
        foreach ($game->fields as $field) {
            $value = trim((string) ($playerFields[$field->key] ?? ''));
            if ($field->is_required && $value === '') {
                throw new DomainException("Required player field [{$field->label_en}] is missing.");
            }
            if ($value !== '' && $field->validation_regex) {
                if (! preg_match($field->validation_regex, $value)) {
                    throw new DomainException("Player field [{$field->label_en}] has an invalid format.");
                }
            }
        }
    }
}
