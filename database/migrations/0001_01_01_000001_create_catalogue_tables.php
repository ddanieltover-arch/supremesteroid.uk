<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('country_of_origin', 100)->nullable();
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // PostgreSQL adds foreign keys before the primary key in the same
            // create statement, so the self-reference must be added afterwards.
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('DRAFT')->index(); // DRAFT, COMPLIANCE_REVIEW, ACTIVE, OUT_OF_STOCK, ARCHIVED
            $table->string('classification')->default('OTHER_LAWFUL_PRODUCT')->index(); // OTC_CONSUMER, RESEARCH_USE, OTHER_LAWFUL_PRODUCT
            $table->decimal('price_amount', 10, 2);
            $table->decimal('compare_at_price_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('GBP');
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->text('ingredients')->nullable();
            $table->text('safety_guidelines')->nullable();
            $table->string('batch_number')->nullable()->index();
            $table->string('lab_verification_reference')->nullable();
            $table->timestamp('compliance_verified_at')->nullable()->index();
            $table->foreignUuid('compliance_officer_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('brand_id')->nullable()->references('id')->on('brands')->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->references('id')->on('categories')->nullOnDelete();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->decimal('price_amount', 10, 2);
            $table->decimal('compare_at_price_amount', 10, 2)->nullable();
            $table->string('barcode')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->string('url');
            $table->string('alt_text')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('product_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->string('attribute_name');
            $table->string('attribute_value');
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
