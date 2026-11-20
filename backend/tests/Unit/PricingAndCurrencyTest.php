<?php

namespace Tests\Unit;

use App\Enums\Currency;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\GamePackage;
use App\Services\Accounting\LedgerService;
use App\Services\Pricing\PricingService;
use App\Services\Security\MfaService;
use App\Services\Security\ProductionSafetyGuard;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class PricingAndCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_usd_and_khr_minor_unit_conversion_respects_currency_precision(): void
    {
        $pricing = app(PricingService::class);

        // $1.50 (150 cents) at 4100 KHR/USD = 6,150 KHR
        $khr = $pricing->convertMinorUnits(150, Currency::USD, Currency::KHR, 4100.0);
        $this->assertSame(6150, $khr);

        // 4,100 KHR back to USD cents = 100 cents ($1.00)
        $usd = $pricing->convertMinorUnits(4100, Currency::KHR, Currency::USD, 4100.0);
        $this->assertSame(100, $usd);
    }

    public function test_pricing_enforces_minimum_profit_floor(): void
    {
        $pricing = app(PricingService::class);
        $pkg = GamePackage::query()->where('internal_sku', 'MLBB-86D')->firstOrFail();
        $pkg->selling_price_minor = 100; // below base_cost (120) + min_profit (15) = 135
        $pkg->save();

        $breakdown = $pricing->calculateBreakdown($pkg, Currency::USD, 'KH', 'mock_khqr');
        $this->assertGreaterThanOrEqual(135, $breakdown['subtotal_minor']);
        $this->assertGreaterThanOrEqual(15, $breakdown['estimated_profit_minor']);
    }

    public function test_order_and_fulfillment_state_machines_block_invalid_transitions(): void
    {
        $this->assertTrue(OrderStatus::AWAITING_PAYMENT->canTransitionTo(OrderStatus::PAID));
        $this->assertFalse(OrderStatus::AWAITING_PAYMENT->canTransitionTo(OrderStatus::COMPLETED));
        $this->assertFalse(OrderStatus::COMPLETED->canTransitionTo(OrderStatus::AWAITING_PAYMENT));

        $this->assertTrue(FulfillmentStatus::PROCESSING->canTransitionTo(FulfillmentStatus::UNCERTAIN));
        $this->assertFalse(FulfillmentStatus::SUCCEEDED->canTransitionTo(FulfillmentStatus::QUEUED));
    }

    public function test_ledger_rejects_unbalanced_double_entry_transactions(): void
    {
        $ledger = app(LedgerService::class);

        $this->expectException(InvalidArgumentException::class);
        $ledger->postTransaction(
            referenceType: 'test',
            referenceId: '00000000-0000-0000-0000-000000000001',
            idempotencyKey: 'unbalanced-test-1',
            currency: 'USD',
            description: 'Must fail because debits != credits',
            lines: [
                ['account_code' => '1000_GATEWAY_RECEIVABLE_USD', 'direction' => 'debit', 'amount_minor' => 500],
                ['account_code' => '4000_TOPUP_REVENUE_USD', 'direction' => 'credit', 'amount_minor' => 450],
            ],
        );
    }

    public function test_totp_mfa_generates_and_verifies_6_digit_codes(): void
    {
        $mfa = app(MfaService::class);
        $secret = $mfa->generateSecret();
        $code = $mfa->generateCode($secret);

        $this->assertSame(6, strlen($code));
        $this->assertTrue($mfa->verifyCode($secret, $code));
        $this->assertFalse($mfa->verifyCode($secret, '000000'));
    }

    public function test_production_safety_guard_blocks_mock_providers_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['topup.allow_mocks_in_production' => false]);

        $guard = app(ProductionSafetyGuard::class);
        $this->expectException(RuntimeException::class);
        $guard->assertSafeProvider('mock_payway', 'payment');
    }
}
