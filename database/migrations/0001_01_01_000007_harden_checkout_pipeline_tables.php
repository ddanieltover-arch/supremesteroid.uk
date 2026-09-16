<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token', 64)->unique();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->json('snapshot');
            $table->string('status')->default('OPEN'); // OPEN, CONVERTED, EXPIRED
            $table->timestamp('expires_at')->index();
            $table->foreignUuid('order_id')->nullable()->references('id')->on('orders')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference', 100)->nullable()->after('currency');
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('reference');
        });

        Schema::dropIfExists('checkout_sessions');
    }
};
