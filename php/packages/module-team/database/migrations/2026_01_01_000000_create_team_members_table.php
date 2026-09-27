<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person of the team (§3 of the team spec).
 *
 * `team_members` rather than `teams`: the team is the section, a row is one person in it. The job
 * title is `job_title` rather than `position`, because `position` is the order column of all the
 * shared ordering code. The photo is the value of a `wx-media` field rather than a key into
 * `media_files`, which keeps this table on the first day (CLAUDE.md §4 on how the migrations of
 * all packages are sorted together). The services a person provides live in `webx_relations`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table): void {
            $table->id();

            // All three translatable; the text is plain text, printed with its line breaks.
            $table->json('name')->nullable();
            $table->json('job_title')->nullable();
            $table->json('text')->nullable();

            $table->json('photo')->nullable();

            // [{ "network": "instagram", "url": "https://…" }], in the editor's order: five links
            // read with the person every time are a column, not a table.
            $table->json('socials')->nullable();

            $table->boolean('published')->default(false);

            // The one order there is: no categories, so no second one.
            $table->integer('position')->default(0);

            // The fields a project patched onto the editor (`HasExtra`).
            $table->json('extra')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
