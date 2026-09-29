<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who changed what, one row per save (WEBX_UI_HISTORY.md §3).
 *
 * `subject_type` is the type a module registered (`catalog.product`) and never a class name, for
 * the reason `entity_notes.entity_type` is an alias: a class in a column is a namespace nobody
 * may rename.
 *
 * `admin_id` carries no foreign key, like `entity_notes.admin_id`: this package does not know
 * which table administrators live in, and the name beside it is a snapshot precisely so that a
 * deleted account leaves the journal readable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_history', function (Blueprint $table): void {
            $table->id();

            // The run (an import, a bulk action) this row was written inside. A run is itself a
            // row of this table, with `event = run` and no subject id.
            $table->foreignId('parent_id')->nullable()->constrained('admin_history')->nullOnDelete();

            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('event', 32);
            $table->string('source', 16);

            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_name')->default('');

            // The connection an agent came in through, when the source is `mcp` — the same id
            // `mcp_calls.grant_id` holds, so the two logs can be read side by side.
            $table->unsignedBigInteger('grant_id')->nullable();

            $table->json('changes')->nullable();
            $table->json('summary')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id', 'created_at'], 'admin_history_subject');
            $table->index('created_at');
            // The rows of one run. MySQL would make this for the key anyway; Postgres would not.
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_history');
    }
};
