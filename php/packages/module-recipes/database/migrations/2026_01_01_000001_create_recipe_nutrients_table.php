<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a recipe is rich in — iron, fibre: the second kind of category a recipe is filed under
 * (decision 8). The shared columns and nothing else: no address, no SEO, and the slug stays empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_nutrients', function (Blueprint $table): void {
            $table->id();

            $table->category();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_nutrients');
    }
};
