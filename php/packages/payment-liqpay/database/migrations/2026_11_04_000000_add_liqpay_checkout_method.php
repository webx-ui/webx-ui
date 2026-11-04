<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;

/**
 * The method of the gateway, switched off (spec §4): the shop enters its keys and turns it on. No
 * title of its own, the type's word reads until the shop writes one. A method already there under
 * its key is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('payment_methods')->where('key', LiqPayCheckoutType::METHOD)->exists()) {
            return;
        }

        $now = now();

        DB::table('payment_methods')->insert([
            'key' => LiqPayCheckoutType::METHOD,
            'type' => LiqPayCheckoutType::KEY,
            'title' => '{}',
            'enabled' => false,
            'position' => (int) DB::table('payment_methods')->max('position') + 1,
            'delivery_rule' => '{"mode":"any"}',
            'settings' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** An attempt is the trace of money: a method that has one stays. */
    public function down(): void
    {
        if (DB::table('payments')->where('method_key', LiqPayCheckoutType::METHOD)->exists()) {
            return;
        }

        DB::table('payment_methods')->where('key', LiqPayCheckoutType::METHOD)->delete();
    }
};
