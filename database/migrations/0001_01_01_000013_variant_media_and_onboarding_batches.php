<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogue_import_images', function (Blueprint $table) {
            if (! Schema::hasColumn('catalogue_import_images', 'source_variant_sku')) {
                $table->string('source_variant_sku')->nullable()->after('source_name');
            }
            if (! Schema::hasColumn('catalogue_import_images', 'source_variant_name')) {
                $table->string('source_variant_name')->nullable()->after('source_variant_sku');
            }
            if (! Schema::hasColumn('catalogue_import_images', 'normalized_variant_candidate')) {
                $table->json('normalized_variant_candidate')->nullable()->after('source_variant_name');
            }
            if (! Schema::hasColumn('catalogue_import_images', 'product_variant_id')) {
                $table->foreignUuid('product_variant_id')->nullable()->after('normalized_variant_candidate')
                    ->constrained('product_variants')->nullOnDelete();
            }
        });

        Schema::table('catalogue_import_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('catalogue_import_batches', 'status')) {
                $table->string('status')->default('DRAFT')->after('batch_number')->index();
            }
            if (! Schema::hasColumn('catalogue_import_batches', 'approved_count')) {
                $table->unsignedInteger('approved_count')->default(0)->after('needs_review');
            }
            if (! Schema::hasColumn('catalogue_import_batches', 'rejected_count')) {
                $table->unsignedInteger('rejected_count')->default(0)->after('approved_count');
            }
            if (! Schema::hasColumn('catalogue_import_batches', 'review_count')) {
                $table->unsignedInteger('review_count')->default(0)->after('rejected_count');
            }
            if (! Schema::hasColumn('catalogue_import_batches', 'max_records')) {
                $table->unsignedInteger('max_records')->nullable()->after('review_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('catalogue_import_images', function (Blueprint $table) {
            if (Schema::hasColumn('catalogue_import_images', 'product_variant_id')) {
                $table->dropConstrainedForeignId('product_variant_id');
            }
            foreach (['normalized_variant_candidate', 'source_variant_name', 'source_variant_sku'] as $column) {
                if (Schema::hasColumn('catalogue_import_images', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('catalogue_import_batches', function (Blueprint $table) {
            foreach (['status', 'approved_count', 'rejected_count', 'review_count', 'max_records'] as $column) {
                if (Schema::hasColumn('catalogue_import_batches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
