<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->nullable()->references('id')->on('products')->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->references('id')->on('product_variants')->cascadeOnDelete();
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('safety_stock_threshold')->default(5);
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_id')->references('id')->on('inventory')->cascadeOnDelete();
            $table->string('movement_type'); // restock, order_reservation, order_fulfillment, adjustment, damage
            $table->integer('quantity_change');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->foreignUuid('actor_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
    }
};
