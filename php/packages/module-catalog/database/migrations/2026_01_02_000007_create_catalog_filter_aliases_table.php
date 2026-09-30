<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The old spellings of the filter's addresses (§4.2 of the properties spec): a code or a value
 * slug that was renamed, merged or deleted, and what it leads to now — the new code, the value it
 * became, or nothing, which drops the segment. Kept by the core rather than by each satellite:
 * brands rename their slugs as well as properties do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_filter_aliases', function (Blueprint $table): void {
            $table->id();
            $table->string('facet_key', 64);
            $table->string('locale', 16);
            $table->string('kind', 8);
            $table->string('old', 191);
            $table->string('target', 191)->nullable();
            $table->timestamps();

            $table->index(['locale', 'kind', 'old']);
            $table->index(['facet_key', 'kind', 'target']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_filter_aliases');
    }
};
