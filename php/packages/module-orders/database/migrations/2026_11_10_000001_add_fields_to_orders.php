<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The snapshot of the extra fields the buyer filled (WEBX_UI_ORDER_FIELDS.md §2.2): the label and
 * the value of each, in the order of the checkout, in the order's language. Read only with the
 * order, so a json column beside `recipient`, `delivery` and `payment`, and no index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->json('fields')->nullable()->after('payment');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('fields');
        });
    }
};
