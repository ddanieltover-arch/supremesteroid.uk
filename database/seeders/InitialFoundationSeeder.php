<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Enums\ShippingZoneType;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ShippingMethod;
use App\Models\ShippingRule;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InitialFoundationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Core Administrative & Customer Accounts
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@supremesteroid.uk'],
            [
                'name' => 'Supreme Super Admin',
                'password' => Hash::make('AdminPass123!'),
                'role' => UserRole::SUPER_ADMIN,
                'phone' => '+442080000001',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@supremesteroid.uk'],
            [
                'name' => 'Fulfillment Staff',
                'password' => Hash::make('StaffPass123!'),
                'role' => UserRole::STAFF,
                'phone' => '+442080000002',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $demoCustomer = User::updateOrCreate(
            ['email' => 'customer@supremesteroid.uk'],
            [
                'name' => 'Verified Retail Customer',
                'password' => Hash::make('CustomerPass123!'),
                'role' => UserRole::CUSTOMER,
                'phone' => '+447911123456',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

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

        // 3. Brands & Categories
        $brandSupreme = Brand::updateOrCreate(
            ['slug' => 'supreme-formulations'],
            [
                'name' => 'Supreme Formulations',
                'description' => 'Pharmaceutical grade wellness, strength, and longevity compounds.',
                'country_of_origin' => 'United Kingdom',
                'is_verified' => true,
                'is_active' => true,
            ]
        );

        $brandApex = Brand::updateOrCreate(
            ['slug' => 'apex-laboratory'],
            [
                'name' => 'Apex Analytical Laboratories',
                'description' => 'Precision synthesized bioactive analytical reagents.',
                'country_of_origin' => 'Switzerland',
                'is_verified' => true,
                'is_active' => true,
            ]
        );

        $catWellness = Category::updateOrCreate(
            ['slug' => 'vitality-hormonal-support'],
            [
                'name' => 'Vitality & Hormonal Support',
                'description' => 'Lawful sports nutrition optimizers, adaptogens, and wellness minerals.',
                'display_order' => 1,
                'is_visible' => true,
            ]
        );

        $catResearch = Category::updateOrCreate(
            ['slug' => 'certified-research-compounds'],
            [
                'name' => 'Certified Research Reagents',
                'description' => 'High-purity reagents strictly for certified laboratory and analytical in-vitro research.',
                'display_order' => 2,
                'is_visible' => true,
            ]
        );

        $catRecovery = Category::updateOrCreate(
            ['slug' => 'recovery-joint-protection'],
            [
                'name' => 'Joint Protection & Recovery',
                'description' => 'Cellular repair catalysts, collagen synthesis co-factors, and recovery blends.',
                'display_order' => 3,
                'is_visible' => true,
            ]
        );

        // 4. Initial Compliance-Approved Products
        $products = [
            [
                'sku' => 'SUP-TEST-BOOST-01',
                'name' => 'Supreme TestoMax Matrix (OTC)',
                'slug' => 'supreme-testomax-matrix',
                'status' => ProductStatus::ACTIVE,
                'classification' => ProductClassification::OTC_CONSUMER,
                'price_amount' => 54.99,
                'compare_at_price_amount' => 69.99,
                'currency' => 'GBP',
                'short_description' => 'Premium natural testosterone enhancement formulation with Tongkat Ali, Fadogia Agrestis, and Zinc Bisglycinate.',
                'description' => 'Engineered for athletes seeking optimal hormonal balance, stamina, and recovery support. Compliant natural OTC nutritional supplement.',
                'ingredients' => 'Tongkat Ali Extract (200:1), Fadogia Agrestis, Zinc Bisglycinate, Boron Citrate, BioPerine.',
                'safety_guidelines' => 'Store in a cool dry place. Keep out of reach of children. Consult a healthcare practitioner if taking prescription medication.',
                'batch_number' => 'TM-2026-088',
                'lab_verification_reference' => 'LAB-APEX-8841-PASS',
                'compliance_verified_at' => now(),
                'compliance_officer_id' => $superAdmin->id,
                'brand_id' => $brandSupreme->id,
                'category_id' => $catWellness->id,
                'is_featured' => true,
                'image_url' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'sku' => 'APX-BPC157-RES-05',
                'name' => 'Apex BPC-157 Lyophilized Peptide (5mg)',
                'slug' => 'apex-bpc-157-5mg',
                'status' => ProductStatus::ACTIVE,
                'classification' => ProductClassification::RESEARCH_USE,
                'price_amount' => 62.00,
                'compare_at_price_amount' => 75.00,
                'currency' => 'GBP',
                'short_description' => 'Certified >= 99.4% purity analytical grade reagent for laboratory investigation.',
                'description' => 'HPLC and Mass-Spectrometry verified synthetic pentadecapeptide sequence. Provided in vacuum-sealed amber glass vial.',
                'ingredients' => 'Pure BPC-157 Acetate Salt (5mg lyophilized powder).',
                'safety_guidelines' => 'Strictly for qualified laboratory, scientific research and educational reagent use. Not for human or veterinary ingestion.',
                'batch_number' => 'BPC-2026-A12',
                'lab_verification_reference' => 'LAB-SWISS-HPLC-9942',
                'compliance_verified_at' => now(),
                'compliance_officer_id' => $superAdmin->id,
                'brand_id' => $brandApex->id,
                'category_id' => $catResearch->id,
                'is_featured' => true,
                'image_url' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'sku' => 'SUP-JOINT-REP-02',
                'name' => 'Supreme Arthrotech Joint & Ligament Complex',
                'slug' => 'supreme-arthrotech-complex',
                'status' => ProductStatus::ACTIVE,
                'classification' => ProductClassification::OTC_CONSUMER,
                'price_amount' => 42.50,
                'compare_at_price_amount' => 52.00,
                'currency' => 'GBP',
                'short_description' => 'Heavy training joint lubrication, connective tissue reinforcement, and cartilage support.',
                'description' => 'High-potency synergy of UC-II Undenatured Type II Collagen, Glucosamine Sulfate, MSM, and Curcumin C3 Complex.',
                'ingredients' => 'Glucosamine Sulfate 2KCl, MSM (Methylsulfonylmethane), Chondroitin Sulfate, UC-II Collagen, Hyaluronic Acid.',
                'safety_guidelines' => 'Take with meals. Contains shellfish derivative.',
                'batch_number' => 'AR-2026-301',
                'lab_verification_reference' => 'LAB-UK-CERT-3310',
                'compliance_verified_at' => now(),
                'compliance_officer_id' => $superAdmin->id,
                'brand_id' => $brandSupreme->id,
                'category_id' => $catRecovery->id,
                'is_featured' => false,
                'image_url' => 'https://images.unsplash.com/photo-1550572017-edd951aa8f72?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'sku' => 'APX-TB500-RES-05',
                'name' => 'Apex TB-500 Thymosin Beta-4 Reagent (10mg)',
                'slug' => 'apex-tb-500-10mg',
                'status' => ProductStatus::ACTIVE,
                'classification' => ProductClassification::RESEARCH_USE,
                'price_amount' => 84.00,
                'compare_at_price_amount' => 98.00,
                'currency' => 'GBP',
                'short_description' => 'Laboratory research peptide with verified HPLC purity of >= 99.2%.',
                'description' => 'Precision synthetic 43-amino acid peptide fragment for biochemical assay and structural analysis.',
                'ingredients' => 'Thymosin Beta-4 acetate 10mg lyophilized cake.',
                'safety_guidelines' => 'For analytical and laboratory in-vitro evaluation only. Refrigerate at 2-8C.',
                'batch_number' => 'TB-2026-X88',
                'lab_verification_reference' => 'LAB-SWISS-HPLC-9980',
                'compliance_verified_at' => now(),
                'compliance_officer_id' => $superAdmin->id,
                'brand_id' => $brandApex->id,
                'category_id' => $catResearch->id,
                'is_featured' => true,
                'image_url' => 'https://images.unsplash.com/photo-1579165466741-7f35e4755660?w=800&auto=format&fit=crop&q=80',
            ],
        ];

        foreach ($products as $prodData) {
            $imageUrl = $prodData['image_url'];
            unset($prodData['image_url']);

            $product = Product::updateOrCreate(
                ['sku' => $prodData['sku']],
                $prodData
            );

            ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'url' => $imageUrl],
                ['alt_text' => $product->name, 'display_order' => 1, 'is_primary' => true]
            );

            Inventory::updateOrCreate(
                ['product_id' => $product->id],
                ['quantity_on_hand' => 150, 'quantity_reserved' => 0, 'safety_stock_threshold' => 10]
            );
        }
    }
}
