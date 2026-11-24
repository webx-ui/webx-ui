<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The watcher's last word (§14 of the spec): one row per watcher, rewritten on every beat.
        Schema::create('tasks_runner_beats', function (Blueprint $table): void {
            $table->id();
            $table->string('runner', 100)->unique();
            // What the watcher said: windows, pause, why it waits, counters, version.
            $table->json('payload');
            $table->timestamp('beat_at');
            // One alarm per silence: set when the person was told, cleared by the next beat.
            $table->timestamp('silent_notified_at')->nullable();
            $table->timestamps();
        });

        // A key for one stage (§14.4): which `mcp_keys` row it is, for which stage, as whom.
        // No constraints on `key_id`: `mcp_keys` belongs to another package, and the row has to
        // outlive a stage that is cancelled — the call log still points at the key.
        Schema::create('tasks_session_keys', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('key_id')->unique();
            $table->unsignedBigInteger('stage_id');
            $table->string('role', 16);
            $table->string('agent', 100);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->index(['stage_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks_session_keys');
        Schema::dropIfExists('tasks_runner_beats');
    }
};
