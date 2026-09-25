<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories of events — the formats a site runs: breakfast meetings, cooking classes. The shared
 * columns, plus the two a page of the site needs.
 *
 * The cover is a `wx-media` value rather than a key into the library, so nothing here points at
 * another package's table and every migration of the module can stay on the first day
 * (CLAUDE.md §4 on how migrations of all packages are sorted together).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_categories', function (Blueprint $table): void {
            $table->id();

            $table->category();

            // The introduction above the list: a document, translatable.
            $table->json('lead')->nullable();
            $table->json('cover')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_categories');
    }
};
