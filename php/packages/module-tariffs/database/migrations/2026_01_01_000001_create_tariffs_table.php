<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tariff (§3 of the tariffs spec): a price card.
 *
 * - The string columns have no defaults: `currency` and `button_variant` get their value from the
 *   form, and a default longer than its column is what MariaDB refuses at `CREATE TABLE` while
 *   sqlite swallows it (CLAUDE.md §4).
 * - The price is a decimal, not a float: 19.99 stays 19.99.
 * - "What is included" is json rather than a table: a handful of rows, always read with the
 *   tariff, in the order of the array. A row is `{ "text": { "en": … } }` — the shape a
 *   `wx-repeater` writes, and room for the "included / not included" mark later.
 * - `featured` and `published`, not `visible`/`hidden`: those are properties of Eloquent
 *   (CLAUDE.md §4).
 *
 * No draft and no history (decision 10): `published` is the whole of a tariff's life.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariffs', function (Blueprint $table): void {
            $table->id();

            // Translatable: the name, the badge ("30 HOURS / 25$"), the period ("/mo") and the
            // words that stand in for a price ("On request").
            $table->json('name')->nullable();
            $table->json('badge')->nullable();

            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->json('period')->nullable();
            $table->json('price_text')->nullable();

            $table->json('features')->nullable();
            $table->json('description')->nullable();

            // One button: its label per language, a `wx-link` value, and a key of the config's looks.
            $table->json('button_label')->nullable();
            $table->json('button_link')->nullable();
            $table->string('button_variant', 32)->nullable();

            $table->boolean('featured')->default(false);
            $table->boolean('published')->default(false);

            // The order of the whole list. The place inside a group is on the link.
            $table->integer('position')->default(0);

            // The fields a project patched onto the editor (`HasExtra`).
            $table->json('extra')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariffs');
    }
};
