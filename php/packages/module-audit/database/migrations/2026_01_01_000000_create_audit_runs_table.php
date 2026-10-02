<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_runs', function (Blueprint $table): void {
            $table->id();

            // queued | running | done | failed | cancelled — `cancelled` is the longest, nine.
            $table->string('status', 16)->default('queued');
            // full | quick | urls
            $table->string('scope', 16);

            $table->string('base_url', 2048);
            $table->string('resolve_to', 255)->nullable();

            $table->unsignedInteger('pages_limit')->default(0);
            $table->unsignedInteger('pages_crawled')->default(0);

            // The stage and its counters, polled by the panel while the run goes.
            $table->json('progress')->nullable();
            // By severity and by group, the health, and the checks that ran.
            $table->json('counts')->nullable();
            // What the probes saw, without the bodies: the host's own facts, for the overview.
            $table->json('probes')->nullable();

            // An administrator's id, `schedule`, `console` or `mcp`.
            $table->string('started_by', 32)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_runs');
    }
};
