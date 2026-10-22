<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The text of a label painted in a colour of its own (`color` = `#rrggbb` instead of a tone):
 * `#rrggbb`, or null for black or white by contrast. A label in a tone has none — the site's
 * class decides both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_labels', function (Blueprint $table): void {
            $table->string('text_color', 7)->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_labels', function (Blueprint $table): void {
            $table->dropColumn('text_color');
        });
    }
};
