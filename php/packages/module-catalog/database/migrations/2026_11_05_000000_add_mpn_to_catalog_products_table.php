<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The manufacturer's part number, beside the barcode. Seventy is the limit of the marketplaces'
 * feeds; not unique, since two suppliers may well sell one part under the same number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_products', function (Blueprint $table): void {
            $table->string('mpn', 70)->nullable()->index()->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_products', function (Blueprint $table): void {
            $table->dropIndex(['mpn']);
            $table->dropColumn('mpn');
        });
    }
};
