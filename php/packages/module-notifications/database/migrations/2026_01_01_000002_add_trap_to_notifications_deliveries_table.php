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
            // What took a sent letter and delivered it to nobody — the `log` mailer, a test
            // sandbox's host — or null for a real way out. Written when it was sent: the
            // settings may be different by the time somebody reads the log.
            $table->string('trap')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('notifications_deliveries', function (Blueprint $table): void {
            $table->dropColumn('trap');
        });
    }
};
