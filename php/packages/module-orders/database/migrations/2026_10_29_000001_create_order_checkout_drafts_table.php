<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The checkout's draft (spec §21.1): what the buyer typed on the way to an order, one per cart and
 * gone with it. Made whether the checkout is on or not, so switching it on needs no migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_checkout_drafts', function (Blueprint $table): void {
            $table->foreignId('cart_id')->primary()->constrained('carts')->cascadeOnDelete();
            $table->json('customer')->nullable();
            $table->json('recipient')->nullable();
            $table->text('comment')->nullable();
            $table->json('steps')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 64)->nullable();
            $table->char('place_key', 64)->nullable();
            $table->unsignedBigInteger('shown_payable')->nullable();
            $table->string('last_step', 32)->nullable();
            $table->timestamps();

            // The prune and the funnel read by the last touch (§21.3, §34).
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_checkout_drafts');
    }
};
