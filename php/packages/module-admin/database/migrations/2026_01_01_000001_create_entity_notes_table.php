<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What one administrator wrote down for the next one.
 *
 * A note belongs to whatever it is about — a submission, an order, a client — so it is a morph
 * rather than a column on any of them. `entity_type` holds the alias of the morph map and never
 * a class name: a class name in a database is a namespace somebody is not allowed to rename.
 *
 * `admin_id` carries no foreign key, exactly as `entity_versions.author_id` does not: this
 * package does not know which table administrators live in, and a deleted account must leave
 * the note standing anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_notes', function (Blueprint $table): void {
            $table->id();

            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');

            $table->unsignedBigInteger('admin_id')->nullable();

            $table->text('body');

            $table->timestamps();

            // The feed of one record, oldest first — which is what the index is read in.
            $table->index(['entity_type', 'entity_id', 'id'], 'entity_notes_entity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_notes');
    }
};
