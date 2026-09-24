<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records of one module pointing at records of another, or of its own: a recipe at the services
 * it belongs to, at the recipes like it (§3.2 of the recipes spec).
 *
 * One table for every pair, and no foreign keys in it, because the other end is somebody else's
 * module and may not be installed — now, or ever. A key into `services` cannot be created before
 * there are services, and services installed later would need a migration in the recipes module.
 * So the table depends on nothing, and sorting the migrations of all packages together
 * (CLAUDE.md §4) cannot put it before a table it points at.
 *
 * Both ends are named by their key in the relation registry (`recipe`, `service`), never by class:
 * a class gets renamed, the rows stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webx_relations', function (Blueprint $table): void {
            $table->id();

            $table->string('owner_type', 64);
            $table->unsignedBigInteger('owner_id');
            $table->string('role', 32);

            $table->string('target_type', 64);
            $table->unsignedBigInteger('target_id');

            // The order the editor put them in, which a template reads: the main service first.
            $table->integer('position')->default(0);

            $table->timestamps();

            // Named by hand: the generated names run past the 64 characters MySQL allows.
            $table->unique(['owner_type', 'owner_id', 'role', 'target_type', 'target_id'], 'webx_relations_link');
            $table->index(['target_type', 'target_id'], 'webx_relations_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webx_relations');
    }
};
