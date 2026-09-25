<?php

declare(strict_types=1);

namespace WebxUi\Events\Rendering;

use Illuminate\Http\Request;

/**
 * One screen of events, as `partials/list` prints it (§4.4): the cards and the pages.
 *
 * The index and a category page both print this and nothing else, so a site that publishes the
 * fragment changes both. Everything a link needs is worked out here, so the fragment is markup
 * and loops: no query, no request, no config. The pages are links — without a script they still
 * work, and a search engine reads them the way a reader clicks through.
 */
final readonly class Listing
{
    /** The query parameter of the page. */
    public const PAGE = 'page';

    /**
     * @param  list<array<string, mixed>>  $items  The cards on this screen.
     * @param  list<array{page: int, url: string, current: bool}>  $pages  Every page; one or none — no pagination.
     */
    public function __construct(
        public array $items,
        public array $pages = [],
        public ?string $previous = null,
        public ?string $next = null,
        public int $page = 1,
        public int $total = 0,
        public int $last = 1,
    ) {}

    /**
     * The page of `$request` out of `$total` things, `$perPage` to a page — the cards come later,
     * built for this page only ({@see withItems()}).
     *
     * @param  list<mixed>  $all  Every item, in order: the page is cut out of them.
     * @return array{0: self, 1: list<mixed>} The screen, and the items on it.
     */
    public static function paged(array $all, int $perPage, Request $request): array
    {
        $perPage = max(1, $perPage);
        $raw = $request->query(self::PAGE);
        $page = is_string($raw) && ctype_digit($raw) && (int) $raw > 0 ? (int) $raw : 1;

        $total = count($all);
        $last = max(1, (int) ceil($total / $perPage));
        $links = [];

        for ($number = 1; $number <= $last && $last > 1; $number++) {
            $links[] = ['page' => $number, 'url' => self::url($request, $number), 'current' => $number === $page];
        }

        $shown = array_slice($all, ($page - 1) * $perPage, $perPage);

        return [new self(
            items: [],
            pages: $links,
            previous: $page > 1 && $page <= $last ? self::url($request, $page - 1) : null,
            next: $page < $last ? self::url($request, $page + 1) : null,
            page: $page,
            total: $total,
            last: $last,
        ), $shown];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function withItems(array $items): self
    {
        return new self($items, $this->pages, $this->previous, $this->next, $this->page, $this->total, $this->last);
    }

    /** A page past the last one: a handler answers 404 rather than an empty list. */
    public function outOfRange(): bool
    {
        return $this->page > $this->last;
    }

    /** This address with the page set — and nothing for page one. */
    private static function url(Request $request, int $page): string
    {
        $query = $request->query();
        $query = is_array($query) ? $query : [];
        unset($query[self::PAGE]);

        if ($page > 1) {
            $query[self::PAGE] = $page;
        }

        return $request->url().($query === [] ? '' : '?'.http_build_query($query));
    }
}
