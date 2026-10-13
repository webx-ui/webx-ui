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
            // Whether this person gets the panel on the site's own pages. On the person rather
            // than in a cookie, like the theme: a cookie is exactly what the bar must never leave
            // on a page, and somebody who turned it off should not find it back on a new laptop.
            $table->boolean('site_bar')->default(true)->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('cms_users', function (Blueprint $table): void {
            $table->dropColumn('site_bar');
        });
    }
};
