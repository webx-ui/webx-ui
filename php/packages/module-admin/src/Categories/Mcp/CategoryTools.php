<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * One module's categories, for an agent: list, create, update, delete, reorder.
 *
 * The names carry no prefix of their own — the module's id puts one in front (`rubrics_list`,
 * `services_categories_update`), and what stands after it is what tells the tools apart in a
 * client that shows only that part (CLAUDE.md §4). The words are the module's, from its
 * {@see CategoryKind}: an agent is told about rubrics and articles, not categories and items.
 *
 * Every write goes through {@see CategoryForm} — the door the panel uses — so the screen checks
 * an agent's values exactly as it checks the editor's, and a project's field lands in `extra`.
 *
 * Needs `webx-ui/mcp`, which the frame does not require: a module that offers these tools
 * requires it, and nothing loads this class otherwise.
 */
final readonly class CategoryTools
{
    /**
     * @param  class-string<Model&Category>  $model
     */
    public function __construct(
        private string $model,
        private CategoryForm $form,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $kind = $this->kind();
        $one = $kind->noun;
        $many = $kind->plural;
        $items = $kind->items;
        // A kind without a prefix has no addresses at all (the FAQ's), and an agent promised a
        // slug would write one, see it vanish and try again.
        $addressed = $kind->prefix !== null;
        $category = [
            'type' => ['integer', 'string'],
            'description' => $addressed
                ? "A {$one}: its id, or its slug in any language."
                : "A {$one}: its id, or its title in any language.",
        ];
        $slug = $addressed
            ? ['slug' => ['type' => ['string', 'object'], 'description' => 'The address part, the same shape as the title. Made out of the title when omitted.']]
            : [];

        return [
            Tool::read(
                'list',
                "The {$many} in the order they stand in on the site: what each is called in every language, "
                .($addressed ? 'the address it answers at, ' : '')
                ."whether it is visible, and how many {$items} are in it. "
                .($addressed
                    ? "The first {$one} of an item is its main one — the one in the breadcrumbs — so the order they are given in matters. "
                    : "A {$one} has no address of its own: it is a way to pick and filter {$items}. ")
                ."Read this before filing anything, and reuse a {$one} rather than making a near-duplicate.",
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'visible' => ['type' => 'boolean', 'description' => 'Only the ones a reader can reach. All of them when omitted.'],
                    'search' => ['type' => 'string', 'description' => 'Only the ones whose title or slug contains this.'],
                ]],
                permission: array_values(array_unique([...$kind->view, $kind->manage])),
            ),

            Tool::mutating(
                'create',
                "Make a new {$one} at the end of the list. "
                .($addressed
                    ? 'The address is made out of the title unless a slug is given; an address another page already answers at is refused, not quietly changed. '
                    : '')
                ."It is visible at once — a {$one} has no draft — so make one only when a person asked for it.",
                fn (array $arguments): array => $this->attempt(fn (): array => $this->create($arguments)),
                ['properties' => [
                    'title' => ['type' => ['string', 'object'], 'description' => 'The name: a string in the default language, or an object of language → text.'],
                    ...$slug,
                ], 'required' => ['title']],
                permission: $kind->manage,
            ),

            Tool::mutating(
                'update',
                "Change the values of a {$one} — the fields of its editor in the panel: title, "
                .($addressed ? 'slug, ' : '')
                .'visibility, and '
                .'whatever else the screen of this site has. Only the fields you send change; the answer carries '
                .'every value the editor now holds. It takes effect on the site at once.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    $one => $category,
                    'values' => ['type' => 'object', 'description' => 'Field name → value. A translated field is an object of language → text.'],
                ], 'required' => [$one, 'values']],
                permission: $kind->manage,
            ),

            Tool::mutating(
                'delete',
                "Put a {$one} in the bin. One that still has {$items} in it is refused, with the number: move them "
                .'first, or decide the '.$one.' was the right idea after all.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => [$one => $category], 'required' => [$one]],
                permission: $kind->manage,
            ),

            Tool::mutating(
                'reorder',
                "Put the {$many} in a new order — the order of the menu on the site. Name them all, first to last; "
                .'one left out stays where it is.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'The ids, first to last.'],
                ], 'required' => ['ids']],
                permission: $kind->manage,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $kind = $this->kind();
        $query = ($this->model)::query()->scopes(['withItemCount', 'ordered']);

        if (method_exists($this->model, 'routeCanonical')) {
            $query->with('routes');
        }

        if (($arguments['visible'] ?? null) === true) {
            $query->scopes(['visible']);
        }

        $search = trim((string) ($arguments['search'] ?? ''));

        if ($search !== '') {
            $query->scopes(['matching' => [$search]]);
        }

        $found = $query->get();

        return [
            'count' => $found->count(),
            $kind->plural => $found->map(fn (Model $category): array => $this->row($category))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments): array
    {
        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_create' => ['title' => $arguments['title'] ?? null, 'slug' => $arguments['slug'] ?? null]];
        }

        $category = $this->form->create($this->model, $arguments['title'] ?? null, $arguments['slug'] ?? null);

        // Read back: a column the insert left to its default (`is_visible`) is not on the
        // model yet, and the answer would call a visible category hidden.
        return $this->describe($category->refresh());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $category = $this->find($arguments[$this->kind()->noun] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value.');
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'fields' => array_keys($values), $this->kind()->noun => $this->row($category)];
        }

        $this->form->save(
            $category,
            $values,
            $user instanceof HasPermissions ? static fn (string $permission): bool => $user->hasPermission($permission) : null,
        );

        return $this->describe($category);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $category = $this->find($arguments[$this->kind()->noun] ?? null);

        if ($this->dryRun($arguments)) {
            $count = $category->itemCount();

            return [
                'dry_run' => true,
                'would_delete' => $count === 0,
                $this->kind()->countKey() => $count,
            ];
        }

        $category->delete();

        return ['deleted' => (int) $category->getKey()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $ids = $arguments['ids'] ?? null;

        if (! is_array($ids) || $ids === []) {
            throw new ToolFailure('`ids` must be a non-empty list of ids.');
        }

        $ids = array_values(array_map(intval(...), $ids));

        if (! $this->dryRun($arguments)) {
            Ordering::move($this->model, $ids);
        }

        return $this->list([]) + ['dry_run' => $this->dryRun($arguments)];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Model $category): array
    {
        /** @var Model&Category $category */
        $paths = [];

        if ($category->relationLoaded('routes')) {
            foreach ($category->getRelation('routes') as $route) {
                if ($route->getAttribute('kind') === 'canonical') {
                    $paths[(string) $route->getAttribute('locale')] = '/'.$route->getAttribute('path');
                }
            }
        }

        $count = $category->getAttribute($this->kind()->countKey());

        $row = [
            'id' => (int) $category->getKey(),
            'title' => $category->categoryValue('title'),
            'slug' => $category->categoryValue('slug'),
            // A language missing here is a language the category names no slug in, and so has no
            // address in. A real state rather than something to paper over.
            'paths' => $paths,
            'is_visible' => (bool) $category->getAttribute('is_visible'),
            'position' => (int) $category->getAttribute('position'),
            $this->kind()->countKey() => $count === null ? $category->itemCount() : (int) $count,
        ];

        if ($this->kind()->prefix === null) {
            // Always empty for such a kind, and an empty `paths` reads as "missing in every
            // language" — something to fix — rather than as "has none by design".
            unset($row['slug'], $row['paths']);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Model $category): array
    {
        /** @var Model&Category $category */
        $category->load(method_exists($category, 'routeCanonical') ? ['routes'] : []);

        return [$this->kind()->noun => $this->row($category), 'values' => $this->form->values($category)];
    }

    /**
     * By id, or by the slug an agent read off an address.
     *
     * @return Model&Category
     */
    private function find(mixed $key): Model
    {
        $query = ($this->model)::query();

        $found = match (true) {
            is_int($key), is_string($key) && ctype_digit($key) => $query->find((int) $key),
            is_string($key) && $key !== '' => $query->whereTranslationLikeAny($this->kind()->prefix === null ? 'title' : 'slug', $key)->first(),
            default => null,
        };

        if (! $found instanceof Category) {
            throw new ToolFailure("There is no {$this->kind()->noun} {$this->printable($key)}. {$this->kind()->plural}_list has them all.");
        }

        return $found;
    }

    /**
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
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function printable(mixed $key): string
    {
        return is_scalar($key) ? '"'.$key.'"' : '(none given)';
    }

    private function kind(): CategoryKind
    {
        return ($this->model)::categoryKind();
    }
}
