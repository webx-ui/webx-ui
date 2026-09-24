<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a recipe is rich in. The shared link table, so the shared code writes it — a nutrient is a
 * category to it, just one without a page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_nutrient_recipe', function (Blueprint $table): void {
            $table->categoryLinks('recipe', 'recipe_nutrients');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_nutrient_recipe');
    }
};
