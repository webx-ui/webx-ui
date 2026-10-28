<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            // sha256 of the cookie's token: a leaked database opens nobody's cart.
            $table->char('token', 64)->unique();
            $table->string('locale', 12);
            $table->char('currency', 3);
            $table->json('declined_rules')->nullable();
            $table->timestamps();

            // What the prune walks.
            $table->index('updated_at');
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->string('type', 64);
            $table->unsignedBigInteger('ref_id');
            $table->json('options')->nullable();
            $table->unsignedInteger('qty');
            // A line of a bundle. The application hands the children their own qty before the
            // parent goes; the key keeps a delete that bypasses it from leaving them dangling.
            $table->foreignId('parent_id')->nullable()->constrained('cart_items')->nullOnDelete();
            $table->json('name_at_add');
            $table->unsignedBigInteger('price_at_add');
            $table->timestamp('added_at');

            // Named, so sqlite has them too: MariaDB would make them for the keys anyway.
            $table->index('cart_id');
            $table->index('parent_id');
            // "Which carts hold product A" and the lines of a provider that is gone.
            $table->index(['type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
