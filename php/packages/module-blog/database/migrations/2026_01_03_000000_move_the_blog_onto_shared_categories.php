<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rubrics become the blog's categories of the shared kind (§3.2 of the services spec).
 *
 * A new migration rather than an edit of the ones already run: both demo sites have them in
 * their `migrations` table, and an edited file would simply never run there. The tables keep
 * their names — `rubrics` and `article_rubric` with `rubric_id` — because only where the code
 * comes from changes.
 *
 * What is added is what `$table->category()` and `$table->categoryLinks()` have and the blog's
 * tables did not: `extra` on both records for the fields a project patches onto their screens,
 * and `item_position` in the link, the place of an article inside a rubric. The blog orders by
 * date and never shows that order, but every link has to have one — so it is filled by date
 * here, newest first, which is the order a rubric page lists its articles in anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rubrics', function (Blueprint $table): void {
            $table->json('extra')->nullable();
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->json('extra')->nullable();
        });

        Schema::table('article_rubric', function (Blueprint $table): void {
            $table->integer('item_position')->default(0);
            $table->index(['rubric_id', 'item_position']);
        });

        $rubrics = DB::table('article_rubric')->distinct()->pluck('rubric_id');

        foreach ($rubrics as $rubric) {
            $articles = DB::table('article_rubric')
                ->join('articles', 'articles.id', '=', 'article_rubric.article_id')
                ->where('article_rubric.rubric_id', $rubric)
                ->orderByDesc('articles.published_at')
                ->orderByDesc('articles.id')
                ->pluck('articles.id');

            foreach ($articles as $position => $article) {
                DB::table('article_rubric')
                    ->where('rubric_id', $rubric)
                    ->where('article_id', $article)
                    ->update(['item_position' => $position]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('article_rubric', function (Blueprint $table): void {
            $table->dropIndex(['rubric_id', 'item_position']);
            $table->dropColumn('item_position');
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('extra');
        });

        Schema::table('rubrics', function (Blueprint $table): void {
            $table->dropColumn('extra');
        });
    }
};
