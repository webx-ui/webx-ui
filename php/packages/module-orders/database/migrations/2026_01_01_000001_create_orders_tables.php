<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The order and everything it froze at placing (spec §3): lines, adjustments, tenders, benefits,
 * the milestones, the refunds and the history of its statuses.
 *
 * Money is `bigint` in the minor units of `orders.currency`, never a float. Nothing here points at
 * a live record of another module — not the cart, not the visitor, not a product: they are cleaned
 * up, deleted or not installed, and the order is a document of the deal that outlives them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            // Written right after the insert, in the same transaction (§4); null only inside it.
            $table->string('number', 32)->nullable()->unique();
            // sha256 of the checkout's key: a double click does not make two orders.
            $table->char('idempotency_key', 64)->nullable()->unique();
            // A visitor of module-users, which may not be installed — so no foreign key.
            $table->unsignedBigInteger('user_id')->nullable();
            // For an investigation only: carts are pruned.
            $table->unsignedBigInteger('cart_id')->nullable();
            $table->string('locale', 12);
            $table->char('currency', 3);
            $table->foreignId('status_id')->constrained('order_statuses');
            $table->bigInteger('subtotal');
            $table->bigInteger('adjustments_total');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('tenders_total');
            $table->unsignedBigInteger('payable');
            $table->unsignedBigInteger('refunded_total')->default(0);
            // Apart, because a carrier's waybill needs them apart (decision 40).
            $table->string('customer_first_name', 190);
            $table->string('customer_last_name', 190)->nullable();
            $table->string('customer_phone', 32)->nullable();
            // Digits only: the phone found however it was written.
            $table->string('customer_phone_key', 20)->nullable();
            $table->string('customer_email', 190)->nullable();
            $table->json('recipient')->nullable();
            $table->text('comment')->nullable();
            $table->text('manager_note')->nullable();
            $table->json('delivery')->nullable();
            $table->json('payment')->nullable();
            // The milestones again, as the last word of `order_milestones`, so the filters of the
            // list need no subquery. Written by one method of `Orders`, never by hand.
            $table->timestamp('placed_at');
            $table->boolean('awaiting_payment')->default(false);
            $table->timestamp('payment_failed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            // The list by status; «awaiting payment»; «My orders»; search and linking to a
            // visitor; the period.
            $table->index(['status_id', 'placed_at']);
            $table->index(['awaiting_payment', 'placed_at']);
            $table->index(['user_id', 'placed_at']);
            $table->index('customer_email');
            $table->index('customer_phone_key');
            $table->index('placed_at');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // A line of a bundle, one level deep. Lines are never deleted, so a key that refuses
            // is the honest one.
            $table->foreignId('parent_id')->nullable()->constrained('order_items')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type', 64);
            $table->unsignedBigInteger('ref_id');
            $table->json('options')->nullable();
            $table->string('sku', 64)->nullable();
            $table->json('name');
            $table->string('options_label', 255)->nullable();
            $table->string('image', 255)->nullable();
            $table->string('source', 64)->nullable();
            $table->string('label', 64)->nullable();
            $table->unsignedBigInteger('price');
            // Absolute — a child's is already multiplied by its parent: the order is not
            // recalculated, and a waybill or a refund counts units.
            $table->unsignedInteger('qty');
            $table->bigInteger('adjustments_total');
            $table->bigInteger('total');
            $table->boolean('shippable');
            $table->unsignedInteger('refunded_qty')->default(0);

            // Named, so sqlite has them too: MariaDB would make them for the keys anyway.
            $table->index('order_id');
            $table->index('parent_id');
        });

        Schema::create('order_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // A discount of a line; null — of the order.
            $table->foreignId('item_id')->nullable()->constrained('order_items');
            $table->string('source', 64);
            $table->string('label', 190);
            $table->bigInteger('amount');
            $table->json('meta')->nullable();

            $table->index('order_id');
            $table->index('item_id');
        });

        Schema::create('order_tenders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('source', 64);
            $table->string('label', 190);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('refunded_amount')->default(0);
            // The wallet's batches: what it gives back the spent from.
            $table->json('meta')->nullable();

            $table->index('order_id');
        });

        Schema::create('order_benefits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label', 190);
            $table->string('source', 64);

            // Leads with the order, so it serves the order's benefits too.
            $table->unique(['order_id', 'key']);
        });

        Schema::create('order_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('milestone', 24);
            // When it happened: a tracker gives the carrier's time, not the time it was asked.
            $table->timestamp('at');
            $table->string('source', 64);
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('reference', 190)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            // Each milestone once (decision 24) — and the milestones of an order.
            $table->unique(['order_id', 'milestone']);
        });

        Schema::create('order_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->json('tenders');
            $table->json('items');
            // `manual` — the money went back outside the site; the only one in v1 (§9.4).
            $table->string('method', 16);
            $table->string('reference', 190)->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('source', 64);
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('order_id');
        });

        Schema::create('order_status_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('kind', 32);
            // No keys to the statuses: a status deleted for good by hand in the database must not
            // take the history with it.
            $table->unsignedBigInteger('from_status_id')->nullable();
            $table->unsignedBigInteger('to_status_id')->nullable();
            $table->unsignedBigInteger('from_public_id')->nullable();
            $table->unsignedBigInteger('to_public_id')->nullable();
            $table->string('source', 64);
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamp('created_at')->nullable();

            // The timeline of an order, in the order it was written.
            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_changes');
        Schema::dropIfExists('order_refunds');
        Schema::dropIfExists('order_milestones');
        Schema::dropIfExists('order_benefits');
        Schema::dropIfExists('order_tenders');
        Schema::dropIfExists('order_adjustments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
