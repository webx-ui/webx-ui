<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table): void {
            $table->id();

            // The language this address is published in. The path itself never carries the
            // language prefix: routes are declared inside `{locale?}`, so what reaches the
            // resolver is already the tail.
            $table->string('locale', 12);

            // 255 rather than something roomier, and that is the whole point of the table: a
            // wider column cannot carry a portable unique index, and MySQL stops at 3072 bytes
            // of DYNAMIC key prefix — 255 characters of utf8mb4 plus the locale fit. An address
            // longer than this fails validation when it is saved rather than being truncated
            // into somebody else's address.
            //
            // Stored without a leading slash, lower case: '' is the site root. Two spellings of
            // one address would otherwise be two rows in the map and one row in the database.
            $table->string('path', 255);

            $table->string('kind', 8)->default('canonical');

            // An alias points at the row, not at its text: after a second rename it still leads
            // to wherever the canonical row is now, with no chain of 301s to walk.
            $table->unsignedBigInteger('target_id')->nullable();

            // A morph alias, never a class name: a class name in the database breaks on the
            // first move between namespaces. `RouteTypes::register()` enforces the map.
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');

            $table->timestamps();

            $table->unique(['locale', 'path']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('target_id');

            $table->foreign('target_id')->references('id')->on('routes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
