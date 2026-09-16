<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebxUi\Localization\Translatable;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table): void {
            $table->id();

            // Polymorphic rather than a column on every content table: what a page says about
            // itself is the same set of fields an article, a product and a category say, and
            // six copies of it are six migrations to keep in step. Written out instead of
            // `morphs()` so the type column has a length — an unbounded one cannot carry the
            // unique index below on MySQL.
            $table->string('seoable_type', 64);
            $table->unsignedBigInteger('seoable_id');

            // One value per language, `{"en": "…", "ru": "…"}` — see webx-ui/localization.
            Translatable::columns($table, 'title', 'h1', 'description', 'keywords', 'og_title', 'og_description');

            // What `wx-media` stores, not a language map — the same bargain the rules table
            // makes: there are no per-language pictures anywhere in the panel yet.
            $table->json('og_image')->nullable();

            $table->string('canonical', 2048)->nullable();
            $table->string('robots', 255)->nullable();
            $table->json('json_ld')->nullable();

            $table->timestamps();

            // One row per entity, and the database says so: the row is written through
            // `updateOrCreate`, and two of them would mean two answers to the same question.
            $table->unique(['seoable_type', 'seoable_id'], 'seo_meta_entity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
