<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editing the lines of an order (spec §37, §38): which edit the lines are at, when the last one
 * was, in which edit a line came and when it went — a line is never deleted, refunds and the
 * history point at it — and the edits themselves, «before — after», never changed afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedSmallInteger('items_revision')->default(0);
            $table->timestamp('items_changed_at')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('revision')->default(0);
            $table->timestamp('removed_at')->nullable();
        });

        Schema::create('order_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // orders.items_revision after this edit: 1, 2, …
            $table->unsignedSmallInteger('number');
            $table->json('changes');
            $table->json('adjustments');
            $table->json('before');
            $table->json('after');
            // For the managers only; the buyer reads `message`.
            $table->string('reason', 500)->nullable();
            $table->string('message', 1000)->nullable();
            $table->string('source', 64);
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['order_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_revisions');

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['revision', 'removed_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['items_revision', 'items_changed_at']);
        });
    }
};
