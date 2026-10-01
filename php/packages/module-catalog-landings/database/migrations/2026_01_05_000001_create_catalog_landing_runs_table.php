<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A generation of landings too large for one request (§8.3 of the landings spec): the rows the
 * preview found free, fixed when it started, how far the queue has got and what refused on the
 * way — the same shape as the core's bulk runs, which only know products.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_landing_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_name')->default('');
            $table->json('rows');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('done')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('errors')->nullable();
            $table->unsignedInteger('cursor')->default(0);
            $table->string('status', 16)->default('queued');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_landing_runs');
    }
};
