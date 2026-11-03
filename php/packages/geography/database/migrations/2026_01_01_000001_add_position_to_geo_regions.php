<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The regions of a country in the order an administrator drags them to — the selects and the
 * panel's list read them so. Alphabetical is the tie: until someone drags, every region is 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geo_regions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('position')->default(0)->after('code');
            $table->index(['country_code', 'position'], 'geo_regions_country_position_index');
        });
    }

    public function down(): void
    {
        Schema::table('geo_regions', function (Blueprint $table): void {
            $table->dropIndex('geo_regions_country_position_index');
            $table->dropColumn('position');
        });
    }
};
