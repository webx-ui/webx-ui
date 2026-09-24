<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Rendering;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use WebxUi\Localization\Locales;
use WebxUi\Recipes\Models\RecipeNutrient;
use WebxUi\Recipes\Seo\Trail;

/**
 * One screen of recipes, as `partials/catalog` prints it (§5.8): the cards, the row of nutrients
 * to narrow them by, the pages — or, as a showcase, the cards and a link to all of them.
 *
 * The index, a category page and the block all print this and nothing else, so a site that
 * publishes the fragment changes all three. Everything a link needs is worked out here, so the
 * fragment is markup and loops: no query, no request, no config.
 *
 * Without JavaScript it reads whole — the filter and the pages are links. Two catalogues on one
 * page share `?page=` and `?nutrient=`; keys per block would be a complication for a case sites
 * do not have.
 */
final readonly class Catalog
{
    /** The query parameter of the page. */
    public const PAGE = 'page';

    /** The query parameter of the nutrient filter. */
    public const NUTRIENT = 'nutrient';

    /** The two views of the block. */
    public const SHOWCASE = 'showcase';

    public const CATALOG = 'catalog';

    /** Columns when the block leaves it empty. */
    public const COLUMNS = 3;

    /**
     * @param  list<array<string, mixed>>  $items  The cards on this screen.
     * @param  list<array{id: int, title: string, url: string, active: bool}>  $nutrients  The filter; empty — no filter.
     * @param  list<array{page: int, url: string, current: bool}>  $pages  Every page; one or none — no pagination.
     */
    public function __construct(
        public array $items,
        public array $nutrients = [],
        public ?string $allUrl = null,
        public array $pages = [],
        public ?string $previous = null,
        public ?string $next = null,
        public int $page = 1,
        public int $total = 0,
        public int $columns = self::COLUMNS,
        public ?string $more = null,
        public bool $filtered = false,
    ) {}

    /**
     * A catalogue of these cards: narrowed to `?nutrient=`, cut into pages of `$perPage`,
     * showing `?page=`.
     *
     * @param  list<array<string, mixed>>  $cards  Every card, in order — the filter and the pages are worked out of them.
     */
    public static function paged(array $cards, int $perPage, ?Request $request = null, int $columns = self::COLUMNS): self
    {
        $request ??= self::request();
        $perPage = max(1, $perPage);

        $nutrient = self::number($request?->query(self::NUTRIENT));
        $page = max(1, self::number($request?->query(self::PAGE)) ?? 1);

        $chips = self::chips($cards, $nutrient, $request);

        // A nutrient nobody on this list is rich in is not a filter to honour: the chip row
        // would show nothing active over an empty page.
        $active = $nutrient !== null && in_array($nutrient, array_column($chips, 'id'), true) ? $nutrient : null;

        if ($active !== null) {
            $cards = array_values(array_filter(
                $cards,
                static fn (array $card): bool => in_array($active, array_column(is_array($card['nutrients'] ?? null) ? $card['nutrients'] : [], 'id'), true),
            ));
        }

        $total = count($cards);
        $last = max(1, (int) ceil($total / $perPage));
        $links = [];

        for ($number = 1; $number <= $last && $last > 1; $number++) {
            $links[] = ['page' => $number, 'url' => self::url($request, $active, $number), 'current' => $number === $page];
        }

        return new self(
            items: array_slice($cards, ($page - 1) * $perPage, $perPage),
            nutrients: $chips,
            allUrl: $chips === [] ? null : self::url($request, null, 1),
            pages: $links,
            previous: $page > 1 && $page <= $last ? self::url($request, $active, $page - 1) : null,
            next: $page < $last ? self::url($request, $active, $page + 1) : null,
            page: $page,
            total: $total,
            columns: $columns,
            filtered: $active !== null,
        );
    }

    /**
     * A handful of cards and a link to all of them — to whatever stands at the prefix: the index,
     * or the page that took its place.
     *
     * @param  list<array<string, mixed>>  $cards
     */
    public static function showcase(array $cards, int $columns = self::COLUMNS, ?string $locale = null): self
    {
        $locale ??= Container::getInstance()->make(Locales::class)->current();

        return new self(items: $cards, total: count($cards), columns: $columns, more: Trail::indexUrl($locale));
    }

    /**
     * What the block's template hands the fragment: its `wx-collection` value as the site reads
     * it, and the block's own settings. An empty view is the showcase — the block keeps only what
     * the editor touched.
     */
    public static function block(mixed $recipes, mixed $mode = null, mixed $perPage = null, mixed $columns = null): self
    {
        $cards = is_array($recipes) && is_array($recipes['items'] ?? null) ? array_values($recipes['items']) : [];
        $columns = self::number($columns) ?? self::COLUMNS;

        if ($mode === self::CATALOG) {
            $perPage = self::number($perPage) ?? (int) Container::getInstance()->make('config')->get('webx-recipes.per-page', 24);

            return self::paged($cards, $perPage, columns: $columns);
        }

        return self::showcase($cards, $columns);
    }

    /**
     * The same screen with other cards on it — the page worked out on light stand-ins, and
     * whole cards built for this page only.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function withItems(array $items): self
    {
        return new self($items, $this->nutrients, $this->allUrl, $this->pages, $this->previous, $this->next, $this->page, $this->total, $this->columns, $this->more, $this->filtered);
    }

    /** A page past the last one: a handler answers 404 rather than an empty catalogue. */
    public function outOfRange(): bool
    {
        return $this->page > 1 && $this->items === [];
    }

    /**
     * The nutrients the cards are rich in, in the order the editor dragged them into.
     *
     * @param  list<array<string, mixed>>  $cards
     * @return list<array{id: int, title: string, url: string, active: bool}>
     */
    private static function chips(array $cards, ?int $active, ?Request $request): array
    {
        $titles = [];

        foreach ($cards as $card) {
            foreach (is_array($card['nutrients'] ?? null) ? $card['nutrients'] : [] as $nutrient) {
                if (is_array($nutrient) && is_int($nutrient['id'] ?? null)) {
                    $titles[$nutrient['id']] = (string) ($nutrient['title'] ?? '');
                }
            }
        }

        if ($titles === []) {
            return [];
        }

        /** @var list<int> $order */
        $order = RecipeNutrient::query()->whereKey(array_keys($titles))->ordered()->pluck('id')->map(intval(...))->all();

        return array_map(static fn (int $id): array => [
            'id' => $id,
            'title' => $titles[$id],
            'url' => self::url($request, $id, 1),
            'active' => $id === $active,
        ], $order);
    }

    /** This address with the filter and the page set — and nothing for page one. */
    private static function url(?Request $request, ?int $nutrient, int $page): string
    {
        if ($request === null) {
            return '?'.http_build_query(array_filter([self::NUTRIENT => $nutrient, self::PAGE => $page > 1 ? $page : null]));
        }

        $query = $request->query();
        $query = is_array($query) ? $query : [];
        unset($query[self::NUTRIENT], $query[self::PAGE]);

        if ($nutrient !== null) {
            $query[self::NUTRIENT] = $nutrient;
        }

        if ($page > 1) {
            $query[self::PAGE] = $page;
        }

        return $request->url().($query === [] ? '' : '?'.http_build_query($query));
    }

    private static function number(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private static function request(): ?Request
    {
        $container = Container::getInstance();

        if (! $container->bound('request')) {
            return null;
        }

        $request = $container->make('request');

        return $request instanceof Request ? $request : null;
    }
}
