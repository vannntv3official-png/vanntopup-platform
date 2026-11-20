<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Fulfillment;
use App\Models\Game;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\ReconciliationDiscrepancy;
use App\Services\Payment\Adapters\MockPayWayAdapter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutAndFulfillmentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync']);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_complete_guest_checkout_verified_payment_and_automatic_fulfillment_flow(): void
    {
        // 1. Browse catalog & fetch Mobile Legends
        $gamesRes = $this->getJson('/api/v1/games')->assertOk();
        $this->assertCount(4, $gamesRes->json('data'));

        $mlbbRes = $this->getJson('/api/v1/games/mobile-legends')->assertOk();
        $gameId = $mlbbRes->json('data.id');
        $packageId = $mlbbRes->json('data.packages.0.id');

        // 2. Validate player
        $this->postJson('/api/v1/games/mobile-legends/validate-player', [
            'region_code' => 'KH',
            'player_fields' => ['user_id' => '123456789', 'zone_id' => '2014'],
        ])->assertOk()->assertJsonPath('valid', true);

        // 3. Create server-signed quote with KHMER10 coupon
        $quoteRes = $this->postJson('/api/v1/quotes', [
            'game_id' => $gameId,
            'package_id' => $packageId,
            'region_code' => 'KH',
            'currency' => 'USD',
            'payment_method' => 'mock_khqr',
            'coupon_code' => 'KHMER10',
        ])->assertCreated();

        $quoteId = $quoteRes->json('data.id');
        $signature = $quoteRes->json('data.signature');
        $this->assertGreaterThan(0, $quoteRes->json('data.discount_minor'));

        // 4. Create guest order
        $orderRes = $this->postJson('/api/v1/orders', [
            'quote_id' => $quoteId,
            'quote_signature' => $signature,
            'player_fields' => ['user_id' => '123456789', 'zone_id' => '2014'],
            'customer_email' => 'guest@example.com',
            'idempotency_key' => 'order-idem-test-001',
        ])->assertCreated();

        $orderNumber = $orderRes->json('data.order_number');
        $trackingToken = $orderRes->json('data.tracking_token');
        $this->assertNotEmpty($trackingToken);

        // 5. Create mock payment session
        $payRes = $this->postJson("/api/v1/orders/{$orderNumber}/payment-sessions", [
            'provider' => 'mock_payway',
            'tracking_token' => $trackingToken,
        ])->assertCreated();

        $intentId = $payRes->json('data.payment_intent_id');
        $providerRef = $payRes->json('data.reference');
        $amountMinor = $payRes->json('data.amount_minor');

        // 6. Simulate verified payment webhook
        $this->postJson("/api/v1/payments/{$intentId}/simulate", [
            'outcome' => 'paid',
            'tracking_token' => $trackingToken,
        ])->assertOk()->assertJsonPath('data.status', 'paid');

        // 7. Verify order is completed with exactly ONE fulfillment & balanced ledger entries
        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();
        $this->assertSame(OrderStatus::COMPLETED, $order->status);
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);
        $this->assertSame(FulfillmentStatus::SUCCEEDED, $order->fulfillment_status);
        $this->assertSame(1, Fulfillment::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, $order->fulfillment->attempts()->count());

        // Balanced double-entry ledger: 1 payment revenue tx + 1 supplier COGS tx
        $this->assertSame(2, LedgerTransaction::query()->count());

        // 8. Replay duplicate webhook -> must be ignored idempotently with ZERO duplicate fulfillment
        $adapter = app(MockPayWayAdapter::class);
        $secret = (string) config('topup.payment.mock_webhook_secret');
        $sig = $adapter->computeSignature($providerRef, 'paid', $amountMinor, 'USD', $secret);

        $this->postJson('/api/v1/webhooks/payments/mock_payway', [
            'event_id' => 'dup-webhook-event-2',
            'provider_reference' => $providerRef,
            'status' => 'paid',
            'amount_minor' => $amountMinor,
            'currency' => 'USD',
            'signature' => $sig,
        ], ['X-Mock-Signature' => $sig])
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $this->assertSame(1, Fulfillment::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, $order->fulfillment->attempts()->count());

        // 9. Track order with valid tracking token
        $this->getJson("/api/v1/orders/{$orderNumber}/track?token={$trackingToken}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonCount(4, 'data.timeline');
    }

    public function test_supplier_timeout_enters_safe_reconciliation_and_unresolved_enters_needs_review(): void
    {
        $game = Game::query()->where('slug', 'free-fire')->firstOrFail();
        $pkg = $game->packages()->firstOrFail();

        // Unresolved timeout trigger: 666666666
        $quoteRes = $this->postJson('/api/v1/quotes', [
            'game_id' => $game->id,
            'package_id' => $pkg->id,
            'region_code' => 'KH',
            'currency' => 'USD',
            'payment_method' => 'mock_khqr',
        ])->assertCreated();

        $orderRes = $this->postJson('/api/v1/orders', [
            'quote_id' => $quoteRes->json('data.id'),
            'quote_signature' => $quoteRes->json('data.signature'),
            'player_fields' => ['player_id' => '666666666'],
            'idempotency_key' => 'order-timeout-unresolved-001',
        ])->assertCreated();

        $orderNumber = $orderRes->json('data.order_number');
        $trackingToken = $orderRes->json('data.tracking_token');

        $payRes = $this->postJson("/api/v1/orders/{$orderNumber}/payment-sessions", [
            'provider' => 'mock_payway',
            'tracking_token' => $trackingToken,
        ])->assertCreated();

        $this->postJson("/api/v1/payments/{$payRes->json('data.payment_intent_id')}/simulate", [
            'outcome' => 'paid',
            'tracking_token' => $trackingToken,
        ])->assertOk();

        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();
        $this->assertSame(OrderStatus::NEEDS_REVIEW, $order->status);
        $this->assertSame(FulfillmentStatus::NEEDS_REVIEW, $order->fulfillment_status);
        $this->assertSame(1, ReconciliationDiscrepancy::query()->where('order_id', $order->id)->count());
    }
}
