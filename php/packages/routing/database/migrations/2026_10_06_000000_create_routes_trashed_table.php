<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The former addresses of whatever is in the bin, kept out of `routes` on purpose: there
        // they would hold their paths against everybody else and answer 301 into a page that is
        // gone. Here they hold nothing and answer nothing — they only remember, so that a
        // restore can bring the trail back.
        Schema::create('routes_trashed', function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 12);
            $table->string('path', 255);
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes_trashed');
    }
};
