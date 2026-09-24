<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories of services: the shared columns of every module's categories, plus the three a page
 * of the site needs (§4.2 of the services spec).
 *
 * `blocks` is here even though the panel only offers the tab when `webx-services.categories.blocks`
 * is on: a category page is a landing page often enough, and an empty column is cheaper than a
 * migration on every site that turns it on later. No draft: a category is edited in place, like a
 * rubric.
 *
 * The second day, not the first: `cover_id` points at `media_files`, and Laravel sorts every
 * package's migrations together by filename (CLAUDE.md §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table): void {
            $table->id();

            $table->category();

            // The introduction above the list: a document, translatable.
            $table->json('lead')->nullable();
            $table->foreignId('cover_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->blocks();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
