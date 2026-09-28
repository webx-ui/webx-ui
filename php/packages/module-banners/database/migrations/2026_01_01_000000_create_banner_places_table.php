<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A place banners stand in (§3 of the banners spec).
 *
 * A declared place gets its row the first time a banner is saved into it, and nothing reads its
 * title: the configuration names it. A place of somebody's own is made in the panel and has only
 * this row, so its title is here, translated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_places', function (Blueprint $table): void {
            $table->id();

            // What a template asks for: `banners('hero')`.
            $table->string('key', 64)->unique();

            $table->json('title')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_places');
    }
};
