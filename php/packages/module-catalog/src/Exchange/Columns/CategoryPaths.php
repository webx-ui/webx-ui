<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange\Columns;

use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\RowError;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Panel\CategoryForm;

/**
 * A category as a file names it (§7.1 of the exchange spec): the path of names from the root,
 * `Electronics/Phones`, in the default language and without regard to case — or `#12`.
 *
 * The tree is read once — id, parent and name of every live category — and walked in memory; a
 * category the file creates is added to it, and a row that fails forgets the tree, which is read
 * again for the next one. A path that two sisters of one name make ambiguous is refused with both
 * ids, and the export writes `#id` for any category whose path would not read back as itself: a
 * name with `/` or `;` in it, or a sister of the same name.
 */
final class CategoryPaths
{
    public const BAG = 'categories';

    /** How many numbered slugs a new category tries before its row fails. */
    private const SLUG_ATTEMPTS = 20;

    /** @var array<int, array{parent: int, name: string}> */
    private array $nodes = [];

    /** @var array<int, array<string, list<int>>> parent id (0 — the root) → lowercase name → ids */
    private array $children = [];

    public function __construct(private readonly string $locale)
    {
        foreach (Category::query()->orderBy('lft')->get(['id', 'parent_id', 'name']) as $category) {
            $name = $category->getTranslation('name', $this->locale, false);
            $this->add((int) $category->id, (int) $category->parent_id, is_string($name) ? trim($name) : '');
        }
    }

    /** The tree of this run, read the first time a row asks. */
    public static function of(ImportContext $context): self
    {
        return $context->remember(self::BAG, 'tree', static fn (): self => new self($context->defaultLocale));
    }

    /**
     * @throws RowError
     */
    public function resolve(string $path, ImportContext $context): int
    {
        $path = trim($path);

        if (preg_match('/^#(\d+)$/', $path, $match) === 1) {
            $id = (int) $match[1];

            return isset($this->nodes[$id]) ? $id : throw RowError::because('category-unknown-id', ['id' => $id]);
        }

        $parent = 0;
        $segments = array_values(array_filter(array_map(trim(...), explode('/', $path)), static fn (string $one): bool => $one !== ''));

        if ($segments === []) {
            throw RowError::because('category-empty');
        }

        foreach ($segments as $segment) {
            $found = $this->children[$parent][mb_strtolower($segment)] ?? [];

            if (count($found) > 1) {
                throw RowError::because('category-ambiguous', ['name' => $segment, 'ids' => '#'.implode(', #', $found)]);
            }

            $parent = $found[0] ?? $this->create($segment, $parent, $path, $context);
        }

        return $parent;
    }

    /** What the export writes for a category: its path, or `#id` when the path would mislead. */
    public function path(int $id): string
    {
        $names = [];
        $current = $id;

        while ($current !== 0 && isset($this->nodes[$current])) {
            $node = $this->nodes[$current];

            if ($node['name'] === '' || strpbrk($node['name'], '/;#') !== false || count($this->children[$node['parent']][mb_strtolower($node['name'])] ?? []) > 1) {
                return '#'.$id;
            }

            array_unshift($names, $node['name']);
            $current = $node['parent'];
        }

        return $current === 0 && $names !== [] ? implode('/', $names) : '#'.$id;
    }

    private function create(string $name, int $parent, string $path, ImportContext $context): int
    {
        if (! $context->createMissing) {
            throw RowError::because('category-missing', ['path' => $path]);
        }

        if (! $context->can('catalog.manage')) {
            throw RowError::because('create-forbidden', ['permission' => 'catalog.manage']);
        }

        // Published: an import files products under it, and a hidden category would take them off
        // the site without anybody having decided so. Slugs are flat while names repeat down the
        // tree — «Gaskets» under two branches — so a taken slug gets a number rather than failing
        // every row of the second branch.
        $form = app(CategoryForm::class);
        $base = str($name)->slug('-', $this->locale)->toString();

        for ($attempt = 1; ; $attempt++) {
            $values = ['name' => [$this->locale => $name], 'is_published' => true];

            if ($attempt > 1 && $base !== '') {
                $values['slug'] = [$this->locale => $base.'-'.$attempt];
            }

            try {
                $category = $form->create($values, $parent === 0 ? null : $parent);

                break;
            } catch (ValidationException $taken) {
                if ($attempt >= self::SLUG_ATTEMPTS || $base === '' || ! self::aboutSlug($taken)) {
                    throw $taken;
                }
            }
        }

        $context->made(self::BAG);
        $this->add((int) $category->id, $parent, $name);

        return (int) $category->id;
    }

    private static function aboutSlug(ValidationException $refused): bool
    {
        foreach (array_keys($refused->errors()) as $field) {
            if ($field === 'slug' || str_starts_with($field, 'slug.')) {
                return true;
            }
        }

        return false;
    }

    private function add(int $id, int $parent, string $name): void
    {
        $this->nodes[$id] = ['parent' => $parent, 'name' => $name];
        $this->children[$parent][mb_strtolower($name)][] = $id;
    }
}
