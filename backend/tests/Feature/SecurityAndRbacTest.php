<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Game;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Fulfillment\FulfillmentOrchestrator;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync']);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_order_cannot_be_tracked_with_order_number_alone(): void
    {
        $game = Game::query()->where('slug', 'pubg-mobile')->firstOrFail();
        $pkg = $game->packages()->firstOrFail();

        $quote = $this->postJson('/api/v1/quotes', [
            'game_id' => $game->id,
            'package_id' => $pkg->id,
            'region_code' => 'GLOBAL',
            'currency' => 'USD',
        ])->assertCreated();

        $orderRes = $this->postJson('/api/v1/orders', [
            'quote_id' => $quote->json('data.id'),
            'quote_signature' => $quote->json('data.signature'),
            'player_fields' => ['character_id' => '5123456789'],
            'idempotency_key' => 'sec-order-001',
        ])->assertCreated();

        $orderNumber = $orderRes->json('data.order_number');

        // Without tracking token -> 403
        $this->getJson("/api/v1/orders/{$orderNumber}/track")->assertForbidden();
        // With forged tracking token -> 403
        $this->getJson("/api/v1/orders/{$orderNumber}/track?token=forged-token-123")->assertForbidden();
    }

    public function test_tampered_quote_signature_is_rejected(): void
    {
        $game = Game::query()->where('slug', 'honor-of-kings')->firstOrFail();
        $pkg = $game->packages()->firstOrFail();

        $quote = $this->postJson('/api/v1/quotes', [
            'game_id' => $game->id,
            'package_id' => $pkg->id,
            'region_code' => 'GLOBAL',
            'currency' => 'USD',
        ])->assertCreated();

        $this->postJson('/api/v1/orders', [
            'quote_id' => $quote->json('data.id'),
            'quote_signature' => str_repeat('a', 64),
            'player_fields' => ['player_uid' => '9012345678'],
            'idempotency_key' => 'tamper-test-001',
        ])->assertUnprocessable();
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $this->postJson('/api/v1/webhooks/payments/mock_payway', [
            'provider_reference' => 'MOCK-PW-FAKE',
            'status' => 'paid',
            'amount_minor' => 100,
            'currency' => 'USD',
            'signature' => 'invalid-signature',
        ], ['X-Mock-Signature' => 'invalid-signature'])
            ->assertStatus(400);
    }

    public function test_zero_fulfillment_before_verified_payment_invariant(): void
    {
        $game = Game::query()->where('slug', 'pubg-mobile')->firstOrFail();
        $pkg = $game->packages()->firstOrFail();

        $quote = $this->postJson('/api/v1/quotes', [
            'game_id' => $game->id,
            'package_id' => $pkg->id,
            'region_code' => 'GLOBAL',
            'currency' => 'USD',
        ])->assertCreated();

        $orderRes = $this->postJson('/api/v1/orders', [
            'quote_id' => $quote->json('data.id'),
            'quote_signature' => $quote->json('data.signature'),
            'player_fields' => ['character_id' => '5123456789'],
            'idempotency_key' => 'unpaid-fulfill-001',
        ])->assertCreated();

        $order = Order::query()->where('order_number', $orderRes->json('data.order_number'))->firstOrFail();

        $this->expectException(DomainException::class);
        app(FulfillmentOrchestrator::class)->initiateForPaidOrder($order->id);
    }

    public function test_admin_endpoints_enforce_rbac(): void
    {
        // Guest -> 401
        $this->getJson('/api/v1/admin/overview')->assertUnauthorized();

        // Customer -> 403
        $customer = User::query()->where('email', 'player@khmertopup.local')->firstOrFail();
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/admin/overview')
            ->assertForbidden();

        // Super Admin -> 200
        $admin = User::query()->where('email', 'admin@khmertopup.local')->firstOrFail();
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/overview')
            ->assertOk()
            ->assertJsonStructure(['data' => ['metrics', 'controls', 'suppliers']]);
    }

    public function test_global_fulfillment_pause_holds_paid_orders_in_needs_review(): void
    {
        SystemSetting::setValue('fulfillment_global_pause', true, 'operations', false);

        $game = Game::query()->where('slug', 'free-fire')->firstOrFail();
        $pkg = $game->packages()->firstOrFail();

        $quote = $this->postJson('/api/v1/quotes', [
            'game_id' => $game->id,
            'package_id' => $pkg->id,
            'region_code' => 'KH',
            'currency' => 'USD',
        ])->assertCreated();

        $orderRes = $this->postJson('/api/v1/orders', [
            'quote_id' => $quote->json('data.id'),
            'quote_signature' => $quote->json('data.signature'),
            'player_fields' => ['player_id' => '2345678901'],
            'idempotency_key' => 'pause-switch-001',
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
    }
}
