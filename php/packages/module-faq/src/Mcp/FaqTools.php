<?php

declare(strict_types=1);

namespace WebxUi\Faq\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Faq\Models\FaqCategory;
use WebxUi\Faq\Models\Question;
use WebxUi\Faq\Panel\QuestionForm;
use WebxUi\Faq\Panel\QuestionList;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * What an agent can do with the questions of the FAQ (§4.7).
 *
 * The same doors the panel uses. The list is {@see QuestionList}, so the two orders are the ones
 * the editor sees; the values go through {@see QuestionForm}, which checks them against the
 * described screen — a field a project patched onto `faq.form` is written under its own name and
 * refused where the panel would refuse it; the order is {@see Ordering}, the code behind the drag.
 *
 * Creating is the form's save of a new question, in its one transaction: categories the screen
 * refuses leave no question behind (the bare row `services_create` left on its release).
 *
 * A question is named by its id or by its anchor — the anchor never changes (decision 10), so it
 * is as good a name as the id and the one an agent read off a link.
 */
final class FaqTools
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $question = [
            'type' => ['integer', 'string'],
            'description' => 'The question: its id, or its anchor — "how-do-i-pay", the part after # in a link to it.',
        ];
        $category = [
            'type' => ['integer', 'string'],
            'description' => 'A category: its id, or its title in any language. faq_categories_list and faq://catalog have both.',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string, or every language as { "en": "…", "ru": "…" }.',
        ];

        return [
            Tool::read(
                'list',
                'The questions of the FAQ in the order they stand in: the question in every language, its '
                .'anchor, whether it is published, the languages a reader sees it in and its categories. '
                .'Narrowed to a category, the list is in that category\'s own order, which is not the order of '
                .'the whole list. Read this (or faq://catalog) first: the same question asked twice is a '
                .'duplicate, not a second answer.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Questions whose text or anchor contains this, in any language the site has.'],
                    'category' => $category + ['description' => 'Only the questions in this category, in its order: its id or its title.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin, newest first.'],
                ]],
                permission: ['faq.view', 'faq.manage'],
            ),

            Tool::read(
                'get',
                'One question in full: the values of its editor — the question and the answer (HTML) in every '
                .'language, whether it is published, its categories, and any field the project added to the '
                .'editor — with its anchor and the languages it is seen in.',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => ['question' => $question], 'required' => ['question']],
                permission: ['faq.view', 'faq.manage'],
            ),

            Tool::mutating(
                'create',
                'Add a question at the end of the list. It is not on the site until it is published — pass '
                .'published: true only when a person asked for that. A reader sees a question only in the '
                .'languages it has both the question and the answer in; no other language stands in. The '
                .'anchor is made from the question in the default language and never changes afterwards.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    'question' => $text,
                    'answer' => $text + ['description' => 'HTML — paragraphs, lists, links. One language as a string, or every language as an object.'],
                    'published' => ['type' => 'boolean', 'description' => 'On the site at once. False when omitted.'],
                    'categories' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Category ids. A question may be in several, or in none.'],
                    'values' => ['type' => 'object', 'description' => 'The fields the project added to the editor, as faq_get returns them.'],
                ], 'required' => ['question']],
                permission: 'faq.manage',
            ),

            Tool::mutating(
                'update',
                'Change the values of a question — question, answer, published, categories and the fields the '
                .'project added. A field left out keeps what it had, and so does a language left out of a '
                .'translated field: send "" for a language to take it away. It is on the site at once: '
                .'questions have no draft.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'question' => $question,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as faq_get returns them. question and answer take { "en": "…" }.'],
                ], 'required' => ['question', 'values']],
                permission: 'faq.manage',
            ),

            Tool::mutating(
                'delete',
                'Put a question in the bin. It leaves every FAQ block at once; the bin in the panel brings it '
                .'back with the same anchor, so links to it work again.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['question' => $question], 'required' => ['question']],
                permission: 'faq.manage',
            ),

            Tool::mutating(
                'reorder',
                'Put questions in a new order. Without a category it is the order of the whole list; with one it '
                .'is the order inside that category only, and every other category keeps its own. Name the '
                .'questions in the order they should stand in — the ones you leave out stay where they are.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'questions' => ['type' => 'array', 'items' => $question, 'description' => 'The questions, first to last.'],
                    'category' => $category + ['description' => 'The category whose own order this is. The whole list when omitted.'],
                ], 'required' => ['questions']],
                permission: 'faq.manage',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = [
            'search' => (string) ($arguments['search'] ?? ''),
            'trashed' => ($arguments['trashed'] ?? false) === true ? '1' : '0',
        ];

        $category = $this->category($arguments['category'] ?? null);

        if ($category !== null) {
            $query['category'] = (string) $category->getKey();
        }

        // The panel's own query: no pages, because a FAQ is dozens of rows and the order is only
        // an order when the whole of it is in view.
        $questions = $this->container->make(QuestionList::class)->build(Request::create('/', 'GET', $query))->get();

        return [
            'locales' => $this->locales()->codes(),
            'default_locale' => $this->locales()->defaultCode(),
            'order' => $category === null ? 'the whole list' : 'the order of this category',
            'count' => $questions->count(),
            'questions' => $questions->map(fn (Question $question): array => $this->summary($question))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        $question = $this->question($arguments['question'] ?? null);

        return [
            'question' => $this->summary($question),
            'values' => $this->form()->values($question),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $values = $arguments['values'] ?? [];

        if (! is_array($values)) {
            throw new ToolFailure('`values` must be an object of field name → value.');
        }

        // The named arguments win over the same names in `values`: they are what the tool says it
        // takes, and an agent that sent both meant the one it could see.
        $values = [
            ...$values,
            'question' => $this->text($arguments['question'] ?? null, 'question'),
            'published' => ($arguments['published'] ?? false) === true,
        ];

        if (array_key_exists('answer', $arguments)) {
            $values['answer'] = $this->text($arguments['answer'], 'answer');
        }

        if (array_key_exists('categories', $arguments)) {
            $values['categories'] = $this->categoryIds($arguments['categories']);
        }

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_create' => [
                    'question' => $values['question'],
                    'published' => $values['published'],
                    'categories' => $values['categories'] ?? [],
                ],
            ];
        }

        $question = $this->form()->save(new Question, $values, $this->can($user));

        return $this->get(['question' => $question->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $question = $this->question($arguments['question'] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value. faq_get says what the fields are.');
        }

        if ($question->trashed()) {
            throw new ToolFailure("Question {$this->reference($question)} is in the bin. Bring it back in the panel before editing it.");
        }

        if (array_key_exists('categories', $values)) {
            $values['categories'] = $this->categoryIds($values['categories']);
        }

        foreach (['question', 'answer'] as $field) {
            // A plain string is the default language, as in faq_create — not the language of a
            // request that never chose one.
            if (is_string($values[$field] ?? null)) {
                $values[$field] = [$this->locales()->defaultCode() => $values[$field]];
            }
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'fields' => array_keys($values), 'question' => $this->reference($question)];
        }

        $this->form()->save($question, $values, $this->can($user));

        return $this->get(['question' => $question->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $question = $this->question($arguments['question'] ?? null);

        if ($question->trashed()) {
            return ['trashed' => true, 'id' => (int) $question->getKey(), 'already' => true];
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->reference($question), 'published' => $question->published];
        }

        $question->delete();

        return ['trashed' => true, 'id' => (int) $question->getKey()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $given = $arguments['questions'] ?? null;

        if (! is_array($given) || $given === []) {
            throw new ToolFailure('`questions` is the list of questions in their new order: ids or anchors.');
        }

        $ids = array_values(array_map(fn (mixed $one): int => (int) $this->question($one)->getKey(), $given));
        $category = $this->category($arguments['category'] ?? null);

        if ($category !== null) {
            // Only the questions the category holds: `item_position` lives on the link, and a
            // question that is not in the category has no place in its order to be given.
            $inside = $category->questions()->pluck('faq_questions.id')->map(intval(...))->all();
            $outside = array_values(array_diff($ids, $inside));

            if ($outside !== []) {
                throw new ToolFailure(sprintf(
                    'Not in this category: #%s. File them into it with faq_update first, or leave them out.',
                    implode(', #', $outside),
                ));
            }
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_order' => $ids, 'category' => $category?->getKey()];
        }

        Ordering::move(Question::class, $ids, $category === null ? null : (int) $category->getKey());

        return $this->list($category === null ? [] : ['category' => $category->getKey()]);
    }

    /**
     * One question as an agent needs it: every language at once, and where a reader sees it —
     * a published question seen in no language is the one worth pointing out.
     *
     * @return array<string, mixed>
     */
    private function summary(Question $question): array
    {
        $question->loadMissing('categories');
        $written = array_values(array_filter(
            $this->locales()->codes(),
            static fn (string $code): bool => $question->writtenIn($code),
        ));

        $summary = [
            'id' => (int) $question->getKey(),
            'anchor' => (string) $question->anchor,
            'question' => $question->getTranslations('question'),
            'published' => $question->published,
            // Decision 9: both halves in a language, and published — nothing stands in.
            'visible_in' => $question->published && ! $question->trashed() ? $written : [],
            'written_in' => $written,
            'position' => (int) $question->position,
            'updated_at' => $question->updated_at?->toAtomString(),
            'categories' => $question->categories
                ->map(static fn (FaqCategory $category): array => [
                    'id' => (int) $category->getKey(),
                    'title' => $category->getTranslations('title'),
                ])
                ->values()
                ->all(),
        ];

        if ($question->trashed()) {
            $summary['deleted_at'] = $question->deleted_at?->toAtomString();
        }

        return $summary;
    }

    private function reference(Question $question): string
    {
        return sprintf('#%s (%s)', $question->getKey(), $question->anchor);
    }

    /**
     * A question by id or by anchor — the anchor is what a link to it and `faq://catalog` carry.
     */
    private function question(mixed $reference): Question
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $question = Question::withTrashed()->find((int) $reference);

            return $question instanceof Question
                ? $question
                : throw new ToolFailure("No question has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('A question is an id, or an anchor like "how-do-i-pay".');
        }

        $anchor = ltrim(trim($reference), '#');
        $question = Question::withTrashed()->where('anchor', $anchor)->first();

        return $question instanceof Question
            ? $question
            : throw new ToolFailure("No question has the anchor [{$anchor}]. faq_list has them all.");
    }

    /**
     * A category by id or by its title in any language — FAQ categories have no slug to go by.
     */
    private function category(mixed $reference): ?FaqCategory
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $category = FaqCategory::query()->find((int) $reference);
        } elseif (is_string($reference)) {
            $category = FaqCategory::query()->whereTranslationLikeAny('title', trim($reference))->first();
        } else {
            throw new ToolFailure('`category` is an id or a title.');
        }

        return $category instanceof FaqCategory
            ? $category
            : throw new ToolFailure('No such category. faq_categories_list says what there is.');
    }

    /**
     * Ids, checked before the save rather than left to it: `wx-categories` would drop an id it
     * does not know without a word, and an agent would think the question was filed.
     *
     * @return list<int>
     */
    private function categoryIds(mixed $given): array
    {
        if (! is_array($given)) {
            throw new ToolFailure('`categories` is a list of category ids.');
        }

        $ids = array_values(array_unique(array_map(
            fn (mixed $one): int => (int) ($this->category($one) ?? throw new ToolFailure('`categories` holds an empty value.'))->getKey(),
            $given,
        )));

        return $ids;
    }

    /**
     * Run a change, and turn a refusal into something the agent can read.
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        } catch (CategoryException $refused) {
            throw new ToolFailure($refused->getMessage());
        }
    }

    /**
     * A translated field as the form takes it: a map of languages. A plain string is the default
     * language — the form would read it as the language of the request, and an agent's request
     * has none it chose.
     *
     * @return array<string, string>
     */
    private function text(mixed $value, string $field): array
    {
        if (is_string($value)) {
            return trim($value) === ''
                ? throw new ToolFailure("`{$field}` cannot be empty.")
                : [$this->locales()->defaultCode() => trim($value)];
        }

        if (! is_array($value) || $value === []) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if (is_string($text) && trim($text) !== '') {
                $texts[(string) $locale] = trim($text);
            }
        }

        return $texts === [] ? throw new ToolFailure("`{$field}` cannot be empty.") : $texts;
    }

    /**
     * The permission check the screen asks for a field behind one — the same question the panel
     * asks of its editor, so an agent cannot write a field its administrator could not.
     *
     * @return (callable(string): bool)|null
     */
    private function can(?Authenticatable $user): ?callable
    {
        return $user instanceof HasPermissions
            ? static fn (string $permission): bool => $user->hasPermission($permission)
            : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function form(): QuestionForm
    {
        return $this->container->make(QuestionForm::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }
}
