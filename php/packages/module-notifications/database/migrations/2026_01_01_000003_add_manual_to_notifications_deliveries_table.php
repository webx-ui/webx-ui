<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications_deliveries', function (Blueprint $table): void {
            // Sent by hand (§8.6): a person asked for it, not an event — «the buyer says the letter
            // never came». Who asked, an administrator's id, and the row it repeats, if it repeats one.
            $table->boolean('manual')->default(false)->after('is_test');
            $table->unsignedBigInteger('manual_by')->nullable()->after('manual');
            $table->unsignedBigInteger('resent_from')->nullable()->after('manual_by')->index();
        });
    }

    public function down(): void
    {
        Schema::table('notifications_deliveries', function (Blueprint $table): void {
            $table->dropIndex(['resent_from']);
            $table->dropColumn(['manual', 'manual_by', 'resent_from']);
        });
    }
};
