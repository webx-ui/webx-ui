<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which categories an event is in, in which order — the first is the main one.
 *
 * `item_position` comes with the shared macro and stays unused (decision 4: events are ordered by
 * their date): the shared code writes it, and leaving it out for one module would mean branching
 * that code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_category_event', function (Blueprint $table): void {
            $table->categoryLinks('event', 'event_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_category_event');
    }
};
