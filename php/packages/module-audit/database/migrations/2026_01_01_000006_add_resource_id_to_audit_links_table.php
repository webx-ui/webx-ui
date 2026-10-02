<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_links', function (Blueprint $table): void {
            // The answer of what the link loads or leads to elsewhere (`audit_resources`); null
            // for the site's own pages — they are in `audit_pages` — and past the stage's limit.
            $table->unsignedBigInteger('resource_id')->nullable()->after('to_page_id');
            $table->index(['run_id', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_links', function (Blueprint $table): void {
            $table->dropIndex(['run_id', 'resource_id']);
            $table->dropColumn('resource_id');
        });
    }
};
