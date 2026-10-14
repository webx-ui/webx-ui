<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The author's networks (§19.11): a list of `{ network, url }` — shown on their page and named as
 * the `sameAs` of their `Person`, which is how a search engine ties the page to the same person
 * elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('blog_authors', 'socials')) {
            return;
        }

        Schema::table('blog_authors', function (Blueprint $table): void {
            $table->json('socials')->nullable()->after('photo_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('blog_authors', 'socials')) {
            return;
        }

        Schema::table('blog_authors', function (Blueprint $table): void {
            $table->dropColumn('socials');
        });
    }
};
