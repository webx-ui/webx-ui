<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The parcels of orders (§5, §11): a waybill at a carrier, who pays it, where the parcel is now.
 * An order is `module-orders`' own, which a shop may not have: the key on `orders` is made only
 * where the table is — without orders there are no shipments, and the table stays empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orders = Schema::hasTable('orders');

        Schema::create('delivery_shipments', static function (Blueprint $table) use ($orders): void {
            $table->id();
            $order = $table->unsignedBigInteger('order_id');

            if ($orders) {
                $order->index();
                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            } else {
                $table->index('order_id');
            }

            $table->string('carrier', 32);
            // The method type that made the waybill: it is asked for the label, the cancellation
            // and the tracking, whatever became of the method since.
            $table->string('type', 64);
            $table->string('method_key', 64);
            $table->string('number', 64)->nullable();
            $table->string('payer', 12);
            $table->integer('cost')->nullable();
            $table->string('status', 16);
            $table->timestamp('status_at')->nullable();
            $table->boolean('final')->default(false);
            $table->timestamp('next_check_at')->nullable();
            $table->json('events');
            $table->json('meta');
            $table->timestamps();

            // What the tracking asks about (§11.3): not final, due now.
            $table->index(['final', 'next_check_at']);
            $table->unique(['carrier', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_shipments');
    }
};
