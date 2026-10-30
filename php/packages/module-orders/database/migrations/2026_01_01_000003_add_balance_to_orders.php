<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The balance of an order (spec §36.1): the money that came in, and the stored copy of `due − over`
 * ({@see \WebxUi\Orders\Balance::stored()}) for the filter «payment differs» and its index. An order
 * marked paid before it was paid in full — nothing else could pay it; the rest got nothing yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('paid_total')->default(0);
            $table->bigInteger('balance')->default(0);
            $table->index(['balance', 'placed_at']);
        });

        DB::table('orders')->whereNotNull('paid_at')->update(['paid_total' => DB::raw('payable')]);

        // Signed before subtracting: MariaDB refuses an unsigned difference below zero.
        $owed = '(case when cancelled_at is null then cast(payable as signed) else 0 end)';
        $due = 'case when cancelled_at is null and payable > paid_total'
            .' then cast(payable as signed) - cast(paid_total as signed) else 0 end';
        $left = "cast(paid_total as signed) - cast(refunded_total as signed) - {$owed}";
        DB::table('orders')->update(['balance' => DB::raw(
            "({$due}) - (case when {$left} > 0 then {$left} else 0 end)"
        )]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['balance', 'placed_at']);
            $table->dropColumn(['paid_total', 'balance']);
        });
    }
};
