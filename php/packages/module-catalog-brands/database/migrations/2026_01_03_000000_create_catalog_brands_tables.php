<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brands and which one each product is of (§2.3 of the dictionaries spec).
 *
 * The shared columns of every module's categories (`$table->category()`: the translated name as
 * `title`, the translated `slug` — the brand's address and its value in the filter's address —
 * the order, `is_visible` — here "published" — the fields of the project, the bin), plus the
 * brand's own: a logo from the media library, a description, and whether it is featured.
 *
 * A product without a row has no brand.
 *
 * The third day: the logo points at the library's files, made on the first, and the link table at
 * the catalogue's products, made on the second.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_brands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logo_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->json('description')->nullable();
            $table->boolean('is_featured')->default(false)->index();

            $table->category();

            $table->index('is_visible');
        });

        Schema::create('catalog_product_brand', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('catalog_products')->cascadeOnDelete();
            $table->foreignId('brand_id')->index()->constrained('catalog_brands');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_product_brand');
        Schema::dropIfExists('catalog_brands');
    }
};
