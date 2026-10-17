<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_history', function (Blueprint $table): void {
            // The journal of runs: `event = 'run' and subject_type in (...) order by id desc`,
            // and the pruner: `event = 'run' and created_at < ?`. The keys the table had start
            // with the subject or the date, so a journal of a busy site read every row to find
            // its runs.
            $table->index(['event', 'subject_type']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('cms_history', function (Blueprint $table): void {
            $table->dropIndex(['event', 'subject_type']);
            $table->dropIndex(['event', 'created_at']);
        });
    }
};
