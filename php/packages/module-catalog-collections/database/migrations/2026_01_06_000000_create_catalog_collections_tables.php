<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collections and their hand-picked products (§2 of the collections spec).
 *
 * `category_id` has no foreign key on purpose: `nullOnDelete` would quietly turn a collection of
 * one category into a collection of the whole catalogue, and a category that is gone gives an
 * empty selection instead. The key stays taken by a collection in the bin, so restoring it never
 * meets a conflict.
 *
 * The sixth day: the list points at the catalogue's products, and the landings hold the fifth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_collections', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->json('title');
            $table->string('source', 16)->default('manual');
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->json('filters')->nullable();
            $table->boolean('on_sale')->default(false);
            $table->string('sort', 32)->nullable();
            $table->boolean('only_purchasable')->default(true);
            $table->unsignedSmallInteger('limit')->default(8);
            $table->string('view', 16)->default('grid');
            $table->json('link')->nullable();
            $table->json('link_text')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->string('attention', 32)->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('catalog_collection_products', function (Blueprint $table): void {
            $table->foreignId('collection_id')->constrained('catalog_collections')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('catalog_products')->cascadeOnDelete();
            $table->integer('position')->default(0);

            $table->primary(['collection_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_collection_products');
        Schema::dropIfExists('catalog_collections');
    }
};
