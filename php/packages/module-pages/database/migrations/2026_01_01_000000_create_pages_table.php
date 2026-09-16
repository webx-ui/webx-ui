<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A page is a node of a tree, a title, a slug, a tree of blocks and a draft — and almost none
 * of those columns are written out here, because four other packages own them.
 *
 * What is not here is a unique index on the slug: uniqueness of an address belongs to the
 * `routes` registry, which is unique on `(locale, path)` and knows about every other kind of
 * entity too. A second copy of the rule in this table would be a copy that drifts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->nestedSet();

            // Translatable: an address is part of the content, and a page that has no slug in
            // a language simply has no address in it (§8).
            $table->json('title')->nullable();
            $table->json('slug')->nullable();

            $table->blocks();
            $table->draft();

            $table->softDeletes();

            // Which page's deletion put this one in the bin — its own id is never here, only
            // an ancestor's. Without it a restore would bring back everything below the node
            // that had been deleted earlier and separately (§7). No foreign key: the page it
            // names may be force-deleted out of the bin while this one is still in it.
            $table->unsignedBigInteger('trashed_with')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
