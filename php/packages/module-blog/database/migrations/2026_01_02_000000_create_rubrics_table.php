<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rubrics: flat, ordered by hand, and with no draft of their own (§3).
 *
 * A rubric is navigation rather than content, so what it has instead of a publication is
 * `is_visible`: hidden, it drops out of the menu and answers 404, and its articles go on
 * answering at their own addresses. They are not its property — an article can be in three
 * rubrics at once, and hiding one of them must not take the article off the site.
 *
 * No unique index on the slug here either: uniqueness of an address lives in `routes`, which
 * is unique on `(locale, path)` and knows about articles and pages too.
 *
 * The whole module migrates on the second day, not the first. Laravel sorts every package's
 * migrations together by filename, so `2026_01_01_…` is the day on which each package builds
 * what is its own — and a foreign key to somebody else's table cannot be added on it: `rubrics`
 * ran before `media_files` existed and MariaDB refused the constraint outright (sqlite says
 * nothing about it, so only the smoke against a real database sees this).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table): void {
            $table->id();

            // Translatable: the address is part of the content, so a rubric with no slug in a
            // language simply has no address in it (§9).
            $table->json('title')->nullable();
            $table->json('slug')->nullable();
            $table->json('lead')->nullable();

            $table->foreignId('cover_id')->nullable()->constrained('media_files')->nullOnDelete();

            $table->boolean('is_visible')->default(true);
            $table->integer('position')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubrics');
    }
};
