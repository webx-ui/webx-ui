<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landings and their recommended products (§4 of the landings spec).
 *
 * The set is stored by the ids of its values, normalised, and its hash with the base is unique:
 * two landings of one set on one base are one address too many. The hash stays on a landing in
 * the bin — restoring it must not make a second one — so a set is free again only when its landing
 * is gone for good. A set a merge made into another landing's, or one emptied, holds no hash: the
 * landing waits for an editor, unpublished, and the form's save asks for the hash again.
 *
 * The fifth day: the base points at the catalogue's categories, made on the second.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_landings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('catalog_categories')->cascadeOnDelete();
            $table->json('filters');
            $table->string('filters_hash', 64)->nullable()->unique();
            $table->json('name');
            $table->json('slug');
            $table->json('h1')->nullable();
            $table->json('text_above')->nullable();
            $table->json('text_below')->nullable();
            $table->string('sort', 32)->nullable();
            $table->boolean('on_category')->default(false);
            $table->integer('position')->default(0);
            $table->boolean('is_published')->default(false);
            $table->string('attention', 32)->nullable()->index();
            $table->integer('products_count')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['category_id', 'is_published']);
        });

        Schema::create('catalog_landing_products', function (Blueprint $table): void {
            $table->foreignId('landing_id')->constrained('catalog_landings')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('catalog_products')->cascadeOnDelete();
            $table->integer('position')->default(0);

            $table->primary(['landing_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_landing_products');
        Schema::dropIfExists('catalog_landings');
    }
};
