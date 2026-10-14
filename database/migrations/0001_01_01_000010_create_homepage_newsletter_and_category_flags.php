<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories') && ! Schema::hasColumn('categories', 'is_featured')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->boolean('is_featured')->default(false)->after('is_visible');
            });
        }

        if (! Schema::hasTable('homepage_contents')) {
            Schema::create('homepage_contents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->boolean('announcement_enabled')->default(true);
                $table->string('announcement_message')->nullable();
                $table->boolean('announcement_use_shipping_threshold')->default(true);
                $table->string('hero_eyebrow')->nullable();
                $table->string('hero_headline')->nullable();
                $table->text('hero_description')->nullable();
                $table->string('hero_primary_label')->nullable();
                $table->string('hero_primary_url')->nullable();
                $table->string('hero_secondary_label')->nullable();
                $table->string('hero_secondary_url')->nullable();
                $table->boolean('editorial_enabled')->default(true);
                $table->string('editorial_label')->nullable();
                $table->string('editorial_heading')->nullable();
                $table->text('editorial_body')->nullable();
                $table->string('editorial_cta_label')->nullable();
                $table->string('editorial_cta_url')->nullable();
                $table->string('editorial_image_url')->nullable();
                $table->boolean('newsletter_enabled')->default(true);
                $table->string('newsletter_heading')->nullable();
                $table->text('newsletter_body')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->json('section_order')->nullable();
                $table->json('social_links')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('homepage_faqs')) {
            Schema::create('homepage_faqs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('question');
                $table->text('answer');
                $table->boolean('is_published')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('newsletter_subscribers')) {
            Schema::create('newsletter_subscribers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('email');
                $table->timestamp('consented_at');
                $table->string('consent_ip', 45)->nullable();
                $table->text('consent_user_agent')->nullable();
                $table->string('source')->default('homepage');
                $table->string('provider_status')->default('pending');
                $table->timestamps();

                $table->unique('email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('homepage_faqs');
        Schema::dropIfExists('homepage_contents');

        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'is_featured')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('is_featured');
            });
        }
    }
};
