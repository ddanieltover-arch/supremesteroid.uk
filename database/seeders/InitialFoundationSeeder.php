<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ShippingZoneType;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ShippingMethod;
use App\Models\ShippingRule;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialFoundationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Core Administrative & Customer Accounts
        $adminEmail = (string) env('ADMIN_DEFAULT_EMAIL', 'info@supremesteroid.uk');

        // Migrate the legacy admin mailbox to the canonical store address.
        if ($adminEmail !== 'admin@supremesteroid.uk') {
            $legacyAdmin = User::query()->where('email', 'admin@supremesteroid.uk')->first();
            $canonicalAdmin = User::query()->where('email', $adminEmail)->first();

            if ($legacyAdmin && ! $canonicalAdmin) {
                $legacyAdmin->forceFill(['email' => $adminEmail])->save();
            } elseif ($legacyAdmin && $canonicalAdmin && $legacyAdmin->isNot($canonicalAdmin)) {
                $legacyAdmin->delete();
            }
        }

        foreach ([
            [
                'email' => $adminEmail,
                'name' => 'Supreme Super Admin',
                'password' => Hash::make('AdminPass123!'),
                'role' => UserRole::SUPER_ADMIN,
                'phone' => '+442080000001',
            ],
            [
                'email' => 'staff@supremesteroid.uk',
                'name' => 'Fulfillment Staff',
                'password' => Hash::make('StaffPass123!'),
                'role' => UserRole::STAFF,
                'phone' => '+442080000002',
            ],
            [
                'email' => 'customer@supremesteroid.uk',
                'name' => 'Verified Retail Customer',
                'password' => Hash::make('CustomerPass123!'),
                'role' => UserRole::CUSTOMER,
                'phone' => '+447911123456',
            ],
        ] as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);
            $user->forceFill([
                ...$account,
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();
        }

        // 2. Shipping Zones, Methods & Threshold Rules
        $ukZone = ShippingZone::updateOrCreate(
            ['code' => 'UK_DOMESTIC'],
            [
                'name' => 'United Kingdom (Domestic)',
                'type' => ShippingZoneType::UK_DOMESTIC,
                'countries' => ['GB'],
                'is_active' => true,
            ]
        );

        $ukStandard = ShippingMethod::updateOrCreate(
            ['code' => 'UK_STANDARD', 'shipping_zone_id' => $ukZone->id],
            [
                'name' => 'UK Standard Delivery',
                'rate_amount' => 10.00,
                'currency' => 'GBP',
                'estimated_days' => '2-4 business days',
                'is_discreet' => false,
                'is_active' => true,
                'display_order' => 1,
            ]
        );

        // Free UK Standard on orders >= £300
        ShippingRule::updateOrCreate(
            ['shipping_method_id' => $ukStandard->id, 'rule_type' => 'FREE_SUBTOTAL_THRESHOLD'],
            [
                'min_subtotal' => 300.00,
                'is_free_shipping' => true,
                'override_rate_amount' => 0.00,
                'is_active' => true,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['code' => 'UK_EXPRESS', 'shipping_zone_id' => $ukZone->id],
            [
                'name' => 'UK Express Delivery',
                'rate_amount' => 15.00,
                'currency' => 'GBP',
                'estimated_days' => '1-2 business days',
                'is_discreet' => false,
                'is_active' => true,
                'display_order' => 2,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['code' => 'UK_DISCREET', 'shipping_zone_id' => $ukZone->id],
            [
                'name' => 'UK Discreet Delivery',
                'rate_amount' => 25.00,
                'currency' => 'GBP',
                'estimated_days' => '1-2 business days',
                'is_discreet' => true,
                'is_active' => true,
                'display_order' => 3,
            ]
        );

        $euZone = ShippingZone::updateOrCreate(
            ['code' => 'EUROPE'],
            [
                'name' => 'European Union & EEA',
                'type' => ShippingZoneType::EUROPE,
                'countries' => ['AT', 'BE', 'DE', 'FR', 'ES', 'IT', 'NL', 'IE', 'PL', 'SE', 'DK', 'NO', 'CH'],
                'is_active' => true,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['code' => 'EUROPE_STANDARD', 'shipping_zone_id' => $euZone->id],
            [
                'name' => 'Europe Tracked Shipping',
                'rate_amount' => 35.00,
                'currency' => 'GBP',
                'estimated_days' => '5-9 business days',
                'is_discreet' => true,
                'is_active' => true,
                'display_order' => 4,
            ]
        );

        $intlZone = ShippingZone::updateOrCreate(
            ['code' => 'INTERNATIONAL'],
            [
                'name' => 'International Priority Zone',
                'type' => ShippingZoneType::INTERNATIONAL,
                'countries' => ['*'],
                'is_active' => true,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['code' => 'INTL_PRIORITY', 'shipping_zone_id' => $intlZone->id],
            [
                'name' => 'International Priority Shipping',
                'rate_amount' => 50.00,
                'currency' => 'GBP',
                'estimated_days' => '7-14 business days',
                'is_discreet' => true,
                'is_active' => true,
                'display_order' => 5,
            ]
        );

        // 3. Brands & Categories (taxonomy only; no seed products)
        Brand::updateOrCreate(
            ['slug' => 'supreme-formulations'],
            [
                'name' => 'Supreme Formulations',
                'description' => 'Pharmaceutical grade wellness, strength, and longevity compounds.',
                'country_of_origin' => 'United Kingdom',
                'is_verified' => true,
                'is_active' => true,
            ]
        );

        Brand::updateOrCreate(
            ['slug' => 'apex-laboratory'],
            [
                'name' => 'Apex Analytical Laboratories',
                'description' => 'Precision synthesized bioactive analytical reagents.',
                'country_of_origin' => 'Switzerland',
                'is_verified' => true,
                'is_active' => true,
            ]
        );

        Category::updateOrCreate(
            ['slug' => 'vitality-hormonal-support'],
            [
                'name' => 'Vitality & Hormonal Support',
                'description' => 'Lawful sports nutrition optimizers, adaptogens, and wellness minerals.',
                'display_order' => 1,
                'is_visible' => true,
            ]
        );

        Category::updateOrCreate(
            ['slug' => 'certified-research-compounds'],
            [
                'name' => 'Certified Research Reagents',
                'description' => 'High-purity reagents strictly for certified laboratory and analytical in-vitro research.',
                'display_order' => 2,
                'is_visible' => true,
            ]
        );

        Category::updateOrCreate(
            ['slug' => 'recovery-joint-protection'],
            [
                'name' => 'Joint Protection & Recovery',
                'description' => 'Cellular repair catalysts, collagen synthesis co-factors, and recovery blends.',
                'display_order' => 3,
                'is_visible' => true,
            ]
        );
    }
}
