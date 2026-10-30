<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A refund recorded by mistake is reversed, not edited or deleted (decision 98, §9.5): the row
 * stays in the history with when, who and why it was reversed, and its sums no longer count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_refunds', function (Blueprint $table): void {
            $table->timestamp('reversed_at')->nullable()->after('created_at');
            $table->string('reversed_source', 64)->nullable()->after('reversed_at');
            $table->unsignedBigInteger('reversed_by')->nullable()->after('reversed_source');
            $table->string('reversal_reason', 500)->nullable()->after('reversed_by');
        });
    }

    public function down(): void
    {
        Schema::table('order_refunds', function (Blueprint $table): void {
            $table->dropColumn(['reversed_at', 'reversed_source', 'reversed_by', 'reversal_reason']);
        });
    }
};
