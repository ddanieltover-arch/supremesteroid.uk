<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogue_import_batches')) {
            Schema::create('catalogue_import_batches', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('batch_number')->unique();
                $table->string('source_name');
                $table->string('source_type')->default('json');
                $table->boolean('is_dry_run')->default(false);
                $table->unsignedInteger('source_records')->default(0);
                $table->unsignedInteger('new_count')->default(0);
                $table->unsignedInteger('possible_duplicates')->default(0);
                $table->unsignedInteger('exact_matches')->default(0);
                $table->unsignedInteger('compliance_review')->default(0);
                $table->unsignedInteger('rejected')->default(0);
                $table->unsignedInteger('needs_review')->default(0);
                $table->json('summary')->nullable();
                $table->foreignUuid('started_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('catalogue_category_mappings')) {
            Schema::create('catalogue_category_mappings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('source_category');
                $table->string('normalized_source_category')->index();
                $table->foreignUuid('target_category_id')->nullable()->constrained('categories')->nullOnDelete();
                $table->string('status')->default('PENDING');
                $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                $table->unique(['normalized_source_category']);
            });
        }

        if (! Schema::hasTable('catalogue_brand_mappings')) {
            Schema::create('catalogue_brand_mappings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('source_brand');
                $table->string('normalized_source_brand')->index();
                $table->foreignUuid('target_brand_id')->nullable()->constrained('brands')->nullOnDelete();
                $table->string('status')->default('PENDING');
                $table->string('match_hint')->nullable();
                $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                $table->unique(['normalized_source_brand']);
            });
        }

        if (! Schema::hasTable('catalogue_import_images')) {
            Schema::create('catalogue_import_images', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('catalogue_import_record_id')->constrained('catalogue_import_records')->cascadeOnDelete();
                $table->string('source_url');
                $table->string('alt_text')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_primary')->default(false);
                $table->string('status')->default('PENDING');
                $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->foreignUuid('product_image_id')->nullable()->constrained('product_images')->nullOnDelete();
                $table->timestamps();
            });
        }

        Schema::table('catalogue_import_records', function (Blueprint $table) {
            if (! Schema::hasColumn('catalogue_import_records', 'source_batch')) {
                $table->string('source_batch')->nullable()->after('import_batch_id');
            }
            if (! Schema::hasColumn('catalogue_import_records', 'source_payload')) {
                $table->json('source_payload')->nullable()->after('original_payload_reference');
            }
            if (! Schema::hasColumn('catalogue_import_records', 'issues')) {
                $table->json('issues')->nullable();
            }
            if (! Schema::hasColumn('catalogue_import_records', 'review_checklist')) {
                $table->json('review_checklist')->nullable();
            }
            if (! Schema::hasColumn('catalogue_import_records', 'classification_status')) {
                $table->string('classification_status')->nullable();
            }
            if (! Schema::hasColumn('catalogue_import_records', 'classification_notes')) {
                $table->text('classification_notes')->nullable();
            }
            if (! Schema::hasColumn('catalogue_import_records', 'classification_reviewed_by')) {
                $table->foreignUuid('classification_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('catalogue_import_records', 'classification_reviewed_at')) {
                $table->timestamp('classification_reviewed_at')->nullable();
            }
            if (! Schema::hasColumn('catalogue_import_records', 'source_price_amount')) {
                $table->decimal('source_price_amount', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('catalogue_import_records', 'source_compare_at_price_amount')) {
                $table->decimal('source_compare_at_price_amount', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('catalogue_import_records', 'source_currency')) {
                $table->string('source_currency', 3)->nullable();
            }
            if (! Schema::hasColumn('catalogue_import_records', 'requires_compliance_review')) {
                $table->boolean('requires_compliance_review')->default(false);
            }
            if (! Schema::hasColumn('catalogue_import_records', 'target_category_id')) {
                $table->foreignUuid('target_category_id')->nullable()->constrained('categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('catalogue_import_records', 'target_brand_id')) {
                $table->foreignUuid('target_brand_id')->nullable()->constrained('brands')->nullOnDelete();
            }
            if (! Schema::hasColumn('catalogue_import_records', 'import_batch_uuid')) {
                $table->foreignUuid('import_batch_uuid')->nullable()->constrained('catalogue_import_batches')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('catalogue_import_records', function (Blueprint $table) {
            foreach ([
                'import_batch_uuid',
                'target_brand_id',
                'target_category_id',
                'requires_compliance_review',
                'source_currency',
                'source_compare_at_price_amount',
                'source_price_amount',
                'classification_reviewed_at',
                'classification_reviewed_by',
                'classification_notes',
                'classification_status',
                'review_checklist',
                'issues',
                'source_payload',
                'source_batch',
            ] as $column) {
                if (Schema::hasColumn('catalogue_import_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('catalogue_import_images');
        Schema::dropIfExists('catalogue_brand_mappings');
        Schema::dropIfExists('catalogue_category_mappings');
        Schema::dropIfExists('catalogue_import_batches');
    }
};
