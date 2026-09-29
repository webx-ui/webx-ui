<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The categories of the catalogue: a tree whose slugs are flat (§6.1).
 *
 * The second day of migrations rather than the first, like every table here: the cover points
 * into `media_files`, and a foreign key into another package's table has to sort after it
 * (docs/pitfalls/laravel-and-php.md).
 *
 * No unique index on the slug: an address is unique in `routes`, which also knows the pages a
 * category's slug competes with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table): void {
            $table->id();
            $table->nestedSet();

            $table->json('name')->nullable();
            $table->json('slug')->nullable();
            $table->json('description')->nullable();

            // The tile of the category, from the library: a few hundred of these are exactly
            // what an editor's tree is for, unlike the product photos (§10.4).
            $table->foreignId('cover_id')->nullable()->constrained('media_files')->nullOnDelete();

            $table->boolean('is_published')->default(false)->index();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_categories');
    }
};
