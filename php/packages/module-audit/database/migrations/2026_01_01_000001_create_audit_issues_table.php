<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('audit_runs')->cascadeOnDelete();

            $table->string('check', 64);
            // error | warning | notice
            $table->string('severity', 8);

            // Null for the checks of the host and of the database. No constraint: `audit_pages`
            // arrives with the crawler, and a finding outlives the snapshot it was found on.
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('url', 2048)->nullable();

            // A summary and a table for the expansion — data, not text (§4).
            $table->json('details')->nullable();

            // check + address + the key of the details: the same problem in the next run.
            $table->char('fingerprint', 40);
            // new | persisting; a fixed one is what the previous run had and this one has not.
            $table->string('state', 16)->default('new');
            // The `audit_ignores` rule that hides it, once there are rules (A5).
            $table->unsignedBigInteger('ignored_by')->nullable();

            $table->timestamps();

            $table->index(['run_id', 'check']);
            $table->index(['run_id', 'severity']);
            $table->index(['run_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_issues');
    }
};
