<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An outlet (§3 of the press spec): a magazine, a paper, a portal — the logo, the name, a few
 * words about it and where it lives.
 *
 * The logo is the value of a `wx-media` field rather than a key into `media_files`, which keeps
 * this table on the first day (CLAUDE.md §4 on how the migrations of all packages are sorted
 * together). No draft and no history (decision 9): `published` is the whole of an outlet's life.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_outlets', function (Blueprint $table): void {
            $table->id();

            // All three translatable. The slug is not needed when outlets have no pages.
            $table->json('title')->nullable();
            $table->json('slug')->nullable();
            $table->json('summary')->nullable();

            $table->json('logo')->nullable();
            $table->string('website_url', 2048)->nullable();

            // In the strip of logos when the block asks for the marked ones only (decision 13).
            $table->boolean('featured')->default(false);
            $table->boolean('published')->default(false);

            // The order of the whole list, dragged in the panel (decision 6).
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
        Schema::dropIfExists('press_outlets');
    }
};
