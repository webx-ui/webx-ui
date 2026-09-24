<?php

declare(strict_types=1);

namespace WebxUi\Faq\Collections;

use Illuminate\Http\Request;
use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Faq\Models\Question;
use WebxUi\Seo\Rendering\Seo;

/**
 * The questions a FAQ block shows (§4.3 of the FAQ spec).
 *
 * An element is `id`, `anchor`, `categories`, the `question` as a string and the `answer` as
 * HTML with its pictures pointed at where they live now. Who is shown is decision 9: published,
 * out of the bin, and written in the page's language — question and answer both, no other
 * language standing in.
 *
 * The markup is one `FAQPage` per page however many blocks ask for it (§3.5): the questions of
 * the request are gathered here by id, and the whole block is put again under one key every time,
 * so the last block's call prints everything and nothing twice. Only where `module-seo` is there
 * to print it; a site without it has no `<head>` of ours to put it in.
 */
final class FaqSource implements CollectionSource
{
    /** The key a schema names the source by, and the key of the markup on the page. */
    public const KEY = 'faq';

    /** Where the questions of this request are gathered, so that they die with it. */
    private const GATHERED = 'webx-faq.markup';

    /**
     * Outside a request — a command rendering a page — the gathering lives here.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $gathered = [];

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return (string) __('webx-faq::module.group');
    }

    public function categories(): string
    {
        return 'faq/categories';
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return [];
    }

    public function supportsMarkup(): bool
    {
        return class_exists(Seo::class) && (bool) config('webx-faq.markup', true);
    }

    public function permission(): string
    {
        return 'faq.view';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(Selection $selection, string $locale): array
    {
        // Without the limit in SQL: whether a question is written in a language is a question of
        // what the words are, not of whether a key is there, and the limit counts what is shown.
        $unlimited = new Selection($selection->categories, null, $selection->filter, $selection->markup);

        $questions = $unlimited->apply(Question::query()->where('published', true)->with('categories'))
            ->get()
            ->filter(static fn (Question $question): bool => $question->visibleIn($locale))
            ->values();

        if ($selection->limit !== null) {
            $questions = $questions->take($selection->limit);
        }

        $items = $questions
            ->map(static fn (Question $question): array => [
                'id' => (int) $question->getKey(),
                'anchor' => (string) $question->anchor,
                'categories' => $question->categoryIds(),
                'question' => $question->questionText($locale),
                'answer' => $question->answerHtml($locale),
            ])
            ->values()
            ->all();

        if ($selection->markup && $this->supportsMarkup()) {
            $this->markUp($items);
        }

        return $items;
    }

    /**
     * Add these questions to what the page has shown so far and put the whole `FAQPage` again.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function markUp(array $items): void
    {
        $gathered = $this->gathered();

        foreach ($items as $item) {
            $answer = trim(preg_replace('/\s+/u', ' ', html_entity_decode(
                strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>'], ["</p>\n", "\n", "\n", "\n", "</li>\n"], (string) $item['answer'])),
                ENT_QUOTES | ENT_HTML5,
            )) ?? '');

            // `+=`: a question shown by an earlier block stays where that block put it.
            $gathered += [(int) $item['id'] => [
                '@type' => 'Question',
                'name' => (string) $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ]];
        }

        $this->keep($gathered);

        if ($gathered === []) {
            return;
        }

        app(Seo::class)->put(self::KEY, [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_values($gathered),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function gathered(): array
    {
        $request = $this->request();

        if ($request === null) {
            return $this->gathered;
        }

        /** @var array<int, array<string, mixed>> $gathered */
        $gathered = (array) $request->attributes->get(self::GATHERED, []);

        return $gathered;
    }

    /**
     * @param  array<int, array<string, mixed>>  $gathered
     */
    private function keep(array $gathered): void
    {
        $request = $this->request();

        if ($request === null) {
            $this->gathered = $gathered;

            return;
        }

        $request->attributes->set(self::GATHERED, $gathered);
    }

    private function request(): ?Request
    {
        $request = app()->bound('request') ? app('request') : null;

        return $request instanceof Request ? $request : null;
    }
}
