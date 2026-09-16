<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Localization\Locales;
use WebxUi\Pages\Exceptions\PagesException;
use WebxUi\Pages\Http\Requests\PageRequest;
use WebxUi\Pages\Http\Resources\PageResource;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\Editors;

/**
 * The section's list and the page as a record.
 *
 * The list answers one level of the tree at a time (§9): the catalogue of a real site is a few
 * hundred nodes, and a table that draws all of them to show twelve is a table nobody scrolls
 * twice. Searching is the one thing that breaks the shape — a flat list of matches with the
 * address under each, because branches drawn for the sake of one match deep inside them tell
 * the reader nothing.
 */
final class PageController
{
    /** As many matches as anybody reads before narrowing the search. */
    private const SEARCH_LIMIT = 50;

    /**
     * How much of the tree a flat list carries. Well past a real site's catalogue, and a stop
     * for the one that is not: a phone asking for ten thousand cards helps nobody.
     */
    private const FLAT_LIMIT = 500;

    public function index(Request $request, Locales $locales): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $trashed = $request->boolean('trashed');
        $flat = $request->boolean('flat');

        $items = match (true) {
            $trashed => $this->bin(),
            $search !== '' => $this->matches($search, $locales->current(), $request),
            $flat => $this->everything($request),
            default => $this->level($request),
        };

        // The home page travels beside the level rather than in it: it is pinned above the
        // list, and its children are the level (§9). Only where there is a tree to pin it to —
        // a search, the bin and a flat list are flat, and in the flat one the home page is
        // simply the first row, because `lft` reads the tree from the top.
        $home = $trashed || $flat || $search !== '' || $request->has('parent') ? null : $this->home();

        $editors = Editors::of($home === null ? $items : [...$items, $home]);

