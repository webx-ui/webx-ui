<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An exact rule remembers the entity its address belonged to when it was saved (§18.2), so a
 * renamed category keeps its rule. Null on masks, regular expressions and addresses with a query,
 * and on any address the registry does not know.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_urls', function (Blueprint $table): void {
            $table->string('entity_type')->nullable()->after('pattern');
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table): void {
            $table->dropIndex(['entity_type', 'entity_id']);
            $table->dropColumn(['entity_type', 'entity_id']);
        });
    }
};
