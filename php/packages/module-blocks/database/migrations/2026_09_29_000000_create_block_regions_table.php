<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_regions', function (Blueprint $table): void {
            $table->id();
            // The key from `webx-blocks.regions`, which is what the layout's tag and an agent call
            // the region by. No title column: the words come from the config, the way a declared
            // menu's do, so a region is renamed where it is declared.
            $table->string('name', 64)->unique();
            $table->blocks();
            // `draft` and `published_at`: null published_at is what prints the fallback.
            $table->draft();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_regions');
    }
};
