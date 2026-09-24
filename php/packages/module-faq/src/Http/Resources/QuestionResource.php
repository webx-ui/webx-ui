<?php

declare(strict_types=1);

namespace WebxUi\Faq\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Faq\Models\FaqCategory;
use WebxUi\Faq\Models\Question;
use WebxUi\Localization\Locales;

/**
 * One question as the panel knows it: a row of the list, and the record the editor opens.
 *
 * `locales` is where the question can be seen (decision 9): the languages it has both a question
 * and an answer in. A published row seen nowhere is the one the list has to point out.
 *
 * @mixin Question
 */
final class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Question $question */
        $question = $this->resource;

        $locales = app(Locales::class);
        $locale = $locales->current();

        return [
            'id' => (int) $question->getKey(),
            'question' => $this->title($question, $locale),
            'anchor' => (string) $question->anchor,
            'published' => $question->published,
            'position' => (int) $question->position,
            'locales' => array_values(array_filter(
                $locales->codes(),
                static fn (string $code): bool => $question->writtenIn($code),
            )),
            'categories' => $question->categories
                ->map(static fn (FaqCategory $category): array => [
                    'id' => (int) $category->getKey(),
                    'title' => $category->displayName($locale),
                ])
                ->values()
                ->all(),
            'updated_at' => $question->updated_at?->toAtomString(),
            'deleted_at' => $question->deleted_at?->toAtomString(),
        ];
    }

    /** The question in this language, in another where there is none, the number where there is neither. */
    private function title(Question $question, string $locale): string
    {
        $text = $question->getTranslation('question', $locale);

        return is_string($text) && trim($text) !== '' ? $text : '#'.$question->getKey();
    }
}