        return ApiResponse::data([
            'home' => $home === null ? null : new PageResource($home, $editors),
            'items' => $items->map(static fn (Page $page): PageResource => new PageResource($page, $editors))->values()->all(),
        ]);
    }

    /**
     * One page, with the trail above it: the editor's breadcrumbs, and the only way the panel
     * can name where a page sits without walking the tree itself.
     *
     * The values of the form are not here yet — they arrive with the described screen, and so
     * does the preview link (§11).
     */
    public function show(Page $page): JsonResponse
    {
        $page->loadMissing('routes')->loadCount('children');

        $ancestors = $page->pathFromRoot()->filter(static fn (Page $node): bool => ! $node->is($page));
        $editors = Editors::of([$page, ...$ancestors]);

        return ApiResponse::data([
            'page' => new PageResource($page, $editors),
            'ancestors' => $ancestors
                ->map(static fn (Page $node): PageResource => new PageResource($node, $editors))
                ->values()
                ->all(),
        ]);
    }

    public function store(PageRequest $request): JsonResponse
    {
        $parent = $this->parent($request->parentId());

        $page = new Page(['title' => $request->title(), 'slug' => $request->slug()]);
        $page->appendTo($parent);

        return ApiResponse::data(new PageResource($page->refresh()->loadMissing('routes')), 201);
    }

    /**
     * Save the draft.
     *
     * Thin on purpose: the form is a described screen, and what a page's values are is decided
     * there (§9, decision 9). Until it exists this accepts the three the section itself can
     * write, and the editor's own form replaces the input with `ScreenValues` and the revision
     * check of §6.
     */
    public function update(PageRequest $request, Page $page): JsonResponse
    {
        $locale = app(Locales::class)->current();

        // Whole maps rather than the one language: the draft is laid over the columns as it
        // is, and a bare string would arrive as "the current language" of whatever language the
        // publishing request happens to be in.
        $page->saveDraft([
            'title' => [...$page->getTranslations('title'), $locale => $request->title()],
            'slug' => $page->isRoot()
                ? $page->getTranslations('slug')
                : [...$page->getTranslations('slug'), $locale => $request->slug()],
        ], $this->author($request));

        return ApiResponse::data(new PageResource($page->refresh()->loadMissing('routes')));
    }

    /**
     * Into the bin, with everything under it (§7).
     *
     * How many went is in the answer because the panel has to say it: an editor who deleted
     * "Catalogue" has just taken two hundred addresses off the site, and finding that out from
     * a search engine a week later is the failure this sentence prevents.
     */
    public function destroy(Page $page): JsonResponse
    {
        $count = $page->descendants()->count() + 1;

        $page->delete();

        return ApiResponse::data(['trashed' => $count]);
    }

    /**
     * The level under a parent, or under the home page when no parent was named.
     *
     * @return Collection<int, Page>
     */
    private function level(Request $request): Collection
    {
        $parent = $request->query('parent');
        $parentId = is_numeric($parent) ? (int) $parent : $this->home()?->getKey();

        /** @var Collection<int, Page> $level */
        $level = $this->listing($request)
            ->where('parent_id', $parentId)
            ->orderBy('lft')
            ->get();

        return $level;
    }

    /**
     * Pages whose name or address contains the term, in the language the panel is open in.
     *
     * Ordered by `lft`, which is the order of the tree read top to bottom: matches from the
     * same branch stay together, and that is the only order a flat list of pages has.
     *
     * @return Collection<int, Page>
     */
    private function matches(string $term, string $locale, Request $request): Collection
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        /** @var Collection<int, Page> $found */
        $found = $this->listing($request)
            ->where(static function (Builder $query) use ($like, $locale): void {
                // Each half through the package's own scope rather than a hand-written
                // `title->en`: how a translation is stored is `webx-ui/localization`'s to know,
                // and a second spelling of it here is one that drifts.
                $query->where(static fn (Builder $nested): Builder => $nested->whereTranslationLike('title', $like, $locale))
                    ->orWhere(static fn (Builder $nested): Builder => $nested->whereTranslationLike('slug', $like, $locale));
            })
            ->orderBy('lft')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        return $found;
    }

    /**
     * Every page at once, in the order the tree reads top to bottom.
     *
     * What a phone asks for. A tree drawn as cards has neither indentation to read nor a
     * chevron to open, so at that width the section stops pretending to be one and shows a flat
     * list with each page's address under its name (§9, last paragraph) — which is the thing
     * that identifies a page anyway.
     *
     * @return Collection<int, Page>
     */
    private function everything(Request $request): Collection
    {
        /** @var Collection<int, Page> $all */
        $all = $this->listing($request)->orderBy('lft')->limit(self::FLAT_LIMIT)->get();

        return $all;
    }

    /**
     * The bin: what was deleted, newest first.
     *
     * Only the pages somebody actually deleted. The branch that went down with one of them is
     * restored with it and has no life of its own in here — listing it would offer an editor a
     * page they cannot bring back on its own.
     *
     * @return Collection<int, Page>
     */
    private function bin(): Collection
    {
        /** @var Collection<int, Page> $trashed */
        $trashed = Page::onlyTrashed()
            ->whereNull('trashed_with')
            ->with('routes')
            ->orderByDesc('deleted_at')
            ->get();

        return $trashed;
    }

    /**
     * @return Builder<Page>
     */
    private function listing(Request $request): Builder
    {
        $query = Page::query()->with('routes')->withCount('children');

        return $this->withStatus($query, (string) $request->query('status', ''));
    }

    /**
     * The three states of §2, decision 6, as the columns say them: never published · on the
     * site · on the site with edits waiting.
     *
     * @param  Builder<Page>  $query
     * @return Builder<Page>
     */
    private function withStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            Page::STATUS_DRAFT => $query->whereNull('published_at'),
            Page::STATUS_PUBLISHED => $query->whereNotNull('published_at')->whereNull('draft'),
            Page::STATUS_MODIFIED => $query->whereNotNull('published_at')->whereNotNull('draft'),
            default => $query,
        };
    }

    private function home(): ?Page
    {
        return Page::query()->roots()->with('routes')->withCount('children')->orderBy('lft')->first();
    }

    /** Where a new page goes: under the page that was named, or under the home page. */
    private function parent(?int $parentId): Page
    {
        if ($parentId === null) {
            $home = $this->home();

            if ($home === null) {
                throw PagesException::homeIsMissing();
            }

            return $home;
        }

        $parent = Page::withTrashed()->findOrFail($parentId);

        if ($parent->trashed()) {
            throw PagesException::parentIsInBin();
        }

        return $parent;
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
