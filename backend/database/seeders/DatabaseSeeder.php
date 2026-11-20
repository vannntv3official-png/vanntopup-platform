<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerProfile;
use App\Models\Game;
use App\Models\GameAsset;
use App\Models\GameField;
use App\Models\GameLocalization;
use App\Models\GamePackage;
use App\Models\GameRegion;
use App\Models\LedgerAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierBalanceSnapshot;
use App\Models\SupplierCapability;
use App\Models\SupplierProduct;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Fulfillment\Adapters\KasPlayAdapter;
use App\Services\Fulfillment\Adapters\MockSupplierAdapter;
use App\Services\Fulfillment\Adapters\UniPinAdapter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRbacAndUsers();
        $this->seedLedgerAccounts();
        $this->seedSystemSettings();
        $mockSupplier = $this->seedSuppliers();
        $this->seedCatalogAndPackages($mockSupplier);
        $this->seedPromotions();
    }

    private function seedRbacAndUsers(): void
    {
        $permissionsData = [
            ['slug' => 'dashboard.view', 'name' => 'View Admin Dashboard', 'module' => 'dashboard'],
            ['slug' => 'orders.view', 'name' => 'View Orders', 'module' => 'orders'],
            ['slug' => 'orders.manage', 'name' => 'Manage & Resolve Orders', 'module' => 'orders'],
            ['slug' => 'catalog.view', 'name' => 'View Catalog', 'module' => 'catalog'],
            ['slug' => 'catalog.manage', 'name' => 'Manage Games & Packages', 'module' => 'catalog'],
            ['slug' => 'suppliers.view', 'name' => 'View Suppliers', 'module' => 'suppliers'],
            ['slug' => 'suppliers.manage', 'name' => 'Manage Suppliers', 'module' => 'suppliers'],
            ['slug' => 'payments.view', 'name' => 'View Payments', 'module' => 'payments'],
            ['slug' => 'payments.refund', 'name' => 'Process Refunds', 'module' => 'payments'],
            ['slug' => 'reconciliation.view', 'name' => 'View Reconciliation', 'module' => 'reconciliation'],
            ['slug' => 'reconciliation.manage', 'name' => 'Resolve Discrepancies', 'module' => 'reconciliation'],
            ['slug' => 'promotions.view', 'name' => 'View Promotions', 'module' => 'promotions'],
            ['slug' => 'promotions.manage', 'name' => 'Manage Promotions', 'module' => 'promotions'],
            ['slug' => 'customers.view', 'name' => 'View Customers', 'module' => 'customers'],
            ['slug' => 'settings.view', 'name' => 'View Settings', 'module' => 'settings'],
            ['slug' => 'settings.manage', 'name' => 'Manage Platform Settings', 'module' => 'settings'],
            ['slug' => 'audit.view', 'name' => 'View Audit Logs', 'module' => 'audit'],
        ];

        $permissions = [];
        foreach ($permissionsData as $item) {
            $permissions[$item['slug']] = Permission::query()->firstOrCreate(['slug' => $item['slug']], $item);
        }

        $superAdminRole = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Administrator', 'description' => 'Full platform access', 'is_system' => true],
        );
        $superAdminRole->permissions()->sync(collect($permissions)->pluck('id'));

        $opsRole = Role::query()->firstOrCreate(
            ['slug' => 'operations_admin'],
            ['name' => 'Operations Manager', 'description' => 'Orders, suppliers, catalog, reconciliation', 'is_system' => true],
        );
        $opsRole->permissions()->sync(
            collect($permissions)->only([
                'dashboard.view', 'orders.view', 'orders.manage', 'catalog.view', 'catalog.manage',
                'suppliers.view', 'suppliers.manage', 'reconciliation.view', 'reconciliation.manage', 'customers.view',
            ])->pluck('id')
        );

        $customerRole = Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer', 'description' => 'Registered storefront customer', 'is_system' => true],
        );

        $adminUser = User::query()->firstOrCreate(
            ['email' => 'admin@khmertopup.local'],
            [
                'name' => 'Platform Super Admin',
                'phone' => '+85512000001',
                'password' => Hash::make('AdminPass123!'),
                'preferred_locale' => 'en',
                'preferred_currency' => 'USD',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
        $adminUser->roles()->syncWithoutDetaching([$superAdminRole->id]);

        $customerUser = User::query()->firstOrCreate(
            ['email' => 'player@khmertopup.local'],
            [
                'name' => 'Sokha Gamer',
                'phone' => '+85512888999',
                'password' => Hash::make('CustomerPass123!'),
                'preferred_locale' => 'km',
                'preferred_currency' => 'USD',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
        $customerUser->roles()->syncWithoutDetaching([$customerRole->id]);
        CustomerProfile::query()->firstOrCreate(['user_id' => $customerUser->id]);
    }

    private function seedLedgerAccounts(): void
    {
        $accounts = [
            ['code' => '1000_GATEWAY_RECEIVABLE_USD', 'name' => 'Payment Gateway Receivable (USD)', 'account_type' => 'asset', 'currency' => 'USD'],
            ['code' => '1100_SUPPLIER_WALLET_USD', 'name' => 'Supplier Reseller Wallet (USD)', 'account_type' => 'asset', 'currency' => 'USD'],
            ['code' => '4000_TOPUP_REVENUE_USD', 'name' => 'Game Top-Up Sales Revenue (USD)', 'account_type' => 'revenue', 'currency' => 'USD'],
            ['code' => '4100_REFUNDS_CONTRA_USD', 'name' => 'Customer Refunds Contra-Revenue (USD)', 'account_type' => 'revenue', 'currency' => 'USD'],
            ['code' => '5000_SUPPLIER_COGS_USD', 'name' => 'Supplier Top-Up COGS (USD)', 'account_type' => 'expense', 'currency' => 'USD'],

            ['code' => '1000_GATEWAY_RECEIVABLE_KHR', 'name' => 'Payment Gateway Receivable (KHR)', 'account_type' => 'asset', 'currency' => 'KHR'],
            ['code' => '1100_SUPPLIER_WALLET_KHR', 'name' => 'Supplier Reseller Wallet (KHR)', 'account_type' => 'asset', 'currency' => 'KHR'],
            ['code' => '4000_TOPUP_REVENUE_KHR', 'name' => 'Game Top-Up Sales Revenue (KHR)', 'account_type' => 'revenue', 'currency' => 'KHR'],
            ['code' => '4100_REFUNDS_CONTRA_KHR', 'name' => 'Customer Refunds Contra-Revenue (KHR)', 'account_type' => 'revenue', 'currency' => 'KHR'],
            ['code' => '5000_SUPPLIER_COGS_KHR', 'name' => 'Supplier Top-Up COGS (KHR)', 'account_type' => 'expense', 'currency' => 'KHR'],
        ];

        foreach ($accounts as $account) {
            LedgerAccount::query()->firstOrCreate(['code' => $account['code']], $account);
        }
    }

    private function seedSystemSettings(): void
    {
        SystemSetting::setValue('exchange_rate_usd_to_khr', 4100, 'pricing', true);
        SystemSetting::setValue('default_currency', 'USD', 'general', true);
        SystemSetting::setValue('default_locale', 'km', 'general', true);
        SystemSetting::setValue('fulfillment_global_pause', false, 'operations', false);
    }

    private function seedSuppliers(): Supplier
    {
        $mock = Supplier::query()->firstOrCreate(
            ['code' => 'mock_supplier'],
            [
                'name' => 'Mock Supplier (DEV/TEST ONLY)',
                'adapter_class' => MockSupplierAdapter::class,
                'environment' => 'mock',
                'is_active' => true,
                'is_paused' => false,
                'is_maintenance' => false,
                'priority' => 10,
                'reliability_score' => 99.90,
                'default_currency' => 'USD',
                'health_status' => 'healthy',
                'last_health_check_at' => now(),
            ],
        );

        SupplierCapability::query()->firstOrCreate(
            ['supplier_id' => $mock->id],
            [
                'supports_catalog_sync' => true,
                'supports_pricing_query' => true,
                'supports_player_validation' => true,
                'supports_submit_order' => true,
                'supports_status_lookup' => true,
                'supports_balance_check' => true,
                'supports_webhooks' => false,
                'supports_idempotency' => true,
                'supported_currencies' => ['USD'],
                'supported_regions' => ['GLOBAL', 'KH', 'SEA'],
            ],
        );

        SupplierBalanceSnapshot::query()->firstOrCreate(
            ['supplier_id' => $mock->id, 'currency' => 'USD'],
            ['available_balance_minor' => 500_000, 'reserved_balance_minor' => 0, 'captured_at' => now()],
        );

        // Real supplier adapters registered as inactive until reseller credentials are verified
        $kasPlay = Supplier::query()->firstOrCreate(
            ['code' => 'kas_play'],
            [
                'name' => 'KAS Play (Authorized Reseller — Pending Credentials)',
                'adapter_class' => KasPlayAdapter::class,
                'environment' => 'sandbox',
                'is_active' => false,
                'is_paused' => true,
                'is_maintenance' => false,
                'priority' => 20,
                'reliability_score' => 98.50,
                'default_currency' => 'USD',
                'health_status' => 'down',
            ],
        );
        SupplierCapability::query()->firstOrCreate(['supplier_id' => $kasPlay->id], [
            'supports_submit_order' => true,
            'supports_status_lookup' => true,
            'supports_idempotency' => true,
            'supported_currencies' => ['USD'],
            'supported_regions' => ['KH', 'SEA', 'GLOBAL'],
        ]);

        $unipin = Supplier::query()->firstOrCreate(
            ['code' => 'unipin'],
            [
                'name' => 'UniPin (Partner API — Pending Credentials)',
                'adapter_class' => UniPinAdapter::class,
                'environment' => 'sandbox',
                'is_active' => false,
                'is_paused' => true,
                'is_maintenance' => false,
                'priority' => 30,
                'reliability_score' => 98.00,
                'default_currency' => 'USD',
                'health_status' => 'down',
            ],
        );
        SupplierCapability::query()->firstOrCreate(['supplier_id' => $unipin->id], [
            'supports_submit_order' => true,
            'supports_status_lookup' => true,
            'supports_idempotency' => true,
            'supported_currencies' => ['USD'],
            'supported_regions' => ['KH', 'SEA', 'GLOBAL'],
        ]);

        return $mock;
    }

    private function seedCatalogAndPackages(Supplier $mockSupplier): void
    {
        $mobaCat = Category::query()->firstOrCreate(
            ['slug' => 'moba'],
            ['name_en' => 'MOBA Games', 'name_km' => 'ហ្គេម MOBA', 'sort_order' => 1, 'is_active' => true],
        );
        $brCat = Category::query()->firstOrCreate(
            ['slug' => 'battle-royale'],
            ['name_en' => 'Battle Royale', 'name_km' => 'ហ្គេមបាញ់ប្រហារ', 'sort_order' => 2, 'is_active' => true],
        );

        $gamesSpec = [
            [
                'slug' => 'mobile-legends',
                'name' => 'Mobile Legends: Bang Bang',
                'publisher' => 'MOONTON',
                'category_id' => $mobaCat->id,
                'is_featured' => true,
                'sort_order' => 1,
                'short_en' => 'Instant Diamonds & Weekly Diamond Pass top-up for Cambodia players.',
                'short_km' => 'បញ្ចូលពេជ្រ (Diamonds) និង Weekly Pass ភ្លាមៗដោយស្វ័យប្រវត្តិសម្រាប់កម្ពុជា។',
                'desc_en' => 'Top up Mobile Legends: Bang Bang Diamonds in seconds. Enter your User ID and Zone ID, pay securely via KHQR, and receive Diamonds directly in your account.',
                'desc_km' => 'បញ្ចូលពេជ្រ Mobile Legends: Bang Bang យ៉ាងរហ័ស និងសុវត្ថិភាព។ បញ្ចូល User ID និង Zone ID ទូទាត់តាម KHQR ហើយទទួលពេជ្រភ្លាមៗ។',
                'thumb' => '/images/games/mlbb-thumb.svg',
                'banner' => '/images/games/mlbb-banner.svg',
                'regions' => [
                    ['code' => 'KH', 'name_en' => 'Cambodia / SEA', 'name_km' => 'កម្ពុជា / អាស៊ីអាគ្នេយ៍'],
                    ['code' => 'GLOBAL', 'name_en' => 'Global Server', 'name_km' => 'ម៉ាស៊ីនមេសកល'],
                ],
                'fields' => [
                    ['key' => 'user_id', 'label_en' => 'User ID', 'label_km' => 'លេខសម្គាល់អ្នកលេង (User ID)', 'placeholder_en' => 'e.g. 123456789', 'placeholder_km' => 'ឧ. 123456789', 'field_type' => 'number', 'validation_regex' => '/^\d{6,12}$/'],
                    ['key' => 'zone_id', 'label_en' => 'Zone ID', 'label_km' => 'លេខតំបន់ (Zone ID)', 'placeholder_en' => 'e.g. 2014', 'placeholder_km' => 'ឧ. 2014', 'field_type' => 'number', 'validation_regex' => '/^\d{4,6}$/'],
                ],
                'packages' => [
                    ['sku' => 'MLBB-WDP', 'name_en' => 'Weekly Diamond Pass', 'name_km' => 'Weekly Diamond Pass', 'ent_en' => '80 Diamonds + 20 daily for 7 days', 'ent_km' => 'ពេជ្រ 80 + 20 រៀងរាល់ថ្ងៃរយៈពេល 7 ថ្ងៃ', 'bonus' => 'Best Value', 'cost' => 155, 'price' => 190, 'featured' => true],
                    ['sku' => 'MLBB-86D', 'name_en' => '86 Diamonds', 'name_km' => '86 ពេជ្រ (Diamonds)', 'ent_en' => '78 + 8 Bonus Diamonds', 'ent_km' => '78 + 8 ពេជ្របន្ថែម', 'bonus' => '+8 Bonus', 'cost' => 120, 'price' => 150, 'featured' => false],
                    ['sku' => 'MLBB-172D', 'name_en' => '172 Diamonds', 'name_km' => '172 ពេជ្រ (Diamonds)', 'ent_en' => '156 + 16 Bonus Diamonds', 'ent_km' => '156 + 16 ពេជ្របន្ថែម', 'bonus' => '+16 Bonus', 'cost' => 240, 'price' => 295, 'featured' => false],
                    ['sku' => 'MLBB-257D', 'name_en' => '257 Diamonds', 'name_km' => '257 ពេជ្រ (Diamonds)', 'ent_en' => '234 + 23 Bonus Diamonds', 'ent_km' => '234 + 23 ពេជ្របន្ថែម', 'bonus' => '+23 Bonus', 'cost' => 360, 'price' => 440, 'featured' => true],
                    ['sku' => 'MLBB-706D', 'name_en' => '706 Diamonds', 'name_km' => '706 ពេជ្រ (Diamonds)', 'ent_en' => '625 + 81 Bonus Diamonds', 'ent_km' => '625 + 81 ពេជ្របន្ថែម', 'bonus' => '+81 Bonus', 'cost' => 960, 'price' => 1150, 'featured' => false],
                ],
            ],
            [
                'slug' => 'free-fire',
                'name' => 'Garena Free Fire',
                'publisher' => 'Garena',
                'category_id' => $brCat->id,
                'is_featured' => true,
                'sort_order' => 2,
                'short_en' => 'Official Garena Free Fire Diamonds top-up using Player ID.',
                'short_km' => 'បញ្ចូលពេជ្រ Garena Free Fire ដោយស្វ័យប្រវត្តិតាមរយៈ Player ID។',
                'desc_en' => 'Fast and automatic Free Fire Diamonds top-up in Cambodia. No password needed — only your Player ID.',
                'desc_km' => 'បញ្ចូលពេជ្រ Free Fire រហ័សទាន់ចិត្ត ដោយគ្រាន់តែប្រើ Player ID មិនចាំបាច់ប្រើពាក្យសម្ងាត់ឡើយ។',
                'thumb' => '/images/games/ff-thumb.svg',
                'banner' => '/images/games/ff-banner.svg',
                'regions' => [
                    ['code' => 'KH', 'name_en' => 'Cambodia / SEA', 'name_km' => 'កម្ពុជា / អាស៊ីអាគ្នេយ៍'],
                ],
                'fields' => [
                    ['key' => 'player_id', 'label_en' => 'Player ID', 'label_km' => 'លេខសម្គាល់អ្នកលេង (Player ID)', 'placeholder_en' => 'e.g. 2345678901', 'placeholder_km' => 'ឧ. 2345678901', 'field_type' => 'number', 'validation_regex' => '/^\d{7,12}$/'],
                ],
                'packages' => [
                    ['sku' => 'FF-100D', 'name_en' => '100 Diamonds', 'name_km' => '100 ពេជ្រ (Diamonds)', 'ent_en' => '100 Free Fire Diamonds', 'ent_km' => '100 ពេជ្រ Free Fire', 'bonus' => null, 'cost' => 85, 'price' => 105, 'featured' => false],
                    ['sku' => 'FF-310D', 'name_en' => '310 Diamonds', 'name_km' => '310 ពេជ្រ (Diamonds)', 'ent_en' => '310 Free Fire Diamonds', 'ent_km' => '310 ពេជ្រ Free Fire', 'bonus' => 'Popular', 'cost' => 255, 'price' => 310, 'featured' => true],
                    ['sku' => 'FF-520D', 'name_en' => '520 Diamonds', 'name_km' => '520 ពេជ្រ (Diamonds)', 'ent_en' => '520 Free Fire Diamonds', 'ent_km' => '520 ពេជ្រ Free Fire', 'bonus' => null, 'cost' => 420, 'price' => 510, 'featured' => false],
                    ['sku' => 'FF-1060D', 'name_en' => '1,060 Diamonds', 'name_km' => '1,060 ពេជ្រ (Diamonds)', 'ent_en' => '1,060 Free Fire Diamonds', 'ent_km' => '1,060 ពេជ្រ Free Fire', 'bonus' => 'Best Value', 'cost' => 840, 'price' => 995, 'featured' => true],
                ],
            ],
            [
                'slug' => 'pubg-mobile',
                'name' => 'PUBG Mobile',
                'publisher' => 'Tencent Games / KRAFTON',
                'category_id' => $brCat->id,
                'is_featured' => true,
                'sort_order' => 3,
                'short_en' => 'Instant PUBG Mobile UC top-up for Global & SEA accounts.',
                'short_km' => 'បញ្ចូល UC សម្រាប់ PUBG Mobile ភ្លាមៗដោយស្វ័យប្រវត្តិ។',
                'desc_en' => 'Purchase PUBG Mobile Unknown Cash (UC) safely using your Character ID. Supports USD and KHR payments.',
                'desc_km' => 'ទិញ PUBG Mobile UC ដោយសុវត្ថិភាពតាមរយៈ Character ID។ គាំទ្រការទូទាត់ជា USD និង KHR។',
                'thumb' => '/images/games/pubgm-thumb.svg',
                'banner' => '/images/games/pubgm-banner.svg',
                'regions' => [
                    ['code' => 'GLOBAL', 'name_en' => 'Global', 'name_km' => 'ម៉ាស៊ីនមេសកល (Global)'],
                ],
                'fields' => [
                    ['key' => 'character_id', 'label_en' => 'Character ID', 'label_km' => 'លេខសម្គាល់តួអង្គ (Character ID)', 'placeholder_en' => 'e.g. 5123456789', 'placeholder_km' => 'ឧ. 5123456789', 'field_type' => 'number', 'validation_regex' => '/^\d{8,12}$/'],
                ],
                'packages' => [
                    ['sku' => 'PUBGM-60UC', 'name_en' => '60 UC', 'name_km' => '60 UC', 'ent_en' => '60 Unknown Cash', 'ent_km' => '60 Unknown Cash', 'bonus' => null, 'cost' => 82, 'price' => 99, 'featured' => false],
                    ['sku' => 'PUBGM-325UC', 'name_en' => '325 UC', 'name_km' => '325 UC', 'ent_en' => '300 + 25 Bonus UC', 'ent_km' => '300 + 25 UC បន្ថែម', 'bonus' => '+25 UC', 'cost' => 415, 'price' => 495, 'featured' => true],
                    ['sku' => 'PUBGM-660UC', 'name_en' => '660 UC', 'name_km' => '660 UC', 'ent_en' => '600 + 60 Bonus UC', 'ent_km' => '600 + 60 UC បន្ថែម', 'bonus' => '+60 UC', 'cost' => 830, 'price' => 980, 'featured' => true],
                    ['sku' => 'PUBGM-1800UC', 'name_en' => '1,800 UC', 'name_km' => '1,800 UC', 'ent_en' => '1,500 + 300 Bonus UC', 'ent_km' => '1,500 + 300 UC បន្ថែម', 'bonus' => '+300 UC', 'cost' => 2050, 'price' => 2450, 'featured' => false],
                ],
            ],
            [
                'slug' => 'honor-of-kings',
                'name' => 'Honor of Kings',
                'publisher' => 'Level Infinite / TiMi Studio',
                'category_id' => $mobaCat->id,
                'is_featured' => true,
                'sort_order' => 4,
                'short_en' => 'Direct Honor of Kings Tokens top-up with instant delivery.',
                'short_km' => 'បញ្ចូល Tokens សម្រាប់ Honor of Kings ភ្លាមៗដោយស្វ័យប្រវត្តិ។',
                'desc_en' => 'Top up Honor of Kings Tokens instantly with your Player UID. Fast, authorized, and reliable in Cambodia.',
                'desc_km' => 'បញ្ចូល Honor of Kings Tokens ភ្លាមៗតាមរយៈ Player UID ជាមួយសេវាកម្មរហ័ស និងទុកចិត្ត។',
                'thumb' => '/images/games/hok-thumb.svg',
                'banner' => '/images/games/hok-banner.svg',
                'regions' => [
                    ['code' => 'GLOBAL', 'name_en' => 'Global / SEA', 'name_km' => 'សកល / អាស៊ីអាគ្នេយ៍'],
                ],
                'fields' => [
                    ['key' => 'player_uid', 'label_en' => 'Player UID', 'label_km' => 'លេខសម្គាល់អ្នកលេង (Player UID)', 'placeholder_en' => 'e.g. 9012345678', 'placeholder_km' => 'ឧ. 9012345678', 'field_type' => 'number', 'validation_regex' => '/^\d{6,14}$/'],
                ],
                'packages' => [
                    ['sku' => 'HOK-80T', 'name_en' => '80 Tokens', 'name_km' => '80 Tokens', 'ent_en' => '80 Honor of Kings Tokens', 'ent_km' => '80 Honor of Kings Tokens', 'bonus' => null, 'cost' => 85, 'price' => 105, 'featured' => false],
                    ['sku' => 'HOK-240T', 'name_en' => '240 Tokens', 'name_km' => '240 Tokens', 'ent_en' => '240 Honor of Kings Tokens', 'ent_km' => '240 Honor of Kings Tokens', 'bonus' => 'Popular', 'cost' => 245, 'price' => 299, 'featured' => true],
                    ['sku' => 'HOK-400T', 'name_en' => '400 Tokens', 'name_km' => '400 Tokens', 'ent_en' => '400 Honor of Kings Tokens', 'ent_km' => '400 Honor of Kings Tokens', 'bonus' => null, 'cost' => 410, 'price' => 495, 'featured' => false],
                    ['sku' => 'HOK-800T', 'name_en' => '800 Tokens', 'name_km' => '800 Tokens', 'ent_en' => '800 + 30 Bonus Tokens', 'ent_km' => '800 + 30 Tokens បន្ថែម', 'bonus' => '+30 Bonus', 'cost' => 820, 'price' => 980, 'featured' => true],
                ],
            ],
        ];

        foreach ($gamesSpec as $spec) {
            $game = Game::query()->firstOrCreate(
                ['slug' => $spec['slug']],
                [
                    'category_id' => $spec['category_id'],
                    'name' => $spec['name'],
                    'publisher' => $spec['publisher'],
                    'fulfillment_type' => 'direct_topup',
                    'supports_player_validation' => true,
                    'is_active' => true,
                    'is_featured' => $spec['is_featured'],
                    'is_in_maintenance' => false,
                    'sort_order' => $spec['sort_order'],
                ],
            );

            GameLocalization::query()->updateOrCreate(
                ['game_id' => $game->id, 'locale' => 'en'],
                ['short_description' => $spec['short_en'], 'description' => $spec['desc_en']],
            );
            GameLocalization::query()->updateOrCreate(
                ['game_id' => $game->id, 'locale' => 'km'],
                ['short_description' => $spec['short_km'], 'description' => $spec['desc_km']],
            );

            GameAsset::query()->updateOrCreate(
                ['game_id' => $game->id, 'asset_type' => 'thumbnail'],
                ['url' => $spec['thumb'], 'alt_text' => $spec['name']],
            );
            GameAsset::query()->updateOrCreate(
                ['game_id' => $game->id, 'asset_type' => 'banner'],
                ['url' => $spec['banner'], 'alt_text' => $spec['name'].' Banner'],
            );

            $regionModels = [];
            foreach ($spec['regions'] as $idx => $reg) {
                $regionModels[] = GameRegion::query()->firstOrCreate(
                    ['game_id' => $game->id, 'code' => $reg['code']],
                    ['name_en' => $reg['name_en'], 'name_km' => $reg['name_km'], 'is_active' => true, 'sort_order' => $idx + 1],
                );
            }

            foreach ($spec['fields'] as $idx => $field) {
                GameField::query()->updateOrCreate(
                    ['game_id' => $game->id, 'key' => $field['key']],
                    [
                        'label_en' => $field['label_en'],
                        'label_km' => $field['label_km'],
                        'placeholder_en' => $field['placeholder_en'],
                        'placeholder_km' => $field['placeholder_km'],
                        'field_type' => $field['field_type'],
                        'is_required' => true,
                        'validation_regex' => $field['validation_regex'],
                        'sort_order' => $idx + 1,
                    ],
                );
            }

            foreach ($spec['packages'] as $idx => $pkg) {
                $package = GamePackage::query()->firstOrCreate(
                    ['internal_sku' => $pkg['sku']],
                    [
                        'game_id' => $game->id,
                        'name_en' => $pkg['name_en'],
                        'name_km' => $pkg['name_km'],
                        'entitlement_description_en' => $pkg['ent_en'],
                        'entitlement_description_km' => $pkg['ent_km'],
                        'bonus_label' => $pkg['bonus'],
                        'currency' => 'USD',
                        'base_cost_minor' => $pkg['cost'],
                        'selling_price_minor' => $pkg['price'],
                        'min_profit_minor' => 15,
                        'markup_percent' => 18.00,
                        'fulfillment_type' => 'direct_topup',
                        'is_promotion_eligible' => true,
                        'is_available' => true,
                        'is_featured' => $pkg['featured'],
                        'version' => 1,
                        'sort_order' => $idx + 1,
                    ],
                );

                $package->regions()->syncWithoutDetaching(collect($regionModels)->pluck('id'));

                foreach ($regionModels as $regModel) {
                    SupplierProduct::query()->firstOrCreate(
                        [
                            'supplier_id' => $mockSupplier->id,
                            'game_package_id' => $package->id,
                            'region_code' => $regModel->code,
                        ],
                        [
                            'external_sku' => 'MOCK-'.$pkg['sku'],
                            'currency' => 'USD',
                            'cost_minor' => $pkg['cost'],
                            'is_authorized' => true,
                            'is_available' => true,
                        ],
                    );
                }
            }
        }
    }

    private function seedPromotions(): void
    {
        Coupon::query()->firstOrCreate(
            ['code' => 'KHMER10'],
            [
                'name_en' => 'Welcome 10% Off',
                'name_km' => 'បញ្ចុះតម្លៃស្វាគមន៍ 10%',
                'discount_type' => 'percent',
                'discount_value' => 1000, // 10.00%
                'currency' => 'USD',
                'min_order_minor' => 100, // $1.00
                'max_discount_minor' => 200, // max $2.00 off
                'max_total_uses' => 1000,
                'max_uses_per_user' => 3,
                'is_active' => true,
            ],
        );
    }
}
