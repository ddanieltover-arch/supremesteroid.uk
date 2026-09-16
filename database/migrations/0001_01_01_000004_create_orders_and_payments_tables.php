<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('type')->default('shipping'); // shipping, billing
            $table->string('full_name');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state_county')->nullable();
            $table->string('postal_code', 20);
            $table->string('country_code', 2)->default('GB');
            $table->string('phone')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique()->index();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('status')->default('PENDING_PAYMENT')->index();
            $table->decimal('subtotal_amount', 10, 2);
            $table->decimal('shipping_amount', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('GBP');
            $table->foreignUuid('shipping_method_id')->nullable()->references('id')->on('shipping_methods')->nullOnDelete();
            $table->foreignUuid('shipping_address_id')->nullable()->references('id')->on('addresses')->nullOnDelete();
            $table->foreignUuid('billing_address_id')->nullable()->references('id')->on('addresses')->nullOnDelete();
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->references('id')->on('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_sku');
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity');
            $table->decimal('total_price', 10, 2);
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->string('previous_status');
            $table->string('new_status');
            $table->foreignUuid('actor_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->string('method'); // BANK_TRANSFER, CRYPTOCURRENCY
            $table->string('status')->default('PENDING')->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GBP');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->string('submission_type'); // BANK_TRANSFER, CRYPTOCURRENCY
            $table->decimal('submitted_amount', 10, 2);
            $table->string('currency', 3)->default('GBP');
            // Bank specific
            $table->string('bank_payment_reference')->nullable()->index();
            $table->string('sender_account_name')->nullable();
            $table->date('transfer_date')->nullable();
            $table->string('proof_document_url')->nullable();
            // Crypto specific
            $table->string('crypto_network')->nullable();
            $table->string('crypto_transaction_hash')->nullable()->index();
            $table->string('crypto_recipient_wallet')->nullable();
            $table->decimal('crypto_submitted_amount', 16, 8)->nullable();
            // Review status
            $table->string('review_status')->default('PAYMENT_SUBMITTED');
            $table->foreignUuid('reviewed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_submissions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('addresses');
    }
};
