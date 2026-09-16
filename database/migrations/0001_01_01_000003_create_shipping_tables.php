<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type'); // UK_DOMESTIC, EUROPE, INTERNATIONAL
            $table->json('countries')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipping_zone_id')->references('id')->on('shipping_zones')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('rate_amount', 10, 2);
            $table->string('currency', 3)->default('GBP');
            $table->string('estimated_days')->nullable();
            $table->boolean('is_discreet')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipping_method_id')->references('id')->on('shipping_methods')->cascadeOnDelete();
            $table->string('rule_type')->default('SUBTOTAL_THRESHOLD');
            $table->decimal('min_subtotal', 10, 2)->default(0.00);
            $table->decimal('max_subtotal', 10, 2)->nullable();
            $table->decimal('override_rate_amount', 10, 2)->nullable();
            $table->boolean('is_free_shipping')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rules');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zones');
    }
};
