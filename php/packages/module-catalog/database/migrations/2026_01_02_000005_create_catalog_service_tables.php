<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tables the catalogue keeps for itself rather than for an editor: the indexing queue (§8.3),
 * popularity (§9) and the runs of bulk actions (§11.4).
 *
 * No foreign keys into `catalog_products` on the first two: a product that is gone has nothing
 * left to index or to count, and the worker that reads them cleans up after it rather than a
 * cascade doing it one row at a time inside somebody's delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Written only when the engine keeps an index of its own; under `SqlEngine` the database
        // is the index and this stays empty.
        Schema::create('catalog_index_queue', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->primary();
            $table->timestamp('queued_at')->nullable()->index();
        });

        // A table of its own so that the nightly recount does not rewrite `catalog_products`.
        Schema::create('catalog_product_popularity', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->primary();
            $table->decimal('views', 14, 4)->default(0);
            $table->decimal('score', 14, 4)->default(0)->index();
            $table->timestamp('computed_at')->nullable();
        });

        Schema::create('catalog_bulk_runs', function (Blueprint $table): void {
            $table->id();
            // No foreign key: the frame does not know where the administrators live.
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('action', 64);
            $table->json('params')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('done')->default(0);
            $table->unsignedInteger('failed')->default(0);
            // The first hundred, by product: enough to act on, not a copy of a failed import.
            $table->json('errors')->nullable();
            $table->string('status', 16)->default('queued')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_bulk_runs');
        Schema::dropIfExists('catalog_product_popularity');
        Schema::dropIfExists('catalog_index_queue');
    }
};
