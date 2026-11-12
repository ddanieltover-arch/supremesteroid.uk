<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_contents', function (Blueprint $table) {
            if (! Schema::hasColumn('product_contents', 'why_choose')) {
                $table->text('why_choose')->nullable()->after('important_information');
            }
            if (! Schema::hasColumn('product_contents', 'how_supplied')) {
                $table->text('how_supplied')->nullable()->after('why_choose');
            }
            if (! Schema::hasColumn('product_contents', 'where_it_fits')) {
                $table->text('where_it_fits')->nullable()->after('how_supplied');
            }
            if (! Schema::hasColumn('product_contents', 'who_its_for')) {
                $table->text('who_its_for')->nullable()->after('where_it_fits');
            }
            if (! Schema::hasColumn('product_contents', 'what_you_get')) {
                $table->text('what_you_get')->nullable()->after('who_its_for');
            }
            if (! Schema::hasColumn('product_contents', 'key_differences')) {
                $table->json('key_differences')->nullable()->after('what_you_get');
            }
            if (! Schema::hasColumn('product_contents', 'answer_capsule')) {
                $table->text('answer_capsule')->nullable()->after('key_differences');
            }
            if (! Schema::hasColumn('product_contents', 'related_reading')) {
                $table->json('related_reading')->nullable()->after('answer_capsule');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_contents', function (Blueprint $table) {
            $columns = [
                'why_choose',
                'how_supplied',
                'where_it_fits',
                'who_its_for',
                'what_you_get',
                'key_differences',
                'answer_capsule',
                'related_reading',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('product_contents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
