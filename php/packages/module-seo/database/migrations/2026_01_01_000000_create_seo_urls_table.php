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
        Schema::create('seo_urls', function (Blueprint $table): void {
            $table->id();

            $table->string('match_type', 8);
            // Long enough for an address nobody meant to write. Deliberately not indexed: a
            // column this wide cannot carry a portable index at all — MySQL stops at 191
            // characters of utf8mb4 — and nothing looks a rule up by its pattern. The public
            // side matches against the compiled list in the cache, and the panel searches with
            // `like '%…%'`, which no index would help either way.
            $table->string('pattern', 2048);
            $table->integer('priority')->default(0);

            // One value per language, `{"en": "…", "ru": "…"}` — see webx-ui/localization.
            Translatable::columns($table, 'title', 'h1', 'description', 'keywords', 'og_title', 'og_description');

            // What `wx-media` stores, not a language map: there are no per-language pictures
            // anywhere in the panel yet, and pretending otherwise would cost a data migration
            // the day somebody actually needs one.
            $table->json('og_image')->nullable();

            $table->string('canonical', 2048)->nullable();
            $table->string('robots', 255)->nullable();
            $table->json('json_ld')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // No unique index on `pattern` on purpose: two rules for one address are resolved
            // by `priority`, which is cheaper to explain than a refused save.
            $table->index(['is_active', 'match_type', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_urls');
    }
};
