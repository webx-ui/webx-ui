<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The exchange (§6 of the exchange spec): saved profiles, the runs of imports and exports, the
 * errors of a run by row, and the products a run has met — what it exported, or what an import
 * found in the file, which "not in the file" is counted against.
 *
 * And the hash of the address a picture of the gallery was downloaded from, so that a price list
 * sent every night downloads each picture once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_exchange_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('direction', 8);
            $table->string('format', 16);
            // Import: the key, the mode, `empty_clears`, `absent` and its scope, `create_missing`,
            // `images`, a CSV's encoding and separator. Export: the languages.
            $table->json('options')->nullable();
            // Import: the header of the file → the column code. Export: the column codes in order.
            $table->json('mapping')->nullable();
            // No foreign key: the two tables would point at each other, and a pruned run leaves
            // the profile as it was.
            $table->unsignedBigInteger('last_run_id')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_exchange_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->nullable()->constrained('catalog_exchange_profiles')->nullOnDelete();
            // A snapshot of what it was asked to do: the profile may change while it runs.
            $table->string('direction', 8);
            $table->string('format', 16);
            $table->json('options')->nullable();
            $table->json('mapping')->nullable();
            $table->boolean('dry_run')->default(false);
            $table->string('status', 16)->default('queued')->index();
            // The name of the uploaded file or its address.
            $table->string('source', 2048)->nullable();
            // On `exchange.disk`: what an import reads, or the export it wrote.
            $table->string('file')->nullable();
            $table->unsignedInteger('rows_total')->default(0);
            // Import: the rows of the file behind the last committed chunk — where a retried job
            // starts again. Export: the products written.
            $table->unsignedInteger('rows_done')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('absent')->default(0);
            $table->unsignedBigInteger('history_id')->nullable();
            // As on `catalog_bulk_runs`: no foreign key — the frame does not know where the
            // administrators live — and the name beside the id for a worker with nobody logged in.
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('admin_name')->default('');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_exchange_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('catalog_exchange_runs')->cascadeOnDelete();
            $table->unsignedInteger('row');
            // Null: the row as a whole — a key nobody has, a product in the bin.
            $table->string('column', 80)->nullable();
            $table->text('value')->nullable();
            $table->string('message');

            $table->index(['run_id', 'row']);
        });

        Schema::create('catalog_exchange_run_items', function (Blueprint $table): void {
            $table->foreignId('run_id')->constrained('catalog_exchange_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->primary(['run_id', 'product_id']);
        });

        Schema::table('catalog_product_images', function (Blueprint $table): void {
            $table->string('source_hash', 40)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_product_images', function (Blueprint $table): void {
            $table->dropIndex(['source_hash']);
            $table->dropColumn('source_hash');
        });

        Schema::dropIfExists('catalog_exchange_run_items');
        Schema::dropIfExists('catalog_exchange_errors');
        Schema::dropIfExists('catalog_exchange_runs');
        Schema::dropIfExists('catalog_exchange_profiles');
    }
};
