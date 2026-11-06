<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The additional barcodes of a product; the main one stays a column of the product and never
 * appears here as well. A table rather than JSON: a search by a code is an exact match on a hot
 * path, which a plain index answers on every database. Not unique: two products may share a code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_product_barcodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('catalog_products')->cascadeOnDelete();
            $table->string('barcode', 64)->index();
            $table->unsignedInteger('position')->default(0);

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_product_barcodes');
    }
};
