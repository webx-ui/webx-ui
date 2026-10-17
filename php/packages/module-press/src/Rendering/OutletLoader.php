<?php

declare(strict_types=1);

namespace WebxUi\Press\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use WeakReference;
use WebxUi\Cache\CacheGroups;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;
use WebxUi\Routing\Models\Route;

/**
 * The outlets a reader of a language may see, loaded once a request for every `press()` of the
 * page (§4.8, WEBX_UI_PERFORMANCE_AUDIT.md #15).
 *
 * The page of the press asks for them many times over: the strip of logos, then the catalogue
 * twice a kind — once to know whether the group is empty, once to print it — and every asking
 * was the same three queries. They are four queries now whatever the page asks — the outlets,
 * their articles, the registry rows of their pages, and the library files the cards print — and
 * every listing narrows the loaded rows in PHP, where `kind` was already decided.
 *
 * Kept for the request, under the versions of the `press` and `routes` cache groups
 * (WEBX_UI_CACHE.md): an outlet or an article saved in this process moves `press`, a new
 * address moves `routes`, and the next `press()` loads the rows again; the next request loads
 * them again whatever happened, as every memo of a request does. Nothing is kept inside a
 * transaction, for the reason `CacheGroups::remember()` gives — a rehearsal that rolls back must
 * not be what the next reader sees.
 */
final class OutletLoader
{
    /** The group every outlet and article moves, and a mass write bumps by hand. */
    public const GROUP = 'press';

    /** @var array<string, EloquentCollection<int, Outlet>> by language */
    private array $loaded = [];

    /** The versions the loaded rows were read under. */
    private ?string $versions = null;

    /**
     * The request the rows were loaded for — a worker that serves many requests in one process
     * must not hand the second one the first one's rows.
     *
     * @var WeakReference<object>|null
     */
    private ?WeakReference $request = null;

    public function __construct(
        private readonly CacheGroups $groups,
        private readonly ConnectionResolverInterface $db,
        private readonly MediaFiles $files,
    ) {}

    /**
     * Every outlet seen in this language, in its own order, with its articles and the registry
     * row of its page in this language — what `Cards` builds an outlet's card from.
     *
     * @return EloquentCollection<int, Outlet>
     */
    public function outlets(string $locale): EloquentCollection
    {
        if ($this->db->connection()->transactionLevel() > 0) {
            return $this->load($locale);
        }

        $request = Container::getInstance()->bound('request') ? Container::getInstance()->make('request') : null;
        $versions = implode('.', $this->groups->versions(self::GROUP, CacheGroups::ROUTES));

        if ($this->versions !== $versions || $this->request?->get() !== $request) {
            $this->loaded = [];
            $this->versions = $versions;
            $this->request = is_object($request) ? WeakReference::create($request) : null;
        }

        return $this->loaded[$locale] ??= $this->load($locale);
    }

    /**
     * The same, by id — for a list of articles to hand each its outlet without a query.
     *
     * @return array<int, Outlet>
     */
    public function outletsById(string $locale): array
    {
        $byId = [];

        foreach ($this->outlets($locale) as $outlet) {
            $byId[(int) $outlet->getKey()] = $outlet;
        }

        return $byId;
    }

    /** @return EloquentCollection<int, Outlet> */
    private function load(string $locale): EloquentCollection
    {
        $canonical = static fn (Relation $routes) => $routes->where('locale', $locale)->where('kind', Route::CANONICAL);

        /** @var EloquentCollection<int, Outlet> $outlets */
        $outlets = Outlet::query()
            ->visibleIn($locale)
            ->ordered()
            ->with(['articles', 'routes' => $canonical])
            ->get();

        // The logos and the PDFs in one query of the library: the strip asks for the logos and
        // the feed for both, and each would otherwise ask for what the other had not.
        $paths = [];

        foreach ($outlets as $outlet) {
            $paths[] = $outlet->logoPath();

            foreach ($outlet->articles as $article) {
                /** @var Article $article */
                $paths[] = $article->filePath();
            }
        }

        $this->files->load(array_values(array_unique(array_filter($paths))));

        return $outlets;
    }
}
