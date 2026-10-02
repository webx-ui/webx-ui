<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_content_urls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('audit_runs')->cascadeOnDelete();

            // The id of the content source: `pages`, `regions`, `blog.posts`.
            $table->string('source', 64);
            $table->string('record_id', 64);
            $table->string('record_label', 255)->nullable();
            $table->string('field', 128);
            $table->string('locale', 16)->nullable();

            $table->string('url', 2048);
            $table->string('host', 255);
            // own | own_mirror | dev | external
            $table->string('host_class', 16);

            // Whether the record is on the site: a stand address in a draft is the same mistake
            // one "Publish" later.
            $table->boolean('published')->default(false);
            // Where in the panel the record is edited, relative to the panel's base.
            $table->string('edit_url', 1024)->nullable();

            $table->timestamps();

            $table->index(['run_id', 'host_class']);
            $table->index(['run_id', 'host']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_content_urls');
    }
};
