<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stage 5 of a run (§3): every picture, stylesheet, script and external link the pages
        // point at, asked once per run however many pages share it.
        Schema::create('audit_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('audit_runs')->cascadeOnDelete();

            $table->string('url', 2048);
            $table->string('url_hash', 40);
            // image | og | icon | css | js | other | page (somebody else's page a link leads to)
            $table->string('kind', 8);
            // own | own_mirror | external
            $table->string('host_class', 16)->nullable();

            // A row without it is still to ask: the queue of the stage is the table itself.
            $table->timestamp('checked_at')->nullable();
            $table->string('method', 4)->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('error', 255)->nullable();
            $table->string('location', 2048)->nullable();
            $table->string('content_type', 128)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->string('cache_control', 255)->nullable();
            $table->string('compression', 16)->nullable();
            // Of an Open Graph picture, read from its bytes.
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('total_ms')->nullable();

            $table->timestamps();

            $table->unique(['run_id', 'url_hash']);
            $table->index(['run_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_resources');
    }
};
