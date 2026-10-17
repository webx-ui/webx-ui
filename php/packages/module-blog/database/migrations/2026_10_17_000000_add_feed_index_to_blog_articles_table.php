<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_articles', function (Blueprint $table): void {
            // The feed and every listing: `deleted_at is null and published_at <= now order by
            // pinned desc, published_at desc, id desc limit 12`. One key in that order lets the
            // server read the page straight off the index; the single-column keys on `pinned`
            // and `published_at` the table was created with each serve half of it and a sort.
            $table->index(['deleted_at', 'pinned', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('blog_articles', function (Blueprint $table): void {
            $table->dropIndex(['deleted_at', 'pinned', 'published_at']);
        });
    }
};
