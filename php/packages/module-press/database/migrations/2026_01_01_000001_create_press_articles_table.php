<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An article in an outlet (§3): what it was called, what kind it was, when, and where it is —
 * an address, a PDF from the library, or both.
 *
 * A table of its own rather than a list inside the outlet (decision 16): the feed of articles
 * across every outlet goes by date, and an agent edits one article without sending the rest.
 *
 * No `softDeletes`: an article lives and dies with its outlet. An outlet in the bin keeps its
 * articles, invisible because the outlet is; one deleted for good takes them with it.
 *
 * The column is `is_hidden`, not `hidden` — `$model->hidden` is Eloquent's own list of attributes
 * kept out of JSON, and reading it as a column is always an empty array (CLAUDE.md §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_articles', function (Blueprint $table): void {
            $table->id();

            // Its own table's key only, so the migration stays on the first day.
            $table->foreignId('outlet_id')->constrained('press_outlets')->cascadeOnDelete();

            $table->json('title')->nullable();
            $table->json('excerpt')->nullable();

            // A key of `webx-press.kinds`.
            $table->string('kind', 32)->nullable();

            // A day, and how much of it is known: `day`, `month` or `year` (decision 10). Five
            // characters is the longest of the three; the default is shorter than the column,
            // which MariaDB checks and sqlite does not (CLAUDE.md §4).
            $table->date('published_on')->nullable();
            $table->string('date_precision', 5)->default('day');

            $table->string('url', 2048)->nullable();
            $table->json('file')->nullable();

            $table->boolean('is_hidden')->default(false);

            // The order inside its outlet.
            $table->integer('position')->default(0);

            $table->json('extra')->nullable();

            $table->timestamps();

            $table->index(['outlet_id', 'position']);
            $table->index('published_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_articles');
    }
};
