<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The shop's own two methods, switched off (spec §5, §12): payment on receipt — offered at the
 * shop's point and for cash on delivery — and bank transfer. No title of their own: the dictionary's
 * reads in every language until the shop writes one. The shop switches them on.
 */
return new class extends Migration
{
    private const array METHODS = [
        ['key' => 'on-receipt', 'type' => 'store.on_receipt', 'position' => 10, 'rule' => '{"mode":"on_hand"}'],
        ['key' => 'bank-transfer', 'type' => 'store.bank_transfer', 'position' => 20, 'rule' => '{"mode":"any"}'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::METHODS as $method) {
            if (DB::table('payment_methods')->where('key', $method['key'])->exists()) {
                continue;
            }

            DB::table('payment_methods')->insert([
                'key' => $method['key'],
                'type' => $method['type'],
                'title' => '{}',
                'enabled' => false,
                'position' => $method['position'],
                'delivery_rule' => $method['rule'],
                'settings' => '{}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('payment_methods')->whereIn('key', array_column(self::METHODS, 'key'))->delete();
    }
};
