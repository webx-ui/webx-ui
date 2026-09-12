<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_users', function (Blueprint $table): void {
            // The language this person reads the panel in — theirs, not the site's. Null
            // means they have not chosen, and get whatever the site considers its default.
            $table->string('locale', 16)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('cms_users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
