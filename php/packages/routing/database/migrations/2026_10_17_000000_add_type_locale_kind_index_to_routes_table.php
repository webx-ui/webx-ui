<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table): void {
            // The sitemap walks one type at a time: `entity_type = ? and kind = ? and locale = ?
            // and id > ? order by id` five hundred rows a chunk. `(entity_type, entity_id)` serves
            // one entity's rows, not a type's; on this key InnoDB appends the primary key, so the
            // chunk is read in order without a sort.
            $table->index(['entity_type', 'locale', 'kind'], 'routes_type_locale_kind_index');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table): void {
            $table->dropIndex('routes_type_locale_kind_index');
        });
    }
};
