<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * The streets of carriers by their localities (§5, §8): the third directory, for a carrier's
 * courier only. Hundreds of thousands of narrow rows a carrier; written by the sync alone — a
 * street the buyer typed for the shop's courier never comes here (decision 31).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_streets', function (Blueprint $table): void {
            $table->id();
            $table->string('carrier', 32);
            $table->foreignId('carrier_locality_id')->constrained('delivery_carrier_localities')->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->string('kind', 16)->default('street');
            $table->json('names');
            $this->binary($table->string('name_key', 191));
            $table->foreignId('seen_run_id')->nullable()->constrained('delivery_sync_runs')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            // The sync's `upsert` (§7.3).
            $table->unique(['carrier_locality_id', 'external_id']);
            // The suggestions: a prefix inside one locality among the streets in force, in order —
            // and the count of a locality's streets after a pass, from the index alone (§8.1).
            $table->index(['carrier_locality_id', 'removed_at', 'name_key']);
            // Which streets of a page are there already: by the carrier's ids, whatever their locality.
            $table->index(['carrier', 'external_id']);
            // The removal of what a pass did not see (§7.5).
            $table->index(['carrier', 'seen_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_streets');
    }

    /**
     * A normalised key compares byte for byte, as the geography's do: under a `_ci` collation a
     * letter with and without its diacritic would be one prefix (§4.2).
     */
    private function binary(ColumnDefinition $column): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $column->collation('utf8mb4_bin');
        }
    }
};
