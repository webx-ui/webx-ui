<?php

declare(strict_types=1);

namespace WebxUi\Faq\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Faq\Models\FaqCategory;
use WebxUi\Faq\Models\Question;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;

/**
 * What an agent reads before it writes a question (§4.7): the whole FAQ in one message.
 *
 * Every category, hidden ones too, each with its questions in that category's own order, and the
 * questions filed nowhere at the end. Unpublished questions are in it and say so — the point of
 * reading this first is not to ask "How do I pay?" a second time beside one that is not out yet.
 * A question in two categories is listed under both: that is where a reader meets it.
 *
 * One text per row, in the language the agent works in; `visible_in` says where a reader sees
 * the question at all (decision 9), and `faq_get` has every language of the one it picks.
 */
final class FaqResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'faq://catalog',
                'FAQ catalog',
                'Every FAQ category in its order, with its questions in that category\'s own order, their '
                .'anchors, whether each is published and the languages a reader sees it in; the questions in '
                .'no category at the end. Read it before adding a question or a category, so that you reuse '
                .'rather than duplicate.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $locales = $this->container->make(Locales::class);
        $locale = $locales->current();
        $codes = $locales->codes();

        return [
            'locales' => $codes,
            'default_locale' => $locales->defaultCode(),
            'categories' => FaqCategory::query()->ordered()->get()->map(fn (FaqCategory $category): array => [
                'id' => (int) $category->getKey(),
                'title' => $category->displayName($locale),
                'visible' => (bool) $category->is_visible,
                'questions' => $this->rows(Question::query()->orderedIn((int) $category->getKey())->get()->all(), $locale, $codes),
            ])->values()->all(),
            'uncategorised' => $this->rows(
                Question::query()->whereDoesntHave('categories')->orderedIn()->get()->all(),
                $locale,
                $codes,
            ),
        ];
    }

    /**
     * @param  list<Question>  $questions
     * @param  list<string>  $codes
     * @return list<array<string, mixed>>
     */
    private function rows(array $questions, string $locale, array $codes): array
    {
        return array_map(static function (Question $question) use ($locale, $codes): array {
            $text = $question->questionText($locale);

            return [
                'id' => (int) $question->getKey(),
                'anchor' => (string) $question->anchor,
                'question' => $text !== '' ? $text : (string) ($question->getTranslation('question', $locale) ?: '#'.$question->getKey()),
                'published' => $question->published,
                'visible_in' => array_values(array_filter($codes, $question->visibleIn(...))),
            ];
        }, $questions);
    }
}
