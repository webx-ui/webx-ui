<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_products', function (Blueprint $table): void {
            // Every list of the storefront on the database engine: `is_published = 1 and
            // deleted_at is null and <category> order by priority desc, created_at desc, id desc`
            // (`SqlEngine::base()`, `CoreSort`). One key in that order lets the server walk the
            // live products in the page's order; the single-column keys on `is_published` and
            // `deleted_at` the table was created with each serve a fraction of the rows and no
            // sort. Four short columns — a boolean, two timestamps, an integer — so no key length
            // of MariaDB's is near.
            $table->index(['is_published', 'deleted_at', 'priority', 'created_at'], 'catalog_products_listing_index');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_products', function (Blueprint $table): void {
            $table->dropIndex('catalog_products_listing_index');
        });
    }
};
