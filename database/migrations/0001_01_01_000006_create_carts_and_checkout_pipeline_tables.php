<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Carts table
        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->references('id')->on('users')->cascadeOnDelete();
            $table->string('session_id', 100)->nullable()->index();
            $table->string('currency', 3)->default('GBP');
            $table->timestamps();
        });

        // 2. Cart Items table
        Schema::create('cart_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cart_id')->references('id')->on('carts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->references('id')->on('product_variants')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['cart_id', 'product_id', 'product_variant_id'], 'cart_product_variant_unique');
        });

        // 3. Inventory Reservations table
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_id')->references('id')->on('inventory')->cascadeOnDelete();
            $table->foreignUuid('order_id')->nullable()->references('id')->on('orders')->cascadeOnDelete();
            $table->string('session_id', 100)->nullable()->index();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('ACTIVE'); // ACTIVE, RELEASED, FULFILLED
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        // 4. Enhance Orders table with idempotency and historical snapshot fields
        Schema::table('orders', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->nullable()->unique()->after('order_number');
            $table->string('customer_name_snapshot')->nullable()->after('user_id');
            $table->string('customer_email_snapshot')->nullable()->after('customer_name_snapshot');
            $table->json('shipping_address_snapshot')->nullable()->after('billing_address_id');
            $table->json('billing_address_snapshot')->nullable()->after('shipping_address_snapshot');
            $table->string('shipping_method_name_snapshot')->nullable()->after('shipping_method_id');
            $table->decimal('tax_amount', 10, 2)->default(0.00)->after('discount_amount');
        });

        // 5. Enhance Order Items table with item price snapshots
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('variant_name_snapshot')->nullable()->after('product_sku');
            $table->decimal('line_subtotal', 10, 2)->default(0.00)->after('unit_price');
            $table->decimal('discount_amount', 10, 2)->default(0.00)->after('quantity');
            $table->decimal('tax_amount', 10, 2)->default(0.00)->after('discount_amount');
            $table->decimal('line_total', 10, 2)->default(0.00)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'variant_name_snapshot',
                'line_subtotal',
                'discount_amount',
                'tax_amount',
                'line_total',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'idempotency_key',
                'customer_name_snapshot',
                'customer_email_snapshot',
                'shipping_address_snapshot',
                'billing_address_snapshot',
                'shipping_method_name_snapshot',
                'tax_amount',
            ]);
        });

        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
