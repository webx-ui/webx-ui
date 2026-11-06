<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For the condition "on sale" (§3.4 of the collections spec). An index cannot answer a comparison
 * of two columns, so the resolver asks `old_price is not null` as well: on 40 000 products with one
 * in ten on sale, MariaDB then reads 4 000 rows rather than all of them.
 *
 * Here and not in the core: nothing else asks this of the table, and a site without collections
 * should not pay for it on every write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_products', function (Blueprint $table): void {
            $table->index('old_price');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_products', function (Blueprint $table): void {
            $table->dropIndex(['old_price']);
        });
    }
};
