<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->string('collection', 50)->index();
            $table->nullableUuidMorphs('fileable');
            $table->foreignUuid('uploaded_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->foreignUuid('proof_file_id')->nullable()->after('proof_document_url')->references('id')->on('stored_files')->nullOnDelete();
        });

        Schema::table('wishlist_items', function (Blueprint $table) {
            $driver = Schema::getConnection()->getDriverName();
            $duplicates = \Illuminate\Support\Facades\DB::table('wishlist_items')
                ->select('wishlist_id', 'product_id')
                ->groupBy('wishlist_id', 'product_id')
                ->havingRaw('count(*) > 1')
                ->exists();

            if ($duplicates) {
                return;
            }

            if ($driver === 'sqlite') {
                $table->unique(['wishlist_id', 'product_id']);

                return;
            }

            $table->unique(['wishlist_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropUnique(['wishlist_id', 'product_id']);
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proof_file_id');
        });

        Schema::dropIfExists('stored_files');
    }
};
