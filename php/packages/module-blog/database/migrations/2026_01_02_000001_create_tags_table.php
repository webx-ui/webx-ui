<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags: made from the article form, sorted out afterwards on a screen of their own (§2.8).
 *
 * `noindex` defaults to what `webx-blog.tags.noindex` says, which is true: a tag page has an
 * address because people follow tags, and stays out of the index because a hundred thin
 * listings is how a site teaches a search engine to ignore it. A rule in `seo_urls` written
 * for a tag's address overrides the flag (§12) — that is decided when the page is rendered,
 * not stored here, so the two ways of opening a tag stay independently visible in the panel.
 *
 * No soft deletes: a tag is a word. Deleting one is losing the word, not losing the articles,
 * and a bin full of words nobody will look through is a table that only grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $noindex = (bool) config('webx-blog.tags.noindex', true);

        Schema::create('tags', function (Blueprint $table) use ($noindex): void {
            $table->id();

            $table->json('title')->nullable();
            $table->json('slug')->nullable();

            $table->boolean('noindex')->default($noindex);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
