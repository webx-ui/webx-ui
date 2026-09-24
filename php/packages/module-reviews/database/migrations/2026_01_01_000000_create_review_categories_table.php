<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories of reviews: the shared columns of every module's categories and nothing else
 * (decision 5 of the reviews spec). No address, no SEO, no blocks — a category of reviews is what
 * a block picks them by and a button in its filter, never a page — so `slug` stays empty.
 *
 * The first day: nothing here points at another package's table (CLAUDE.md §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_categories', function (Blueprint $table): void {
            $table->id();

            $table->category();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_categories');
    }
};
