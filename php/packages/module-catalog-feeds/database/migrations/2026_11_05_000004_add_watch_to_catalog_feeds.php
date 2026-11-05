<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the watcher remembers between two looks at the catalogue (§7.3 of the feeds spec).
 *
 * `dirty_since` moves with every new generation, which is what `quiet_minutes` waits on, so it
 * cannot also say when the first change came: `first_dirty_at` does, for `max_wait`.
 * `seen_generation` is how a new generation is told from the one already noticed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_feeds', function (Blueprint $table): void {
            $table->string('seen_generation', 64)->nullable();
            $table->timestamp('first_dirty_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_feeds', function (Blueprint $table): void {
            $table->dropColumn(['seen_generation', 'first_dirty_at']);
        });
    }
};
