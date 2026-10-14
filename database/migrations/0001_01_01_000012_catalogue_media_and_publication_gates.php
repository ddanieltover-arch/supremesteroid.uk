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
            if (! Schema::hasColumn('catalogue_import_images', 'source_name')) {
                $table->string('source_name')->nullable()->after('source_url');
            }
            if (! Schema::hasColumn('catalogue_import_images', 'copied_at')) {
                $table->timestamp('copied_at')->nullable()->after('product_image_id');
            }
            if (! Schema::hasColumn('catalogue_import_images', 'transfer_failure_reason')) {
                $table->text('transfer_failure_reason')->nullable()->after('copied_at');
            }
            if (! Schema::hasColumn('catalogue_import_images', 'transfer_attempts')) {
                $table->unsignedInteger('transfer_attempts')->default(0)->after('transfer_failure_reason');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'image_not_required')) {
                $table->boolean('image_not_required')->default(false)->after('is_demo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('catalogue_import_images', function (Blueprint $table) {
            foreach (['source_name', 'copied_at', 'transfer_failure_reason', 'transfer_attempts'] as $column) {
                if (Schema::hasColumn('catalogue_import_images', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'image_not_required')) {
                $table->dropColumn('image_not_required');
            }
        });
    }
};
