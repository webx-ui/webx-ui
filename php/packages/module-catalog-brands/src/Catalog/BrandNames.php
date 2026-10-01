<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Catalog;

use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\RowError;
use WebxUi\CatalogBrands\Models\Brand;

/**
 * Every brand by the words a file may name it with — a slug in any language, the name in the
 * default one — read once a run (§4.4 of the exchange spec) and added to as the import creates.
 *
 * Case does not count. A slug wins over a name, a live brand over one in the bin, and a name two
 * live brands share is refused with both ids rather than guessed.
 */
final class BrandNames
{
    public const BAG = 'brands';

    /** @var array<string, int> lowercase slug → id */
    private array $slugs = [];

    /** @var array<string, list<int>> lowercase name → ids */
    private array $titles = [];

    /** @var array<int, string> id → name, for what a refusal says */
    private array $shown = [];

    /** @var array<int, true> */
    private array $trashed = [];

    /** @var array<int, true> */
    private array $ids = [];

    private function __construct(private readonly string $locale) {}

    public static function read(string $locale): self
    {
        $names = new self($locale);

        foreach (Brand::withTrashed()->orderBy('id')->get(['id', 'title', 'slug', 'deleted_at']) as $brand) {
            $names->add($brand);
        }

        return $names;
    }

    public static function of(ImportContext $context): self
    {
        return $context->remember(self::BAG, 'names', static fn (): self => self::read($context->defaultLocale));
    }

    public function add(Brand $brand): void
    {
        $id = (int) $brand->id;
        $this->ids[$id] = true;
        $slugs = $brand->getTranslations('slug');
        // The default language first: a slug two languages give two brands is the default one's.
        uksort($slugs, fn (string $a, string $b): int => ($b === $this->locale) <=> ($a === $this->locale));

        foreach ($slugs as $slug) {
            if (is_string($slug) && $slug !== '') {
                $this->slugs[mb_strtolower($slug)] ??= $id;
            }
        }

        $title = $brand->getTranslation('title', $this->locale, false);

        if (is_string($title) && trim($title) !== '') {
            $this->titles[mb_strtolower(trim($title))][] = $id;
            $this->shown[$id] = trim($title);
        }

        if ($brand->deleted_at !== null) {
            $this->trashed[$id] = true;
        }
    }

    /** A live brand of this id. */
    public function has(int $id): bool
    {
        return isset($this->ids[$id]) && ! isset($this->trashed[$id]);
    }

    /**
     * The brand these words name, or null when none does.
     *
     * @throws RowError
     */
    public function find(string $words): ?int
    {
        $key = mb_strtolower(trim($words));
        $id = $this->slugs[$key] ?? null;

        if ($id === null) {
            $ids = $this->titles[$key] ?? [];
            $live = array_values(array_filter($ids, fn (int $one): bool => ! isset($this->trashed[$one])));
            $ids = $live === [] ? $ids : $live;

            if (count($ids) > 1) {
                throw new RowError((string) __('webx-catalog-brands::exchange.ambiguous', ['name' => trim($words), 'ids' => '#'.implode(', #', $ids)]));
            }

            $id = $ids[0] ?? null;
        }

        if ($id !== null && isset($this->trashed[$id])) {
            throw new RowError((string) __('webx-catalog-brands::exchange.trashed', ['name' => trim($words)]));
        }

        return $id;
    }

    /** The name of a brand when it reads back as that brand alone; null when it would not. */
    public function unique(Brand $brand): ?string
    {
        $name = $this->shown[(int) $brand->id] ?? null;

        if ($name === null || str_starts_with($name, '#')) {
            return null;
        }

        $key = mb_strtolower($name);

        return count($this->titles[$key] ?? []) === 1 && ! isset($this->slugs[$key]) ? $name : null;
    }
}
