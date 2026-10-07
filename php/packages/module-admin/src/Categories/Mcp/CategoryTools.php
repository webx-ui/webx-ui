<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories\Mcp;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Tool;

/**
 * One module's categories, for an agent: list, get, create, update, delete, reorder.
 *
 * The names carry no prefix of their own — the module's id puts one in front (`rubrics_list`,
 * `services_categories_update`), and what stands after it is what tells the tools apart in a
 * client that shows only that part (CLAUDE.md §4). The words are the module's, from its
 * {@see CategoryKind}: an agent is told about rubrics and articles, not categories and items.
 *
 * Every write goes through {@see CategoryForm} — the door the panel uses — so the screen checks
 * an agent's values exactly as it checks the editor's, and a project's field lands in `extra`.
 * A dry run is that same write, rolled back ({@see Rehearsal}): it is refused where the write
 * would be, and answers what the write would answer.
 *
 * A category is named by its title or its slug, so two by one name are a trap: the second one
 * is refused when it is made or renamed, and a name that already points at two is refused with
 * their ids rather than guessed.
 *
 * Needs `webx-ui/mcp`, which the frame does not require: a module that offers these tools
 * requires it, and nothing loads this class otherwise.
 */
final readonly class CategoryTools
{
    /**
     * @param  class-string<Model&Category>  $model
     * @param  string|null  $module  The id of the module the tools are served under — what their
     *                               names start with, so a refusal can name the list tool as the
     *                               agent sees it (`tariff_groups_list`, not `groups_list`).
     */
    public function __construct(
        private string $model,
        private CategoryForm $form,
        private ?string $module = null,
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

        return Arguments::strictAll([
            Tool::read(
                'list',
                "The {$many} in the order they stand in on the site: what each is called in every language, "
                .($addressed ? 'the address it answers at, ' : '')
                ."whether it is visible, and how many {$items} are in it. "
                .match (true) {
                    ! $addressed => "A {$one} has no address of its own: it is a way to pick and filter {$items}. ",
                    // A brand is one per product: an agent told about a main one would look for the rest.
                    $kind->single => '',
                    default => "The first {$one} of an item is its main one — the one in the breadcrumbs — so the order they are given in matters. ",
                }
                ."Read this before filing anything, and reuse a {$one} rather than making a near-duplicate.",
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'visible' => ['type' => 'boolean', 'description' => 'Only the ones a reader can reach. All of them when omitted.'],
                    'search' => ['type' => 'string', 'description' => 'Only the ones whose title or slug contains this.'],
                ]],
                permission: array_values(array_unique([...$kind->view, $kind->manage])),
            ),

            Tool::read(
                'get',
                "One {$one} in full: what the list says of it, and every value of its editor — the fields "
                .'the project added to the screen included.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->describe($this->find($arguments[$one] ?? null))),
                ['properties' => [$one => $category], 'required' => [$one]],
                permission: array_values(array_unique([...$kind->view, $kind->manage])),
            ),

            Tool::mutating(
                'create',
                "Make a new {$one} at the end of the list. "
                .($addressed
                    ? 'The address is made out of the title unless a slug is given; an address another page already answers at is refused, not quietly changed. '
                    : '')
                ."A title another {$one} already has, in any language, is refused. "
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
                "Put the {$many} in a new order — the order of the menu on the site. Name them in the order they "
                .'should stand in: they trade the places they hold among themselves, and one left out stays where it is. '
                ."An id that is not one of the {$many} is refused.",
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'The ids, first to last.'],
                ], 'required' => ['ids']],
                permission: $kind->manage,
            ),
        ], $this->module === null ? '' : str_replace('-', '_', $this->module).'_');
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
        $this->refuseTaken($arguments['title'] ?? null);

        $work = function () use ($arguments): array {
            $category = $this->form->create($this->model, $arguments['title'] ?? null, $arguments['slug'] ?? null);

            // Read back: a column the insert left to its default (`is_visible`) is not on the
            // model yet, and the answer would call a visible category hidden.
            return $this->describe($category->refresh());
        };

        if (! $this->dryRun($arguments)) {
            return $work();
        }

        $would = $this->rehearse($work);
        // The id the rehearsal was given is nobody's once it is rolled back.
        $would[$this->kind()->noun]['id'] = null;

        return ['dry_run' => true] + $would;
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

        Arguments::refuseUnknown($values, $this->fieldsOf($category), $this->toolName('update'));

        if (array_key_exists('title', $values)) {
            $this->refuseTaken($values['title'], $category);
        }

        $work = function () use ($category, $values, $user): array {
            $this->form->save(
                $category,
                $values,
                $user instanceof HasPermissions ? static fn (string $permission): bool => $user->hasPermission($permission) : null,
            );

            return $this->describe($category);
        };

        if (! $this->dryRun($arguments)) {
            return $work();
        }

        return ['dry_run' => true, 'fields' => array_keys($values)] + $this->rehearse($work);
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

        foreach ($ids as $id) {
            if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                throw new ToolFailure('`ids` is a list of ids — numbers, as '.$this->listTool().' gives them.');
            }
        }

        $ids = array_values(array_map(intval(...), $ids));
        // `Ordering` skips an id it does not know, which is right for a panel that has not seen the
        // newest row and wrong for an agent that typed one: it would be told the order was saved.
        $known = ($this->model)::query()->whereKey($ids)->pluck((new $this->model)->getKeyName())->map(intval(...))->all();
        $unknown = array_values(array_unique(array_diff($ids, $known)));

        if ($unknown !== []) {
            throw new ToolFailure(sprintf(
                'There is no %s #%s. %s has them all.',
                $this->kind()->noun,
                implode(', #', $unknown),
                $this->listTool(),
            ));
        }

        $work = function () use ($ids): array {
            Ordering::move($this->model, $ids);

            return $this->list([]);
        };

        if (! $this->dryRun($arguments)) {
            return $work() + ['dry_run' => false];
        }

        return $this->rehearse($work) + ['dry_run' => true];
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

    /** The list tool by the name the agent calls it. */
    private function listTool(): string
    {
        return $this->module === null
            ? 'The list of '.$this->kind()->plural
            : $this->toolName('list');
    }

    private function toolName(string $tool): string
    {
        return $this->module === null ? $tool : str_replace('-', '_', $this->module).'_'.$tool;
    }

    /**
     * By id, or by the slug an agent read off an address — the title, for a kind without one.
     * A name two of them answer to is refused with both ids: picking one is a guess.
     *
     * @return Model&Category
     */
    private function find(mixed $key): Model
    {
        $query = ($this->model)::query();

        if (is_int($key) || (is_string($key) && ctype_digit($key))) {
            $found = $query->find((int) $key);
        } elseif (is_string($key) && trim($key) !== '') {
            $matches = $query->whereTranslationLikeAny($this->kind()->prefix === null ? 'title' : 'slug', trim($key))->get();

            if ($matches->count() > 1) {
                throw new ToolFailure(sprintf(
                    'More than one %s is called %s: #%s. Name it by its id.',
                    $this->kind()->noun,
                    $this->printable($key),
                    $matches->map(static fn (Model $one): int => (int) $one->getKey())->implode(', #'),
                ));
            }

            $found = $matches->first();
        } else {
            $found = null;
        }

        if (! $found instanceof Category) {
            throw new ToolFailure("There is no {$this->kind()->noun} {$this->printable($key)}. {$this->listTool()} has them all.");
        }

        return $found;
    }

    /**
     * A title another one already carries, in any language and in any case, is refused: these are
     * picked by name — in the panel's filters and by agents — and two by one name is a filter that
     * picks the wrong one.
     *
     * @param  (Model&Category)|null  $except  The one being renamed.
     */
    private function refuseTaken(mixed $title, ?Model $except = null): void
    {
        $names = $this->names($title);

        if ($names === []) {
            return;
        }

        $clash = ($this->model)::query()->get()
            ->filter(fn (Model $other): bool => ($except === null || (int) $other->getKey() !== (int) $except->getKey())
                && array_intersect($names, $this->names($other->categoryValue('title'))) !== [])
            ->values();

        if ($clash->isEmpty()) {
            return;
        }

        throw new ToolFailure(sprintf(
            'A %s called [%s] already exists: #%s. Reuse it — %s has them all — or give this one another name.',
            $this->kind()->noun,
            implode('], [', array_values(array_intersect($names, $this->names($clash->first()->categoryValue('title'))))),
            $clash->map(static fn (Model $one): int => (int) $one->getKey())->implode(', #'),
            $this->listTool(),
        ));
    }

    /**
     * @return list<string>
     */
    private function names(mixed $title): array
    {
        $texts = is_array($title) ? $title : [$title];
        $names = [];

        foreach ($texts as $text) {
            if (is_string($text) && trim($text) !== '') {
                $names[] = mb_strtolower(trim($text));
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * What `values` may hold: the fields of the screen, which is everything the save reads.
     *
     * @param  Model&Category  $category
     * @return list<string>
     */
    private function fieldsOf(Model $category): array
    {
        $fields = array_map(
            static fn (array $node): string => (string) $node['name'],
            Container::getInstance()->make(ScreenRegistry::class)->fields($this->kind()->screen),
        );

        return array_values(array_unique([...$category->categoryFields(), ...$fields]));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function rehearse(Closure $work): mixed
    {
        return Rehearsal::run($work, (new $this->model)->getConnectionName());
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
