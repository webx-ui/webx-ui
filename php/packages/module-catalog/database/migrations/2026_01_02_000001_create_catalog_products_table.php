<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product (§3). Indexes on everything the catalogue looks things up or joins by, from the first
 * migration — the lesson of a shop that had none even on the article number.
 *
 * The article number is unique across the deleted too: a deleted product is still named by the
 * orders it was in, and a new one under the same number would answer for it. The slug is not
 * unique at all — `-{id}` is what makes the address unique (§4).
 *
 * Price, old price and barcode are always here; the config hides them. Satellites never add a
 * column to this table (decision 2 of the family): they keep their own with a `product_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_products', function (Blueprint $table): void {
            $table->id();

            $table->string('sku', 64)->nullable()->unique();
            $table->string('barcode', 64)->nullable()->index();
            $table->string('external_id', 64)->nullable()->unique();

            $table->json('name')->nullable();
            $table->json('slug')->nullable();
            $table->json('summary')->nullable();
            $table->json('description')->nullable();

            // The main category: the crumbs go through it and a deleted product redirects to it.
            // Restricted rather than nulled on delete, because categories are soft-deleted and a
            // real delete of one that products still name is a mistake to refuse.
            $table->foreignId('category_id')->nullable()->constrained('catalog_categories')->restrictOnDelete();

            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('old_price', 12, 2)->nullable();
            $table->string('unit', 16)->nullable();

            $table->integer('priority')->default(0)->index();
            $table->boolean('is_published')->default(false)->index();

            $table->softDeletes();
            $table->timestamps();

            $table->index('deleted_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_products');
    }
};
