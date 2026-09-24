<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which categories a recipe is in, in which order — the first is the main one.
 *
 * `item_position` comes with the shared macro and stays unused (decision 4): the shared code
 * writes it, and leaving it out for one module would mean branching that code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_category_recipe', function (Blueprint $table): void {
            $table->categoryLinks('recipe', 'recipe_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_category_recipe');
    }
};
