<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A recipe (§4): a page of fixed structure, so every part of it is a column rather than a block.
 *
 * `gallery` is a list of `wx-media` values and the first one is the cover (decision 9) — a list
 * rather than a key into the library, so nothing here points at another package's table.
 * `nutrition` is one map with the languages on each key, not a map of keys per language: the form
 * draws one field per key with its own language chip. The services and the similar recipes are
 * `webx_relations` rows (§3), not tables of this module.
 *
 * No unique index on the slug: an address is unique in `routes`, which also knows the categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table): void {
            $table->id();

            $table->json('title')->nullable();
            $table->json('slug')->nullable();

            // On a card and the fallback description: text, no markup.
            $table->json('lead')->nullable();

            $table->json('gallery')->nullable();

            // Translated documents (`wx-rich-text`): the markup reads their <li> (decision 6).
            $table->json('ingredients')->nullable();
            $table->json('method')->nullable();

            $table->json('nutrition')->nullable();

            $table->unsignedSmallInteger('total_minutes')->nullable();
            $table->unsignedTinyInteger('servings')->nullable();

            // The one order there is (decision 4): no place inside a category or a service.
            $table->integer('position')->default(0);

            // The fields a project patched onto the editor (`HasExtra`).
            $table->json('extra')->nullable();

            $table->draft();

            $table->softDeletes();
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
