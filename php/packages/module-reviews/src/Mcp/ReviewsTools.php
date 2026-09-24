<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;
use WebxUi\Reviews\Panel\ReviewForm;
use WebxUi\Reviews\Panel\ReviewList;
use WebxUi\Reviews\Panel\ReviewNames;

/**
 * What an agent can do with reviews (§4.8).
 *
 * The same doors the panel uses. The list is {@see ReviewList}, so the two orders are the ones the
 * editor sees; the values go through {@see ReviewForm}, which checks them against the described
 * screen — the stars, the link to a profile and a field a project patched onto `reviews.form` are
 * refused where the panel would refuse them; the order is {@see Ordering}, the code behind the drag.
 *
 * Creating is the form's save of a new review, in its one transaction: a refused value leaves no
 * review behind.
 *
 * A review is named by its id and nothing else: it has no slug and no anchor of its own making, and
 * names repeat — two Annas are two reviews.
 */
final class ReviewsTools
{
    /** The fields that take one language as a string or every language as a map. */
    private const TRANSLATED = ['name', 'job_title', 'text'];

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $review = [
            'type' => ['integer', 'string'],
            'description' => 'The review\'s id — reviews_list and reviews://catalog have them.',
        ];
        $category = [
            'type' => ['integer', 'string'],
            'description' => 'A category: its id, or its title in any language. review_categories_list and reviews://catalog have both.',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string, or every language as { "en": "…", "ru": "…" }.',
        ];
        $fields = [
            'name' => $text + ['description' => 'Who wrote it. A name without a translation is shown in the default language.'],
            'job_title' => $text + ['description' => 'What they do — "CEO, Acme". Shown in the default language when not translated.'],
            'text' => $text + ['description' => 'What they wrote: plain text, paragraphs separated by blank lines — no HTML.'],
            'rating' => ['type' => ['integer', 'null'], 'description' => 'Stars, 1 to 5. Null or 0 is no rating: the site then shows no stars.'],
            'reviewed_on' => ['type' => ['string', 'null'], 'description' => 'The date of the review, YYYY-MM-DD. Only shown; it orders nothing.'],
            'profile_url' => ['type' => ['string', 'null'], 'description' => 'A link to the person or their company, http(s) only.'],
            'photo' => [
                'type' => ['string', 'object', 'null'],
                'description' => 'A picture from the library: its key ("media/ab/cd/anna.jpg", as media_list_files gives it), '
                    .'or { "path": "…", "alt": "…" }. Null takes it away. Without one the site shows the initials.',
            ],
        ];

