<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A video of its own for phones and a poster of its own (§3 of the banners spec). Both empty on
 * every banner there is: a banner without them plays and looks as it did — the picture is the
 * poster, and a phone sees the video only where the place says `video_on_mobile`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->json('video_mobile')->nullable()->after('video');
            $table->json('poster')->nullable()->after('video_mobile');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->dropColumn(['video_mobile', 'poster']);
        });
    }
};
