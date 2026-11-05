<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The feeds and everything hanging off them (§3 of the feeds spec).
 *
 * After the catalogue's own: the exclusions and the category map point at its products and
 * categories (docs/pitfalls/laravel-and-php.md, «Миграции всех пакетов сортируются вместе»).
 * `current_build_id` has no foreign key, as `last_run_id` of the exchange has none: the journal is
 * cleaned on its own schedule and the current build is what it leaves alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_feeds', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('preset', 32)->nullable();
            $table->string('format', 16);
            $table->string('driver', 64)->nullable();
            $table->string('locale', 8);
            $table->string('currency', 3);
            $table->longText('head')->nullable();
            $table->longText('item')->nullable();
            $table->longText('foot')->nullable();
            $table->string('separator', 32);
            $table->json('filter');
            $table->json('stock_map');
            $table->string('taxonomy', 32)->nullable();
            $table->json('custom_labels');
            $table->json('sources');
            $table->json('options');
            $table->string('schedule', 16)->default('manual');
            $table->time('schedule_at')->nullable();
            $table->boolean('rebuild_on_change')->default(false);
            $table->string('access_key', 64)->unique();
            $table->timestamp('key_issued_at');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('current_build_id')->nullable();
            $table->string('built_generation', 64)->nullable();
            $table->timestamp('dirty_since')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_feed_exclusions', function (Blueprint $table): void {
            $table->foreignId('feed_id')->constrained('catalog_feeds')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('catalog_products')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['feed_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('catalog_feed_taxonomies', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32);
            $table->string('locale', 8);
            $table->string('version', 64)->nullable();
            $table->string('source', 500)->nullable();
            $table->timestamp('imported_at')->nullable();

            $table->unique(['key', 'locale']);
        });

        Schema::create('catalog_feed_taxonomy_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained('catalog_feed_taxonomies')->cascadeOnDelete();
            $table->string('external_id', 32);
            $table->string('path', 500);

            $table->unique(['taxonomy_id', 'external_id']);
            $table->index(['taxonomy_id', 'path']);
        });

        Schema::create('catalog_feed_categories', function (Blueprint $table): void {
            $table->foreignId('feed_id')->constrained('catalog_feeds')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('catalog_categories')->cascadeOnDelete();
            $table->foreignId('taxonomy_item_id')->nullable()->constrained('catalog_feed_taxonomy_items')->nullOnDelete();
            $table->string('value')->nullable();

            $table->primary(['feed_id', 'category_id']);
        });

        Schema::create('catalog_feed_builds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feed_id')->constrained('catalog_feeds')->cascadeOnDelete();
            $table->string('status', 16)->default('queued');
            $table->string('trigger', 16)->default('manual');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('selected')->default(0);
            $table->unsignedInteger('written')->default(0);
            $table->unsignedInteger('filtered')->default(0);
            $table->unsignedInteger('excluded')->default(0);
            $table->json('rejected')->nullable();
            $table->json('warnings')->nullable();
            $table->text('error')->nullable();
            $table->string('path', 500)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->string('etag', 64)->nullable();
            $table->timestamp('content_at')->nullable();
            $table->unsignedBigInteger('cursor')->nullable();
            $table->unsignedBigInteger('body_bytes')->nullable();
            $table->string('generation', 64)->nullable();
        });

        // No key on the product: the journal keeps a reason after the product is gone.
        Schema::create('catalog_feed_build_rejects', function (Blueprint $table): void {
            $table->foreignId('build_id')->constrained('catalog_feed_builds')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('reason', 32);

            $table->index('build_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_feed_build_rejects');
        Schema::dropIfExists('catalog_feed_builds');
        Schema::dropIfExists('catalog_feed_categories');
        Schema::dropIfExists('catalog_feed_taxonomy_items');
        Schema::dropIfExists('catalog_feed_taxonomies');
        Schema::dropIfExists('catalog_feed_exclusions');
        Schema::dropIfExists('catalog_feeds');
    }
};
