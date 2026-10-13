<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The layers of a banner (the layers spec, §3.1): texts, pictures and buttons placed on the
 * picture by hand, in percent of its width. One JSON list — a layer is read and written whole,
 * never queried by its parts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->json('layers')->nullable()->after('buttons');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->dropColumn('layers');
        });
    }
};
