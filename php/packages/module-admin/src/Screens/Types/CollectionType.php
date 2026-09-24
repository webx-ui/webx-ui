<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Admin\Screens\ResolvesMissing;
use WebxUi\Localization\Locales;

/**
 * `wx-collection`: which records of a module a block shows — the questions of a FAQ, the people
 * of a team — kept as the choice and read as the records (§3 of the FAQ spec).
 *
 * The source is the node's `props.source`, never part of the value: a block built for questions
 * does not become a block of reviews by an edit to its content.
 *
 * What the site reads is the records, and only the site reads it. The panel is handed the value
 * as stored, like every other field: `resolve()` is the site's door, the way a picture's words
 * are picked apart only there (`MediaValues`). A source that is gone — the module removed — reads
 * as nothing rather than as an exception: the block prints empty and the page stays up.
 */
final class CollectionType implements ResolvesMissing
{
    public function __construct(
        private readonly CollectionSources $sources,
        private readonly CategorySources $categories,
        private readonly Locales $locales,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail) use ($node): void {
            if (! is_array($value)) {
                return;
            }

            $key = $node['props']['source'] ?? null;
            $source = is_string($key) ? $this->sources->find($key) : null;

            if ($source === null) {
                $fail((string) __('webx-admin::collections.unknown-source', ['source' => is_string($key) ? $key : '']));

                return;
            }

            $limit = $value['limit'] ?? null;

            if ($limit !== null && ! (is_int($limit) && $limit >= 1 && $limit <= Selection::MAX_LIMIT)) {
                $fail((string) __('webx-admin::collections.limit', ['max' => Selection::MAX_LIMIT]));
            }

            foreach (['filter', 'markup'] as $flag) {
                if (($value[$flag] ?? null) !== null && ! is_bool($value[$flag])) {
                    $fail((string) __('webx-admin::collections.flag'));

                    break;
                }
            }

            $ids = $value['categories'] ?? [];

            if (! is_array($ids)) {
                $fail((string) __('webx-admin::collections.unknown-category'));

                return;
            }

            $wanted = Selection::normalise(['categories' => $ids])['categories'];

            if ($wanted === []) {
                return;
            }

            $model = $this->categoryModel($source);

            // Trashed ones counted, as `wx-categories` counts them: a category waiting in the bin
            // is not a reason to refuse the page it was chosen on.
            $found = $model === null ? 0 : $model::query()->withoutGlobalScopes()->whereKey($wanted)->count();

            if ($found !== count($wanted)) {
                $fail((string) __('webx-admin::collections.unknown-category'));
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{categories: list<int>, limit: int|null, filter: bool, markup: bool|null}|null
     */
    public function store(mixed $value, array $node): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        return Selection::normalise($value, $this->source($node));
    }

    /**
     * The records, their categories for a filter, and whether to draw one.
     *
     * @param  array<string, mixed>  $node
     * @return array{items: list<array<string, mixed>>, groups: list<array{id: int, title: string, items: list<int|string>}>, filter: bool}
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): array
    {
        $source = $this->source($node);

        if ($source === null) {
            return ['items' => [], 'groups' => [], 'filter' => false];
        }

        $locale ??= $this->locales->current();
        $selection = Selection::of($stored, $source);

        $items = $source->items($selection, $locale);

        return [
            'items' => $items,
            'groups' => $selection->filter ? $this->groups($source, $selection, $items, $locale) : [],
            'filter' => $selection->filter,
        ];
    }

    /**
     * The buttons of the filter: the chosen categories, or with none chosen every visible one of
     * the source — in the order the editor dragged them into, and only those with something shown
     * in them. A button that empties the list is not a filter anybody wants to press.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array{id: int, title: string, items: list<int|string>}>
     */
    private function groups(CollectionSource $source, Selection $selection, array $items, string $locale): array
    {
        $model = $this->categoryModel($source);

        if ($model === null || $items === []) {
            return [];
        }

        $members = [];

        foreach ($items as $item) {
            $id = $item['id'] ?? null;

            if (! is_int($id) && ! is_string($id)) {
                continue;
            }

            foreach (is_array($item['categories'] ?? null) ? $item['categories'] : [] as $category) {
                if (is_int($category)) {
                    $members[$category][] = $id;
                }
            }
        }

        $ids = $selection->categories === [] ? array_keys($members) : array_values(array_intersect($selection->categories, array_keys($members)));

        if ($ids === []) {
            return [];
        }

        $groups = [];

        foreach ($model::query()->whereKey($ids)->scopes(['visible' => [$locale], 'ordered'])->get() as $category) {
            $id = (int) $category->getKey();

            if (! $category instanceof Category) {
                continue;
            }

            $groups[] = [
                'id' => $id,
                'title' => $category->displayName($locale),
                'items' => $members[$id],
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function source(array $node): ?CollectionSource
    {
        $key = $node['props']['source'] ?? null;

        return is_string($key) ? $this->sources->find($key) : null;
    }

    /**
     * @return class-string<Model&Category>|null
     */
    private function categoryModel(CollectionSource $source): ?string
    {
        $path = $source->categories();

        return $path === null ? null : $this->categories->model($path);
    }
}
