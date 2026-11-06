<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What became of an import by address (§6.2 of the feeds spec): it runs in the queue, so the person
 * who asked reads the answer here. `imported_at` stays the date of the last list that came in: a
 * failed import leaves that list in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_feed_taxonomies', function (Blueprint $table): void {
            $table->string('status', 16)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('queued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_feed_taxonomies', function (Blueprint $table): void {
            $table->dropColumn(['status', 'error', 'queued_at']);
        });
    }
};
