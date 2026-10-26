<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The configurator a product of the catalogue opens, and where it starts (§22.2 of the
 * configurator spec): one row per product, the configurator by its key, the preset as
 * `{ step: value, field: number }`.
 *
 * A table of the configurator's own rather than a column of the product: the catalogue adds no
 * columns to its products for anybody (decision 2 of the catalogue family) and knows nothing of
 * configurators. No foreign key to `catalog_products` either — the catalogue may not be installed,
 * and a product is never erased, only binned; a row of a product that is gone is simply not read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_product', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->primary();
            $table->string('configurator', 64)->index();
            $table->json('preset')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_product');
    }
};
