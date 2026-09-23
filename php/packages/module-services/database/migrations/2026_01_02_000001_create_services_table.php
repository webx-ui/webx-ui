<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A service: a title, a slug, an announcement, a cover, a tree of blocks and a draft (§4.2).
 *
 * `position` is the order of the whole list — what the index falls back on and what a service
 * newly filed into a category takes its place there by. Its place inside each category is on the
 * link, not here.
 *
 * No unique index on the slug: an address is unique in `routes`, which knows about categories and
 * pages too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();

            $table->json('title')->nullable();
            $table->json('slug')->nullable();

            // The announcement on a card and the fallback description: text, no markup.
            $table->json('lead')->nullable();

            $table->foreignId('cover_id')->nullable()->constrained('media_files')->nullOnDelete();

            $table->integer('position')->default(0);

            // The fields a project patched onto the editor (`HasExtra`).
            $table->json('extra')->nullable();

            $table->blocks();
            $table->draft();

            $table->softDeletes();
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
