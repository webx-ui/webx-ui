<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A menu item may point at a file of the library — a price list, a brochure — like any other link
 * field of the panel.
 *
 * Two columns, spelled as the link value spells them: the file's key and whether the browser saves
 * it rather than opens it. The key is as long as `url`, because it is the same kind of string and
 * the link type checks both against one limit. No index: nothing looks an item up by its file —
 * the library finds where a file is used by reading text columns, this one among them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('path', 1024)->nullable()->after('url');
            $table->boolean('download')->default(false)->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn(['path', 'download']);
        });
    }
};
