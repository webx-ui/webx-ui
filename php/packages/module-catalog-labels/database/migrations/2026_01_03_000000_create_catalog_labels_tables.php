<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Labels and what they are on (§2.1 of the dictionaries spec).
 *
 * The shared columns of every module's categories (`$table->category()`: the translated name as
 * `title`, the order, `is_visible` — here "in the filter" — the fields of the project, the bin),
 * plus the label's own: a code one for every language, a tone and whether it is drawn as a badge.
 * The translated `slug` of the shared columns stays empty: a label has no page.
 *
 * The third day: the link table points at the catalogue's products, which are made on the second
 * (docs/pitfalls/laravel-and-php.md, «Миграции всех пакетов сортируются вместе»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_labels', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('color', 16)->default('neutral');
            $table->boolean('is_badge')->default(true);

            $table->category();
        });

        Schema::create('catalog_label_product', function (Blueprint $table): void {
            $table->foreignId('label_id')->constrained('catalog_labels')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('catalog_products')->cascadeOnDelete();

            $table->primary(['label_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_label_product');
        Schema::dropIfExists('catalog_labels');
    }
};
