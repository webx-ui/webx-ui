<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which facets a category shows and in what order (§6.2). No rows means the category did not
 * set its own and inherits from the nearest ancestor that did.
 *
 * The facet is a key of the registry rather than a foreign key: facets are code, registered by
 * whichever modules are installed, not rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_category_facets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('catalog_categories')->cascadeOnDelete();
            $table->string('facet_key', 64);
            $table->boolean('is_visible')->default(true);
            $table->integer('position')->default(0);

            $table->unique(['category_id', 'facet_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_category_facets');
    }
};
