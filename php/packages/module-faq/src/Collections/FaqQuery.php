<?php

declare(strict_types=1);

namespace WebxUi\Faq\Collections;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\Faq\Models\Question;

/**
 * The questions a FAQ block may show — the steps and their rules are {@see RecordQuery}'s, so a
 * block of questions filters, orders and counts the way a block of services does.
 *
 * Who is shown is decision 9: published, out of the bin, and written in the language — question
 * and answer both, no other language standing in. An element is `id`, `anchor`, `categories`,
 * the `question` as a string and the `answer` as HTML with its pictures pointed at where they
 * live now. No site helper stands on it: a FAQ is placed as a block (§4.3 of the FAQ spec).
 *
 * @extends RecordQuery<Question>
 */
final class FaqQuery extends RecordQuery
{
    protected function newQuery(string $locale): Builder
    {
        return Question::query()->where('published', true)->with('categories');
    }

    protected function shownIn(Model $record, string $locale): bool
    {
        return $record->visibleIn($locale);
    }

    /**
     * @param  list<Question>  $records
     * @return list<array<string, mixed>>
     */
    protected function cards(array $records, string $locale): array
    {
        return array_map(static fn (Question $question): array => [
            'id' => (int) $question->getKey(),
            'anchor' => (string) $question->anchor,
            'categories' => $question->categoryIds(),
            'question' => $question->questionText($locale),
            'answer' => $question->answerHtml($locale),
        ], $records);
    }
}
