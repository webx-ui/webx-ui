<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a file written by a PHP class is served as (§10 of the feeds spec). Kept with the build: the
 * class may be gone by the time the file is asked for, and the file goes on being served. Null for
 * a template, whose format says it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_feed_builds', function (Blueprint $table): void {
            $table->string('content_type', 128)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_feed_builds', function (Blueprint $table): void {
            $table->dropColumn('content_type');
        });
    }
};
