<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories of questions: the shared columns of every module's categories and nothing else
 * (decision 2 of the FAQ spec). No address, no SEO, no blocks — a category of a FAQ is a button
 * in a block's filter, not a page — so `slug` stays empty.
 *
 * The first day: nothing here points at another package's table (CLAUDE.md §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_categories', function (Blueprint $table): void {
            $table->id();

            $table->category();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_categories');
    }
};
