<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The additional categories of a product. Only those: the main one is a column of the product,
 * and never appears here as well (decision 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_category_product', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained('catalog_categories')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('catalog_products')->cascadeOnDelete();

            $table->primary(['category_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_category_product');
    }
};
