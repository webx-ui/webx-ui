<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A review (§3 of the reviews spec): who wrote it, what they wrote, and how many stars.
 *
 * The job title is `job_title` rather than `position`, because `position` is the order column of
 * every module on the shared category code. The photo is the value of a `wx-media` field rather
 * than a key into `media_files`, which keeps this table on the first day (CLAUDE.md §4 on how the
 * migrations of all packages are sorted together).
 *
 * No draft and no history (decision 10): `published` is the whole of a review's life.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();

            // All three translatable; the text is plain text, printed with its line breaks.
            $table->json('name')->nullable();
            $table->json('job_title')->nullable();
            $table->json('text')->nullable();

            $table->unsignedTinyInteger('rating')->nullable();
            $table->date('reviewed_on')->nullable();
            $table->string('profile_url', 2048)->nullable();
            $table->json('photo')->nullable();

            $table->boolean('published')->default(false);

            // The order of the whole list. The place inside a category is on the link.
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
        Schema::dropIfExists('reviews');
    }
};
