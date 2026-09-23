<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A menu is a key and a name (§4).
 *
 * The table is the source of truth for every menu, declared in the configuration or not: the
 * configuration only says which ones the templates of this site ask for, and a row for one of
 * those appears the first time it is saved rather than on boot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();

            // What a template calls the menu by. Unique, and for a declared menu unchangeable:
            // renaming it would break `menu('header')` in a view nobody is looking at.
            $table->string('key', 64)->unique();

            // Translated, because this name is only ever read in the panel.
            $table->json('title')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
