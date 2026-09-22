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
            // Light or dark, stored on the person so the panel looks the same on every
            // machine they sign in on. Null is an answer too — it means "whatever this
            // machine says", which is the one choice a column cannot spell out.
            $table->string('theme', 8)->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('cms_users', function (Blueprint $table): void {
            $table->dropColumn('theme');
        });
    }
};
