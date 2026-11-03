<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The methods of the site, the buyer's choice for a cart and the attempts to pay (spec §5).
 * Money is an integer in minor units everywhere; there is no float.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            // The site's key of a method, for ever: an order's block and attempts keep it.
            $table->string('key', 64)->unique();
            $table->string('type', 64)->index();
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('icon', 255)->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('delivery_rule');
            $table->unsignedBigInteger('min_amount')->nullable();
            $table->unsignedBigInteger('max_amount')->nullable();
            $table->json('settings');
            // A gateway's keys, encrypted with the site's APP_KEY (§15.1); null — none, or read
            // from the environment.
            $table->text('credentials')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_selections', function (Blueprint $table): void {
            $table->id();
            // No foreign key: carts are cleaned up, and the choice goes with them by a listener.
            $table->unsignedBigInteger('cart_id')->unique();
            $table->string('method_key', 64);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            // A ULID: the only id that travels through a gateway (§4).
            $table->char('uuid', 26)->unique();
            // An attempt is the trace of money: the order cannot go while it has one.
            $table->foreignId('order_id')->index()->constrained('orders')->restrictOnDelete();
            $table->string('method_key', 64);
            $table->string('type', 64);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 16);
            $table->string('problem', 24)->nullable()->index();
            $table->string('gateway_ref', 190)->nullable();
            $table->unsignedBigInteger('paid_amount')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('hold')->default(false);
            $table->unsignedBigInteger('authorized_amount')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('hold_expires_at')->nullable()->index();
            $table->unsignedBigInteger('capture_amount')->nullable();
            $table->unsignedBigInteger('refunded_amount')->nullable();
            $table->string('source', 64);
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // The poll of unfinished attempts (§9.3) and the list by status.
            $table->index(['status', 'created_at']);
            // A notification finds its attempt by the gateway's id, and one payment of a gateway
            // never belongs to two attempts.
            $table->unique(['type', 'gateway_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_selections');
        Schema::dropIfExists('payment_methods');
    }
};
