<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A banner (§3 of the banners spec): pictures, words and buttons, in one place.
 *
 * The media are values of `wx-media` fields — a library key and captions — rather than keys into
 * `media_files`, which keeps this table on the first day (CLAUDE.md §4 on how the migrations of all
 * packages are sorted together). The picture is required by the form, not by the column: a file
 * deleted from the library leaves the row alone, and the site simply does not print it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
            $table->id();

            // A safety net rather than a mechanism: only an empty place can be deleted.
            $table->foreignId('place_id')->constrained('banner_places')->cascadeOnDelete();

            $table->json('image')->nullable();
            $table->json('image_mobile')->nullable();
            $table->json('video')->nullable();

            // Translated; the text is plain text, printed with its line breaks.
            $table->json('title')->nullable();
            $table->json('text')->nullable();

            // [{ "label": {"en": "…"}, "link": {Link}, "variant": "primary" }], in the editor's
            // order: three buttons read with the banner every time are a column, not a table.
            $table->json('buttons')->nullable();

            // Off until somebody turns it on: the first save of an unfinished banner must not be a
            // banner on the home page.
            $table->boolean('enabled')->default(false);

            // The order inside the place.
            $table->integer('position')->default(0);

            // The fields a project patched onto the editor (`HasExtra`).
            $table->json('extra')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['place_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
