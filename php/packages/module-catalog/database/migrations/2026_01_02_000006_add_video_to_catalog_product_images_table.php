<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A video attached to a picture of the gallery (§2 of the video spec). The picture stays the row
 * and becomes the poster: lists, feeds and `og:image` need a picture, and a row without one does
 * not exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_product_images', function (Blueprint $table): void {
            // `file` or a provider's key (`youtube`); null — no video.
            $table->string('video_provider', 32)->nullable()->after('size');
            // A path on the gallery's disk, or the provider's id of the video.
            $table->string('video')->nullable()->after('video_provider');
            // Seconds, when known: a file's from the browser, a provider's never without its API.
            $table->unsignedInteger('video_duration')->nullable()->after('video');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_product_images', function (Blueprint $table): void {
            $table->dropColumn(['video_provider', 'video', 'video_duration']);
        });
    }
};
