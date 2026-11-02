<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the buyer chose for a cart (§5): one row a cart, gone with it. The chain of the cart reads
 * it to put the price of delivery into the total (§9.3); only the delivery step writes it — a read
 * of the cart still writes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_selections', static function (Blueprint $table): void {
            $table->foreignId('cart_id')->primary()->constrained('carts')->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->foreignId('locality_id')->nullable()->constrained('geo_localities')->nullOnDelete();
            $table->foreignId('method_id')->nullable()->constrained('delivery_methods')->nullOnDelete();
            $table->foreignId('point_id')->nullable()->constrained('delivery_points')->nullOnDelete();
            $table->json('address')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_selections');
    }
};
