<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The gallery of a product (§10.4): files on a disk of their own, not rows of the media library.
 * The first by `position` is the main picture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('catalog_products')->cascadeOnDelete();

            $table->string('path');

            // Translatable, like every word of a product: an `alt` is read aloud to somebody.
            $table->json('alt')->nullable();
            $table->json('title')->nullable();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->integer('position')->default(0);

            $table->timestamps();

            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_product_images');
    }
};
