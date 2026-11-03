<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A point open to some people only (§7.2): a parcel locker in a block of flats for those who live
 * there is still the one they pick, so it is kept and said, and the list puts it after the others.
 * A code, not a flag: `residents`, or another word a carrier has — the base says the known ones in
 * its dictionary and the rest as «limited access».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_points', static function (Blueprint $table): void {
            $table->string('restriction', 32)->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_points', static function (Blueprint $table): void {
            $table->dropColumn('restriction');
        });
    }
};
