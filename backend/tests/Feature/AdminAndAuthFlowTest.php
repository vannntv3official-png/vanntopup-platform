<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Game;
use App\Models\Order;
use App\Models\User;
use App\Services\Security\MfaService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAndAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync']);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_customer_registration_login_and_mfa_flow(): void
    {
        $reg = $this->postJson('/api/v1/auth/register', [
            'name' => 'Dara Phnom Penh',
            'email' => 'dara@example.com',
            'phone' => '+85512345678',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'preferred_locale' => 'km',
            'preferred_currency' => 'KHR',
        ])->assertCreated();

        $token = $reg->json('token');

        // Setup MFA
        $setup = $this->withToken($token)->postJson('/api/v1/auth/mfa/setup')->assertOk();
        $secret = $setup->json('secret');
        $code = app(MfaService::class)->generateCode($secret);

        $this->withToken($token)->postJson('/api/v1/auth/mfa/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.mfa_enabled', true);

        // Login without MFA code must be rejected with mfa_required
        $this->postJson('/api/v1/auth/login', [
            'email' => 'dara@example.com',
            'password' => 'StrongPass123!',
        ])->assertUnprocessable()->assertJsonPath('mfa_required', true);

        // Login with valid MFA code succeeds
        $this->postJson('/api/v1/auth/login', [
            'email' => 'dara@example.com',
            'password' => 'StrongPass123!',
            'mfa_code' => $code,
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_supplier_timeout_reconciles_to_completed_via_status_lookup(): void
    {
        $game = Game::query()->where('slug', 'free-fire')->firstOrFail();
        $pkg = $game->packages()->firstOrFail();

        $quote = $this->postJson('/api/v1/quotes', [
            'game_id' => $game->id,
            'package_id' => $pkg->id,
            'region_code' => 'KH',
            'currency' => 'KHR',
        ])->assertCreated();

        // 888888888 triggers timeout on submit, then succeeded on status_lookup
        $orderRes = $this->postJson('/api/v1/orders', [
            'quote_id' => $quote->json('data.id'),
            'quote_signature' => $quote->json('data.signature'),
            'player_fields' => ['player_id' => '888888888'],
            'idempotency_key' => 'timeout-recon-khr-001',
        ])->assertCreated();

        $orderNumber = $orderRes->json('data.order_number');
        $trackingToken = $orderRes->json('data.tracking_token');

        $pay = $this->postJson("/api/v1/orders/{$orderNumber}/payment-sessions", [
            'provider' => 'mock_payway',
            'tracking_token' => $trackingToken,
        ])->assertCreated();

        $this->postJson("/api/v1/payments/{$pay->json('data.payment_intent_id')}/simulate", [
            'outcome' => 'paid',
            'tracking_token' => $trackingToken,
        ])->assertOk();

        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();
        $this->assertSame(OrderStatus::COMPLETED, $order->status);
        $this->assertSame(FulfillmentStatus::SUCCEEDED, $order->fulfillment_status);
        $this->assertSame('KHR', $order->currency);
        // Attempt 1 = submit (timeout_uncertain), Attempt 2 = status_lookup (succeeded)
        $this->assertSame(2, $order->fulfillment->attempts()->count());
    }

    public function test_definitive_supplier_failure_can_be_refunded_by_admin(): void
    {
        $game = Game::query()->where('slug', 'free-fire')->firstOrFail();
        $pkg = $game->packages()->firstOrFail();

        $quote = $this->postJson('/api/v1/quotes', [
            'game_id' => $game->id,
            'package_id' => $pkg->id,
            'region_code' => 'KH',
            'currency' => 'USD',
        ])->assertCreated();

        // 999999999 triggers definitive failure
        $orderRes = $this->postJson('/api/v1/orders', [
            'quote_id' => $quote->json('data.id'),
            'quote_signature' => $quote->json('data.signature'),
            'player_fields' => ['player_id' => '999999999'],
            'idempotency_key' => 'fail-refund-001',
        ])->assertCreated();

        $orderNumber = $orderRes->json('data.order_number');
        $trackingToken = $orderRes->json('data.tracking_token');

        $pay = $this->postJson("/api/v1/orders/{$orderNumber}/payment-sessions", [
            'provider' => 'mock_payway',
            'tracking_token' => $trackingToken,
        ])->assertCreated();

        $this->postJson("/api/v1/payments/{$pay->json('data.payment_intent_id')}/simulate", [
            'outcome' => 'paid',
            'tracking_token' => $trackingToken,
        ])->assertOk();

        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();
        $this->assertSame(OrderStatus::FAILED, $order->status);
        $this->assertSame(FulfillmentStatus::FAILED, $order->fulfillment_status);

        $admin = User::query()->where('email', 'admin@khmertopup.local')->firstOrFail();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'reason' => 'Supplier rejected ineligible player ID.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'succeeded');

        $order->refresh();
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payment_status);
    }
}
