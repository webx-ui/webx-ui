<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interlinking (§18.4): a donor address and the list of links it prints.
 *
 * Migrated whether or not `webx-seo.links.enabled` is on, so turning the feature off hides the
 * data rather than losing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_link_blocks', function (Blueprint $table): void {
            $table->id();

            // The donor: the language from the address prefix, the path without it. One address
            // in one language — `/ru/catalog/…` and `/uk/catalog/…` are two blocks, which is why
            // the heading and anchors are plain strings rather than translations.
            $table->string('locale', 8);
            // Not indexed for the same reason as `seo_urls.pattern`: too wide for a portable
            // index. A page with an entity is found by the entity, which is indexed.
            $table->string('path', 2048);
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();

            $table->string('heading', 255)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['locale', 'entity_type', 'entity_id']);
        });

        Schema::create('seo_link_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('block_id')->constrained('seo_link_blocks')->cascadeOnDelete();

            // The acceptor, bound the same way as the donor. Its own language, because an
            // address with a prefix of another language is still an address on this site.
            $table->string('locale', 8);
            $table->string('path', 2048);
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();

            $table->string('anchor', 255);
            $table->integer('position')->default(0);

            $table->index(['block_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_link_items');
        Schema::dropIfExists('seo_link_blocks');
    }
};