        return [
            Tool::read(
                'list',
                'Reviews in the order they stand in: who wrote them, the stars, whether each is published and the '
                .'languages a reader sees it in, and its categories. Narrowed to a category, the list is in that '
                .'category\'s own order, which is not the order of the whole list. Read this (or reviews://catalog) '
                .'first: the same review typed in twice is a duplicate.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Reviews whose name, job title or text contains this, in any language the site has.'],
                    'category' => $category + ['description' => 'Only the reviews in this category, in its order: its id or its title.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin, newest first.'],
                ]],
                permission: ['reviews.view', 'reviews.manage'],
            ),

            Tool::read(
                'get',
                'One review in full: the values of its editor — name, job title and text in every language, the '
                .'stars, the date, the link to a profile, the photo, whether it is published, its categories and any '
                .'field the project added — with the languages it is seen in.',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => ['review' => $review], 'required' => ['review']],
                permission: ['reviews.view', 'reviews.manage'],
            ),

            Tool::mutating(
                'create',
                'Add a review at the end of the list. It is not on the site until it is published — pass '
                .'published: true only when a person asked for that. A reader sees a review only in the languages '
                .'its text is written in; no other language stands in for the text. Write down what a real person '
                .'said: a review made up is not a review.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    ...$fields,
                    'published' => ['type' => 'boolean', 'description' => 'On the site at once. False when omitted.'],
                    'categories' => ['type' => 'array', 'items' => $category, 'description' => 'Categories: ids or titles. A review may be in several, or in none.'],
                    'values' => ['type' => 'object', 'description' => 'The fields the project added to the editor, as reviews_get returns them.'],
                ], 'required' => ['name']],
                permission: 'reviews.manage',
            ),

            Tool::mutating(
                'update',
                'Change the values of a review — any of name, job_title, text, rating, reviewed_on, profile_url, '
                .'photo, published, categories and the fields the project added. A field left out keeps what it '
                .'had, and so does a language left out of a translated field: send "" for a language to take it '
                .'away. It is on the site at once: reviews have no draft.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'review' => $review,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as reviews_get returns them. name, job_title and text take a string (the default language) or { "en": "…" }; photo takes a library key.'],
                ], 'required' => ['review', 'values']],
                permission: 'reviews.manage',
            ),

            Tool::mutating(
                'delete',
                'Put a review in the bin. It leaves every reviews block at once; the bin in the panel brings it back.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['review' => $review], 'required' => ['review']],
                permission: 'reviews.manage',
            ),

            Tool::mutating(
                'reorder',
                'Put reviews in a new order. Without a category it is the order of the whole list; with one it is '
                .'the order inside that category only, and every other category keeps its own. Name the reviews in '
                .'the order they should stand in — the ones you leave out stay where they are.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'reviews' => ['type' => 'array', 'items' => $review, 'description' => 'The reviews, first to last.'],
                    'category' => $category + ['description' => 'The category whose own order this is. The whole list when omitted.'],
                ], 'required' => ['reviews']],
                permission: 'reviews.manage',
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

        // The panel's own query: no pages, because the order is only an order when the whole of
        // it is in view.
        $reviews = $this->container->make(ReviewList::class)->build(Request::create('/', 'GET', $query))->get();

        return [
            'locales' => $this->locales()->codes(),
            'default_locale' => $this->locales()->defaultCode(),
            'order' => $category === null ? 'the whole list' : 'the order of this category',
            'count' => $reviews->count(),
            'reviews' => $reviews->map(fn (Review $review): array => $this->summary($review))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        $review = $this->review($arguments['review'] ?? null);

        return [
            'review' => $this->summary($review),
            'values' => $this->form()->values($review),
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
            'name' => $this->text($arguments['name'] ?? null, 'name'),
            'published' => ($arguments['published'] ?? false) === true,
        ];

        foreach (['job_title', 'text'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $values[$field] = $this->text($arguments[$field], $field, empty: true);
            }
        }

        foreach (['rating', 'reviewed_on', 'profile_url', 'photo', 'categories'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $values[$field] = $arguments[$field];
            }
        }

        $values = $this->prepare($values);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_create' => [
                    'name' => $values['name'],
                    'published' => $values['published'],
                    'categories' => $values['categories'] ?? [],
                ],
            ];
        }

        $review = $this->form()->save(new Review, $values, $this->can($user));

        return $this->get(['review' => $review->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $review = $this->review($arguments['review'] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value. reviews_get says what the fields are.');
        }

        if ($review->trashed()) {
            throw new ToolFailure("Review #{$review->getKey()} is in the bin. Bring it back in the panel before editing it.");
        }

        foreach (self::TRANSLATED as $field) {
            // A plain string is the default language, as in reviews_create — not the language of a
            // request that never chose one.
            if (is_string($values[$field] ?? null)) {
                $values[$field] = [$this->locales()->defaultCode() => $values[$field]];
            }
        }

        $values = $this->prepare($values);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'fields' => array_keys($values), 'review' => $this->reference($review)];
        }

        $this->form()->save($review, $values, $this->can($user));

        return $this->get(['review' => $review->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $review = $this->review($arguments['review'] ?? null);

        if ($review->trashed()) {
            return ['trashed' => true, 'id' => (int) $review->getKey(), 'already' => true];
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->reference($review), 'published' => $review->published];
        }

        $review->delete();

        return ['trashed' => true, 'id' => (int) $review->getKey()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $given = $arguments['reviews'] ?? null;

        if (! is_array($given) || $given === []) {
            throw new ToolFailure('`reviews` is the list of review ids in their new order.');
        }

        $ids = array_values(array_map(fn (mixed $one): int => (int) $this->review($one)->getKey(), $given));
        $category = $this->category($arguments['category'] ?? null);

        if ($category !== null) {
            // Only the reviews the category holds: `item_position` lives on the link, and a
            // review that is not in the category has no place in its order to be given.
            $inside = $category->reviews()->pluck('reviews.id')->map(intval(...))->all();
            $outside = array_values(array_diff($ids, $inside));

            if ($outside !== []) {
                throw new ToolFailure(sprintf(
                    'Not in this category: #%s. File them into it with reviews_update first, or leave them out.',
                    implode(', #', $outside),
                ));
            }
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_order' => $ids, 'category' => $category?->getKey()];
        }

        Ordering::move(Review::class, $ids, $category === null ? null : (int) $category->getKey());

        return $this->list($category === null ? [] : ['category' => $category->getKey()]);
    }

    /**
     * What the screen takes, from what an agent is likely to send: category titles become ids, and
     * a photo named by its key becomes the value `wx-media` stores.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function prepare(array $values): array
    {
        if (array_key_exists('categories', $values)) {
            $values['categories'] = $this->categoryIds($values['categories']);
        }

        if (is_string($values['photo'] ?? null)) {
            $values['photo'] = trim($values['photo']) === '' ? null : ['path' => trim($values['photo'])];
        }

        $path = is_array($values['photo'] ?? null) ? ($values['photo']['path'] ?? null) : null;

        // `wx-media` lets a key the library does not have through — the panel only offers keys it
        // has. An agent types them, and a typo would leave the initials where it meant a photo
        // without a word.
        if (is_string($path) && $path !== '' && ! MediaFile::query()->where('path', $path)->exists()) {
            throw new ToolFailure("The library has no file [{$path}]. media_search_files finds one by name.");
        }

        return $values;
    }

    /**
     * One review as an agent needs it: every language at once, and where a reader sees it — a
     * published review seen in no language is the one worth pointing out.
     *
     * @return array<string, mixed>
     */
    private function summary(Review $review): array
    {
        $review->loadMissing('categories');
        $written = array_values(array_filter($this->locales()->codes(), $review->writtenIn(...)));

        $summary = [
            'id' => (int) $review->getKey(),
            'name' => $review->getTranslations('name'),
            'job_title' => $review->getTranslations('job_title'),
            'rating' => $review->rating,
            'photo' => $review->photoPath(),
            'published' => $review->published,
            // Decision 7: the text in a language, and published — nothing stands in.
            'visible_in' => $review->published && ! $review->trashed() ? $written : [],
            'written_in' => $written,
            'position' => (int) $review->position,
            'updated_at' => $review->updated_at?->toAtomString(),
            'categories' => $review->categories
                ->map(static fn (ReviewCategory $category): array => [
                    'id' => (int) $category->getKey(),
                    'title' => $category->getTranslations('title'),
                ])
                ->values()
                ->all(),
        ];

        if ($review->trashed()) {
            $summary['deleted_at'] = $review->deleted_at?->toAtomString();
        }

        return $summary;
    }

    private function reference(Review $review): string
    {
        return sprintf('#%s (%s)', $review->getKey(), ReviewNames::of($review, $this->locales()));
    }

    private function review(mixed $reference): Review
    {
        if (! is_int($reference) && ! (is_string($reference) && ctype_digit(ltrim(trim($reference), '#')))) {
            throw new ToolFailure('A review is its id — reviews_list has them.');
        }

        $id = (int) ltrim(trim((string) $reference), '#');
        $review = Review::withTrashed()->find($id);

        return $review instanceof Review
            ? $review
            : throw new ToolFailure("No review has the id [{$id}].");
    }

    /**
     * A category by id or by its title in any language — these categories have no slug to go by.
     */
    private function category(mixed $reference): ?ReviewCategory
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $category = ReviewCategory::query()->find((int) $reference);
        } elseif (is_string($reference)) {
            $category = ReviewCategory::query()->whereTranslationLikeAny('title', trim($reference))->first();
        } else {
            throw new ToolFailure('`category` is an id or a title.');
        }

        return $category instanceof ReviewCategory
            ? $category
            : throw new ToolFailure('No such category. review_categories_list says what there is.');
    }

    /**
     * Ids, checked before the save rather than left to it: `wx-categories` would drop an id it
     * does not know without a word, and an agent would think the review was filed.
     *
     * @return list<int>
     */
    private function categoryIds(mixed $given): array
    {
        if (! is_array($given)) {
            throw new ToolFailure('`categories` is a list of category ids or titles.');
        }

        return array_values(array_unique(array_map(
            fn (mixed $one): int => (int) ($this->category($one) ?? throw new ToolFailure('`categories` holds an empty value.'))->getKey(),
            $given,
        )));
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
    private function text(mixed $value, string $field, bool $empty = false): array
    {
        if (is_string($value)) {
            if (trim($value) !== '') {
                return [$this->locales()->defaultCode() => trim($value)];
            }

            return $empty ? [] : throw new ToolFailure("`{$field}` cannot be empty.");
        }

        if ($value === null && $empty) {
            return [];
        }

        if (! is_array($value)) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if (is_string($text) && trim($text) !== '') {
                $texts[(string) $locale] = trim($text);
            }
        }

        return $texts === [] && ! $empty ? throw new ToolFailure("`{$field}` cannot be empty.") : $texts;
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

    private function form(): ReviewForm
    {
        return $this->container->make(ReviewForm::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }
}
