<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The popular towns of the checkout's «Where to deliver?» dialog (decision 40): one list for the
 * whole site, in the order the administrator dragged it into. A locality appears in it once; one
 * that is deleted leaves the list with it, so the list never points at nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_popular_localities', static function (Blueprint $table): void {
            $table->foreignId('locality_id')->primary()->constrained('geo_localities')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_popular_localities');
    }
};
