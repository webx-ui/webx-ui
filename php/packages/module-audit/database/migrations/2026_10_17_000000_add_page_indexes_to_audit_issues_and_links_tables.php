<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_issues', function (Blueprint $table): void {
            // The list of pages counts each page's open issues in a correlated subquery:
            // `page_id = ? and ignored_by is null`, once per row of the list. The keys the
            // table had all start with `run_id`, which that subquery does not name.
            $table->index(['page_id', 'ignored_by']);
        });

        Schema::table('audit_links', function (Blueprint $table): void {
            // A page's card counts what links to it across every run (`to_page_id = ?`), and
            // the hosts' card asks when a host was first seen (`host in (...) group by host`
            // joined to the runs) — neither names `run_id` first, as every key here did.
            $table->index('to_page_id');
            $table->index(['host', 'run_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_issues', function (Blueprint $table): void {
            $table->dropIndex(['page_id', 'ignored_by']);
        });

        Schema::table('audit_links', function (Blueprint $table): void {
            $table->dropIndex(['to_page_id']);
            $table->dropIndex(['host', 'run_id']);
        });
    }
};
