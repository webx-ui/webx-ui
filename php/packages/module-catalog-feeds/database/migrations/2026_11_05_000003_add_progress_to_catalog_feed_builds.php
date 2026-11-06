<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a build carries from one job to the next (§7.1 of the feeds spec).
 *
 * `state` holds what the head needs and only the pages know, the categories above all, and is
 * written with the cursor, so a page that is repeated does not count twice. `progress_at` says a
 * long build is alive: `started_at` cannot, a big catalogue honestly outlasts `stale_minutes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_feed_builds', function (Blueprint $table): void {
            $table->json('state')->nullable();
            $table->timestamp('progress_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_feed_builds', function (Blueprint $table): void {
            $table->dropColumn(['state', 'progress_at']);
        });
    }
};
