<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an article is in, what it is about, and what it points at (§3).
 *
 * Every foreign key cascades. Soft-deleting an article leaves these rows alone — it is coming
 * back — but deleting it for good has to take them, and `article_related.related_id` cascades
 * for the same reason from the other side: an article that is gone must disappear out of
 * everybody's "read next" rather than hang there as a row pointing at nothing.
 *
 * The unique index on `article_tag` is not decoration either: it is what makes merging two tags
 * safe (§6). An article that carried both ends up carrying one, and the database is what says so
 * rather than a `distinct()` somebody has to remember.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_rubric', function (Blueprint $table): void {
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('rubric_id')->constrained('rubrics')->cascadeOnDelete();

            // The order the editor dragged them into. The first one is the main rubric (§2.6):
            // it goes in the breadcrumbs, in "more in this rubric" and in `<category>` of the
            // RSS. There is no separate switch, because one control beats two.
            $table->integer('position')->default(0);

            $table->unique(['article_id', 'rubric_id']);
            $table->index('rubric_id');
        });

        Schema::create('article_tag', function (Blueprint $table): void {
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();

            $table->unique(['article_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('article_related', function (Blueprint $table): void {
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('related_id')->constrained('articles')->cascadeOnDelete();

            $table->integer('position')->default(0);

            $table->unique(['article_id', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_related');
        Schema::dropIfExists('article_tag');
        Schema::dropIfExists('article_rubric');
    }
};
