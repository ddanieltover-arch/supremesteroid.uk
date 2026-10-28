<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('catalogue_bulk_ingest_runs')) {
            return;
        }

        Schema::create('catalogue_bulk_ingest_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('run_key')->unique();
            $table->string('source_type');
            $table->string('source_name');
            $table->string('status')->default('PENDING')->index();
            $table->string('batch_number')->nullable()->index();
            $table->foreignUuid('import_batch_uuid')->nullable()->constrained('catalogue_import_batches')->nullOnDelete();
            $table->unsignedInteger('page_size')->default(25);
            $table->unsignedInteger('batch_limit')->nullable();
            $table->json('cursor')->nullable();
            $table->json('stats')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_dry_run')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_bulk_ingest_runs');
    }
};
