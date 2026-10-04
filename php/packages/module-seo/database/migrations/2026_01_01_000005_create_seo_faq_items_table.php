<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The FAQ of a page (§18.5): questions and answers on an exact rule, beside its meta tags.
 *
 * Migrated whether or not `webx-seo.faq.enabled` is on, so turning the feature off hides the
 * questions rather than losing them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_faq_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seo_url_id')->constrained('seo_urls')->cascadeOnDelete();

            // Per language, like the fields of the rule: an exact address without a language
            // prefix is every language of the site that has no prefix of its own.
            $table->json('question');
            // Rich text, as the editor wrote it; the markup takes it without the tags.
            $table->json('answer');
            $table->integer('position')->default(0);

            $table->timestamps();

            $table->index(['seo_url_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_faq_items');
    }
};
