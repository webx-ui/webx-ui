<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table): void {
            $table->id();

            // The `type` every stored block names. Renaming it is a breaking change for every
            // entity that holds one, which is why the panel does not offer to.
            $table->string('slug', 64)->unique();
            $table->string('title', 120);
            $table->string('description', 255)->nullable();
            $table->string('icon', 64)->nullable();

            // One of `webx-blocks.groups`. Quoted by the grammar everywhere Eloquent touches it;
            // a raw query has to quote it by hand, because GROUP is a keyword.
            $table->string('group', 64);

            // The order of the list and — the reason it is here at all — the order of the CSS
            // cascade when the bundle is glued together.
            $table->smallInteger('sort')->default(0);

            // Which types may go inside; null means the block is not a container at all.
            $table->json('allow')->nullable();
            // Where the block itself may be placed; null means anywhere.
            $table->json('allowed_in')->nullable();
            $table->unsignedSmallInteger('max_per_entity')->nullable();

            // Hidden from editors, still rendered wherever it already stands.
            $table->boolean('is_enabled')->default(true);

            // Pointers into block_versions, kept in step by the model rather than by the
            // database: the two tables point at each other, and a circular constraint costs a
            // driver-specific alter in each direction for nothing the model does not already
            // guarantee. `draft` is what is being edited and not yet live — null once it is
            // published; `published` is what the site prints.
            $table->unsignedBigInteger('draft_version_id')->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();

            $table->timestamps();

            $table->index(['is_enabled', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
