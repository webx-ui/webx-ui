<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A table rather than a cache, and that is the point: a cache flushed on production
        // must not turn into a page without styles. Written the first time a set of types is
        // rendered together, read by the route that serves `/blocks/{hash}.css`.
        Schema::create('block_bundles', function (Blueprint $table): void {
            // The hash of the sorted list of "type + version" pairs.
            $table->string('hash', 16)->primary();
            // What it was built from — for debugging and for pruning.
            $table->json('types');
            // Glued in `sort` order, so that two blocks arguing over one rule are settled by
            // their author and not by the alphabet.
            $table->longText('css');
            $table->longText('js')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_bundles');
    }
};
