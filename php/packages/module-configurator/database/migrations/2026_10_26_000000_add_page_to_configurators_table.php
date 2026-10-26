<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the configurator stands on the site (§22 of the configurator spec): a link of `wx-link` —
 * the page with its block, chosen rather than typed. A product of the catalogue sends a visitor
 * there. Not drafted and not versioned: it is where the configurator is, not what it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurators', function (Blueprint $table): void {
            $table->json('page')->nullable()->after('form');
        });
    }

    public function down(): void
    {
        Schema::table('configurators', function (Blueprint $table): void {
            $table->dropColumn('page');
        });
    }
};
