<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The blog's tables under the blog's name.
 *
 * Every section names its tables after itself (`faq_*`, `inbox_*`, `catalog_*`); the blog was
 * the one that took the bare words — `articles`, `tags`, `rubrics` — which are exactly the
 * names a site is most likely to want for tables of its own.
 *
 * A rename, because sites have articles in them. The keys of the link tables follow the tables
 * they point at on MySQL, Postgres and SQLite alike. Each one is skipped when it has already
 * moved, so a site that ran half of this finishes it on the next `migrate`.
 */
return new class extends Migration
{
    private const NAMES = [
        'rubrics' => 'blog_rubrics',
        'tags' => 'blog_tags',
        'articles' => 'blog_articles',
        'article_rubric' => 'blog_article_rubric',
        'article_tag' => 'blog_article_tag',
        'article_related' => 'blog_article_related',
    ];

    public function up(): void
    {
        foreach (self::NAMES as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $from => $to) {
            if (Schema::hasTable($to) && ! Schema::hasTable($from)) {
                Schema::rename($to, $from);
            }
        }
    }
};
