<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A configurator (§4 of the configurator spec): one row, the whole of it in one JSON document.
 *
 * `schema` is what the site prices by, `draft` what is being prepared — the frame's drafts and
 * versions copy columns, and a document in a column is copied whole (§3). `key` is what a block,
 * a template and an agent name it by, and is never changed after the first publication: blocks of
 * a site keep it. `form` is the slug of the inbox form a request goes to — a link to another
 * module, so a column rather than a key inside the document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurators', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();

            // Translatable: the name in the panel and the default heading over the block.
            $table->json('title')->nullable();

            // Null until the first publication.
            $table->json('schema')->nullable();
            $table->string('form', 64)->nullable();

            $table->json('draft')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurators');
    }
};
