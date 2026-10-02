<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('audit_runs')->cascadeOnDelete();
            $table->foreignId('from_page_id')->constrained('audit_pages')->cascadeOnDelete();

            $table->string('to_url', 2048);
            // The page the address is in the snapshot as; null for anybody else's, and for an
            // own address the crawl had no room left for.
            $table->unsignedBigInteger('to_page_id')->nullable();

            // a | img | srcset | script | link | iframe | form | style | meta | json_ld
            $table->string('kind', 16);
            $table->string('anchor', 255)->nullable();
            $table->string('rel', 64)->nullable();
            $table->string('target', 32)->nullable();

            $table->string('host', 255)->nullable();
            // own | own_mirror | dev | external
            $table->string('host_class', 16)->nullable();
            // Written with a scheme and host rather than as a path — `hosts.absolute_own`.
            $table->boolean('absolute')->default(false);
            // For the external ones, once they are checked (A3).
            $table->unsignedSmallInteger('status')->nullable();

            $table->timestamps();

            $table->index(['run_id', 'from_page_id']);
            $table->index(['run_id', 'to_page_id']);
            $table->index(['run_id', 'host_class']);
            $table->index(['run_id', 'host']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_links');
    }
};
