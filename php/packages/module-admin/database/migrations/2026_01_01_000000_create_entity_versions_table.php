<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('versionable_type', 64);
            $table->unsignedBigInteger('versionable_id');
            // Publications are numbered 1, 2, 3… within an entity; an autosave has no number,
            // because it is not a point in the entity's history, only a copy of its draft.
            $table->unsignedInteger('number')->nullable();
            $table->string('kind', 16);
            $table->json('payload');
            $table->boolean('is_pinned')->default(false);
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('source', 16)->default('panel');
            $table->string('comment', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['versionable_type', 'versionable_id', 'number'], 'entity_versions_entity_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_versions');
    }
};
