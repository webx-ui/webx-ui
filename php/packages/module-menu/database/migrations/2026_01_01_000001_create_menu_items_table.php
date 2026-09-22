<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The items of every menu, as one nested set scoped by `menu_id` (§4).
 *
 * Nesting is here even though the first version draws no dropdowns: a footer is a heading with
 * a list under it, which is already two levels.
 *
 * One tree for every language, and the labels translated. An item points at an entity, and that
 * entity already has a row per language in the address registry — so the same item resolves to
 * the right address in each of them. A second tree would repeat the structure for the sake of
 * the one thing that differs. The case it would have covered, an item that exists in one
 * language only, is the `locales` column.
 *
 * Both migrations are `2026_01_01_*`: everything this table points at is pointed at by a morph
 * pair rather than by a foreign key, so there is no other package's table to wait for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->nestedSet();

            // Empty by default, and that is a property rather than laziness: an item with no
            // label of its own is called what the entity behind it is called, so renaming a
            // page renames the item. It is overridden where the tree says "Work With Katia"
            // and the header wants something shorter.
            $table->json('title')->nullable();

            // What the item points at, said out loud. Six characters into a column of eight:
            // MariaDB refuses a default longer than its column on CREATE TABLE, and sqlite
            // says nothing at all.
            $table->string('target', 8)->default('entity');

            $table->string('entity_type', 32)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();

            // Absolute, or a path on this site that is given the language prefix on render.
            $table->string('url', 1024)->nullable();

            // The fragment, without its `#`, for either kind of target: a chosen page has
            // nowhere to write one, because its address is asked for rather than typed.
            $table->string('hash', 190)->nullable();

            // A string and not a boolean "is this a call to action": the flag covers exactly
            // one case, and the second request is always "and this one an outline button".
            $table->string('variant', 32)->default('link');

            // How to draw it, as against where it goes — a heading with children and a page of
            // its own is an ordinary thing, and one column for both questions would lose it.
            $table->boolean('is_heading')->default(false);

            $table->boolean('new_tab')->default(false);

            // A subset of nofollow · sponsored · ugc. `noopener noreferrer` is not in it: the
            // render adds those itself whenever the item opens a new tab.
            $table->json('rel')->nullable();

            // Empty means every language.
            $table->json('locales')->nullable();

            $table->boolean('visible')->default(true);

            $table->timestamps();

            // Reading a menu is one ordered walk of one scope.
            $table->index(['menu_id', 'lft']);

            // Which menus an entity stands in — the cache is forgotten through this index, and
            // so is the panel's answer to "where else is this page linked from".
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
