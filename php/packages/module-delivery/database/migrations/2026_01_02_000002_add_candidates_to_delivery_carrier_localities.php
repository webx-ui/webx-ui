<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the queue of bindings offers a person (§4.3, §5): the namesakes with their distances,
 * nearest first, as the matcher found them on the last pass of the sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_carrier_localities', static function (Blueprint $table): void {
            $table->json('candidates')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_carrier_localities', static function (Blueprint $table): void {
            $table->dropColumn('candidates');
        });
    }
};
