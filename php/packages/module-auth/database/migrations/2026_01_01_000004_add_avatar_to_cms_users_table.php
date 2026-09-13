<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The administrator's photograph — stored the way every other picture on this system is, as
     * the library's key and nothing else.
     *
     * No foreign key and no reference to the media module: this package does not depend on that
     * one, and a panel installed without a library simply leaves the column empty.
     */
    public function up(): void
    {
        Schema::table('cms_users', function (Blueprint $table): void {
            $table->string('avatar')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('cms_users', function (Blueprint $table): void {
            $table->dropColumn('avatar');
        });
    }
};
