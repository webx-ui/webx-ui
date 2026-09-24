<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which categories a question is in, and where it stands inside each (decision 4).
 *
 * After both tables it links, which the filenames guarantee inside this package.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_category_question', function (Blueprint $table): void {
            $table->categoryLinks('question', 'faq_categories', 'faq_questions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_category_question');
    }
};
