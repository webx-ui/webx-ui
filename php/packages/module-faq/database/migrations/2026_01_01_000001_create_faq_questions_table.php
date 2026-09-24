<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A question and its answer (§4.1 of the FAQ spec).
 *
 * The anchor is made once, when the question is created, and never again (decision 10): it is
 * what `…/faq#paying-by-card` points at, and a link that stops opening its question because
 * somebody fixed a typo breaks without telling anyone. Nullable only for the instant between the
 * insert and the `q-<id>` a question with no words gets.
 *
 * No draft and no history (decision 12): `published` is the whole of the question's life.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_questions', function (Blueprint $table): void {
            $table->id();

            // Both translatable; the answer is a rich-text document holding library keys.
            $table->json('question')->nullable();
            $table->json('answer')->nullable();

            $table->string('anchor', 96)->nullable()->unique();
            $table->boolean('published')->default(false);

            // The order of the whole list. The place inside a category is on the link.
            $table->integer('position')->default(0);

            // The fields a project patched onto the editor (`HasExtra`).
            $table->json('extra')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_questions');
    }
};
