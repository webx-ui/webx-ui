<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many times the amount the buyer saw was not what the order came to (spec §30, §32): the
 * funnel's «where the price surprised them», kept by the draft through the order and pruned with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_checkout_drafts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('payable_changes')->default(0)->after('shown_payable');
        });
    }

    public function down(): void
    {
        Schema::table('order_checkout_drafts', function (Blueprint $table): void {
            $table->dropColumn('payable_changes');
        });
    }
};
