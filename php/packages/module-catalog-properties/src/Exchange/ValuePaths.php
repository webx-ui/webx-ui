<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Exchange;

use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\RowError;
use WebxUi\CatalogProperties\Catalog\ValueBook;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyValue;

/**
 * The values of one reference book as a file names them (decision 12 of the properties spec): the
 * name in the default language, a path of names through `/` in a tree — `BMW/3 Series/E90` — a
 * slug in any language, or `#id`. Case does not count.
 *
 * Read once a run per property and walked in memory, like the categories' paths; a value the import
 * creates is added, and a row that fails forgets the book. The export writes the path of names, and
 * `#id` wherever the path would not read back as the same value: a name with the separator of its
 * book in it (`/` in a tree, `;` in a book of several), a sister of the same name. A name at the
 * top wins over a slug that spells it, so a path of names always reads back as itself.
 */
final class ValuePaths
{
    /** @var array<int, array{parent: int, name: string}> */
    private array $nodes = [];

    /** @var array<int, array<string, list<int>>> parent id (0 — the top) → lowercase name → ids */
    private array $children = [];

    /** @var array<string, int> lowercase slug in any language → id */
    private array $slugs = [];

    public function __construct(private readonly Property $property, private readonly string $locale)
    {
        foreach (PropertyValue::query()->where('property_id', $property->id)->orderBy('lft')->get(['id', 'parent_id', 'title', 'slug']) as $value) {
            $this->add($value);
        }
    }

    public static function bag(Property $property): string
    {
        return 'property-values.'.$property->id;
    }

    /** The book of this property for the run, read the first time a row asks. */
    public static function of(Property $property, ImportContext $context): self
    {
        return $context->remember(self::bag($property), 'paths', static fn (): self => new self($property, $context->defaultLocale));
    }

    /**
     * @throws RowError
     */
    public function resolve(string $path, ImportContext $context): int
    {
        $path = trim($path);
        $named = ['property' => $this->property->displayName($this->locale)];

        if (preg_match('/^#(\d+)$/', $path, $match) === 1) {
            $id = (int) $match[1];

            return isset($this->nodes[$id]) ? $id : throw new RowError((string) __('webx-catalog-properties::exchange.unknown-id', [...$named, 'id' => $id]));
        }

        $segments = $this->property->is_tree ? explode('/', $path) : [$path];
        $segments = array_values(array_filter(array_map(trim(...), $segments), static fn (string $one): bool => $one !== ''));

        if ($segments === []) {
            throw new RowError((string) __('webx-catalog-properties::exchange.empty'));
        }

        // A slug names one value wherever it stands — the one an agent read off an address.
        if (count($segments) === 1 && ! isset($this->children[0][mb_strtolower($segments[0])]) && isset($this->slugs[mb_strtolower($segments[0])])) {
            return $this->slugs[mb_strtolower($segments[0])];
        }

        $parent = 0;

        foreach ($segments as $segment) {
            $found = $this->children[$parent][mb_strtolower($segment)] ?? [];

            if (count($found) > 1) {
                throw new RowError((string) __('webx-catalog-properties::exchange.ambiguous', [...$named, 'name' => $segment, 'ids' => '#'.implode(', #', $found)]));
            }

            $parent = $found[0] ?? $this->create($segment, $parent, $path, $context);
        }

        return $parent;
    }

    /** What the export writes for a value: its path of names, or `#id` when that would mislead. */
    public function path(int $id): string
    {
        $names = [];
        $current = $id;

        while ($current !== 0 && isset($this->nodes[$current])) {
            $node = $this->nodes[$current];
            $key = mb_strtolower($node['name']);
            $misleading = $node['name'] === ''
                || str_starts_with($node['name'], '#')
                || ($this->property->is_tree && str_contains($node['name'], '/'))
                || ($this->property->is_multiple && str_contains($node['name'], ';'))
                || count($this->children[$node['parent']][$key] ?? []) > 1;

            if ($misleading) {
                return '#'.$id;
            }

            array_unshift($names, $node['name']);
            $current = $node['parent'];
        }

        return $current === 0 && $names !== [] ? implode('/', $names) : '#'.$id;
    }

    /**
     * @throws RowError
     */
    private function create(string $name, int $parent, string $path, ImportContext $context): int
    {
        if (! $context->createMissing) {
            throw new RowError((string) __('webx-catalog-properties::exchange.unknown', ['property' => $this->property->displayName($this->locale), 'path' => $path]));
        }

        if (! $context->can('catalog.manage')) {
            throw RowError::because('create-forbidden', ['permission' => 'catalog.manage']);
        }

        try {
            // The reference book's own path, as the panel and the agent create values.
            $value = app(ValueBook::class)->create($this->property, [
                'title' => [$this->locale => $name],
                'parent_id' => $parent === 0 ? null : $parent,
            ]);
        } catch (ValidationException $refused) {
            throw new RowError((string) collect($refused->errors())->flatten()->first());
        }

        $context->made(self::bag($this->property));
        $this->add($value);

        return (int) $value->id;
    }

    private function add(PropertyValue $value): void
    {
        $id = (int) $value->id;
        $parent = (int) $value->getAttribute('parent_id');
        $title = $value->getTranslation('title', $this->locale, false);
        $name = is_string($title) ? trim($title) : '';

        $this->nodes[$id] = ['parent' => $parent, 'name' => $name];
        $this->children[$parent][mb_strtolower($name)][] = $id;

        foreach ($value->getTranslations('slug') as $slug) {
            if (is_string($slug) && $slug !== '') {
                $this->slugs[mb_strtolower($slug)] ??= $id;
            }
        }
    }
}
