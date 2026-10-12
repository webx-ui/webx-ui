<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table): void {
            // Which theme layer put the type on the site, and the hash of what it put (THEMES
            // §9.2). Empty source — a type of the site, or one a module offered: sync never
            // takes it over. A hash that no longer matches the published version — the type was
            // changed here since, and sync keeps the change.
            $table->string('theme_source', 191)->nullable()->after('former_slugs');
            $table->string('theme_hash', 64)->nullable()->after('theme_source');
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table): void {
            $table->dropColumn(['theme_source', 'theme_hash']);
        });
    }
};
