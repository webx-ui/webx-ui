<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('audit_runs')->cascadeOnDelete();

            // The address
            $table->string('url', 2048);
            // sha1 of the address: the address itself is too long for a unique index on MySQL.
            $table->char('url_hash', 40);
            // home | sitemap | registry | link | probe — how the crawl first heard of it.
            $table->string('source', 16);
            // Clicks from the home page; null for an address no link from the home page reaches.
            $table->unsignedSmallInteger('depth')->nullable();
            // Null until the page has been asked: the queue of the crawl is these rows.
            $table->timestamp('fetched_at')->nullable();
            // Where the chain of redirects starting here ends, once the crawl is over.
            $table->unsignedSmallInteger('final_status')->nullable();
            $table->string('redirect_to', 2048)->nullable();

            // The answer
            $table->unsignedSmallInteger('status')->nullable();
            // Nothing answered at all: no DNS, refused, timed out.
            $table->string('error', 255)->nullable();
            $table->string('content_type', 128)->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->unsignedInteger('ttfb_ms')->nullable();
            $table->unsignedInteger('total_ms')->nullable();
            $table->string('compression', 16)->nullable();
            // A selection of the headers, not all of them.
            $table->json('headers')->nullable();
            $table->boolean('blocked_by_robots')->default(false);
            // 200, HTML, no noindex, no canonical pointing elsewhere — what the duplicate checks
            // and the filters mean by a page that search engines are meant to have.
            $table->boolean('indexable')->default(false);

            // The markup
            $table->text('title')->nullable();
            $table->text('description')->nullable();
            $table->json('h1')->nullable();
            $table->json('headings')->nullable();
            $table->string('canonical', 2048)->nullable();
            $table->string('robots_meta', 255)->nullable();
            $table->string('x_robots_tag', 255)->nullable();

            // Languages, social networks, structured data
            $table->string('lang', 32)->nullable();
            $table->json('hreflang')->nullable();
            $table->json('og')->nullable();
            $table->json('twitter')->nullable();
            $table->json('json_ld')->nullable();

            // The text
            $table->unsignedInteger('word_count')->nullable();
            $table->char('text_hash', 40)->nullable();

            // The links
            $table->unsignedInteger('links_in')->default(0);
            $table->unsignedInteger('links_out_internal')->default(0);
            $table->unsignedInteger('links_out_external')->default(0);
            $table->unsignedInteger('images')->default(0);
            $table->unsignedInteger('images_without_alt')->default(0);
            $table->boolean('in_sitemap')->default(false);
            $table->boolean('in_registry')->default(false);

            // What the page checks need beyond the snapshot of §4: how many titles and
            // canonicals, the viewport, the icon, the share of text, unnamed buttons and fields.
            $table->json('facts')->nullable();

            $table->timestamps();

            $table->unique(['run_id', 'url_hash']);
            $table->index(['run_id', 'fetched_at', 'depth']);
            $table->index(['run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_pages');
    }
};
