<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'published_at')) {
                $table->timestamp('published_at')->nullable()->index();
            }
            if (! Schema::hasColumn('products', 'sort_order')) {
                $table->integer('sort_order')->default(0)->index();
            }
            if (! Schema::hasColumn('products', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->index();
            }
        });

        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'logo_url')) {
                $table->string('logo_url')->nullable();
            }
            if (! Schema::hasColumn('brands', 'website_url')) {
                $table->string('website_url')->nullable();
            }
            if (! Schema::hasColumn('brands', 'display_order')) {
                $table->integer('display_order')->default(0);
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'image_url')) {
                $table->string('image_url')->nullable();
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'weight_grams')) {
                $table->unsignedInteger('weight_grams')->nullable();
            }
        });

        Schema::table('product_images', function (Blueprint $table) {
            if (! Schema::hasColumn('product_images', 'caption')) {
                $table->string('caption')->nullable();
            }
            if (! Schema::hasColumn('product_images', 'thumbnail_url')) {
                $table->string('thumbnail_url')->nullable();
            }
            if (! Schema::hasColumn('product_images', 'product_variant_id')) {
                $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('product_category')) {
            Schema::create('product_category', function (Blueprint $table) {
                $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
                $table->primary(['product_id', 'category_id']);
            });
        }

        if (! Schema::hasTable('variant_attributes')) {
            Schema::create('variant_attributes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->string('attribute_name');
                $table->string('attribute_value');
                $table->timestamps();
                $table->unique(['product_variant_id', 'attribute_name']);
                $table->index(['attribute_name', 'attribute_value']);
            });
        }

        if (! Schema::hasTable('product_relations')) {
            Schema::create('product_relations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignUuid('related_product_id')->constrained('products')->cascadeOnDelete();
                $table->string('relation_type')->default('RELATED');
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();
                $table->unique(['product_id', 'related_product_id', 'relation_type']);
            });
        }

        if (! Schema::hasTable('product_contents')) {
            Schema::create('product_contents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
                $table->text('overview')->nullable();
                $table->json('highlights')->nullable();
                $table->json('specifications')->nullable();
                $table->text('composition')->nullable();
                $table->text('manufacturer_information')->nullable();
                $table->text('packaging_information')->nullable();
                $table->text('documentation')->nullable();
                $table->text('storage_information')->nullable();
                $table->text('shipping_information')->nullable();
                $table->json('faqs')->nullable();
                $table->text('important_information')->nullable();
                $table->timestamps();
                $table->unique('product_id');
            });
        }

        if (! Schema::hasTable('product_revisions')) {
            Schema::create('product_revisions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedInteger('revision_number');
                $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('changed_fields');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'revision_number']);
            });
        }

        if (! Schema::hasTable('catalogue_import_records')) {
            Schema::create('catalogue_import_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('import_batch_id')->index();
                $table->string('source_name');
                $table->string('source_url')->nullable();
                $table->string('source_product_reference')->nullable()->index();
                $table->string('source_category')->nullable();
                $table->string('source_brand')->nullable();
                $table->string('original_payload_reference')->nullable();
                $table->json('normalized_payload')->nullable();
                $table->string('match_status')->default('NEW');
                $table->string('workflow_status')->default('STAGED');
                $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignUuid('matched_product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->timestamp('imported_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stock_notifications')) {
            Schema::create('stock_notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('email');
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();
                $table->unique(['email', 'product_id', 'product_variant_id'], 'stock_notifications_unique_subscription');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_notifications');
        Schema::dropIfExists('catalogue_import_records');
        Schema::dropIfExists('product_revisions');
        Schema::dropIfExists('product_contents');
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('variant_attributes');
        Schema::dropIfExists('product_category');

        Schema::table('product_images', function (Blueprint $table) {
            if (Schema::hasColumn('product_images', 'product_variant_id')) {
                $table->dropConstrainedForeignId('product_variant_id');
            }
            foreach (['caption', 'thumbnail_url'] as $column) {
                if (Schema::hasColumn('product_images', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'weight_grams')) {
                $table->dropColumn('weight_grams');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'image_url')) {
                $table->dropColumn('image_url');
            }
        });

        Schema::table('brands', function (Blueprint $table) {
            foreach (['logo_url', 'website_url', 'display_order'] as $column) {
                if (Schema::hasColumn('brands', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            foreach (['published_at', 'sort_order', 'is_demo'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
