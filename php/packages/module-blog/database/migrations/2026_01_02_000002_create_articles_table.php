<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An article: a title, a slug, an announcement, a cover, an author, a tree of blocks and a
 * draft — and most of those columns are written by somebody else's macro (§3).
 *
 * `$table->draft()` brings `draft` and `published_at`, and that one timestamp is the whole of
 * publication *and* of scheduling (§2.7): a date in the future means the article is waiting, a
 * date in the past moves it down the feed. No queue and no scheduler — the check happens where
 * the article is read.
 *
 * The two indexes are what the feed orders by: every listing in the module is pinned first,
 * then `published_at` descending.
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
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();

            $table->json('title')->nullable();
            $table->json('slug')->nullable();

            // The announcement under the heading in a listing: text, no markup, translatable.
            $table->json('lead')->nullable();

            $table->foreignId('cover_id')->nullable()->constrained('media_files')->nullOnDelete();

            // The author is an administrator (§2.14), and losing the account must not lose the
            // article: an editor who left is a byline that needs replacing, not a page to delete.
            $table->foreignId('author_id')->nullable()->constrained('cms_users')->nullOnDelete();

            $table->boolean('pinned')->default(false);

            $table->blocks();
            $table->draft();

            $table->softDeletes();
            $table->timestamps();

            $table->index('published_at');
            $table->index('pinned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
