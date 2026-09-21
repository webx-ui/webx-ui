<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Pages\Exceptions\PagesException;
use WebxUi\Pages\Http\Requests\PageRequest;
use WebxUi\Pages\Http\Resources\PageResource;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\Editors;
use WebxUi\Pages\Panel\PageForm;

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

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $trashed = $request->boolean('trashed');
        $flat = $request->boolean('flat');

        $items = match (true) {
            $trashed => $this->bin($search),
            $search !== '' => $this->matches($search, $request),
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
     * One page as its editor needs it: the row, the trail above it for the breadcrumbs, the
     * values of the described screen, and a link to the draft.
     */
    public function show(Request $request, Page $page, PageForm $form): JsonResponse
    {
        $page->loadMissing('routes')->loadCount('children');

        return ApiResponse::data($form->describe($page, $this->author($request)));
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
     * Thin on purpose: the form is a described screen, so what a page's values are is decided
     * by the description and checked by `ScreenValues` (§11). What is left here is the one
     * thing the screen cannot answer — whether this editor is writing over somebody else.
     */
    public function update(Request $request, Page $page, PageForm $form): JsonResponse
    {
        $sent = $request->input('revision');

        // A request that names no revision is one that did not read the page first — an import,
        // a script — and is let through: the check protects an editor from a surprise, and
        // there is no editor to surprise.
        if (is_string($sent) && $sent !== $form->revision($page)) {
            return $this->conflict($page, $request, $form);
        }

        $user = $request->user();
        $input = $request->input('values');

        $form->save(
            $page,
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
            $this->author($request),
        );

        $page->refresh()->loadMissing('routes')->loadCount('children');

        return ApiResponse::data($form->describe($page, $this->author($request)));
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
     * Somebody wrote to this page between the editor reading it and saving it (§6).
     *
     * 409 with the page as it now is, so the panel can say who changed it and offer to re-read
     * rather than quietly keeping one of the two edits. The same answer covers two editors and
     * an agent: what is stale is the request, not whoever made it.
     */
    private function conflict(Page $page, Request $request, PageForm $form): JsonResponse
    {
        $page->loadMissing('routes')->loadCount('children');
        $editor = Editors::of([$page])[(int) $page->getKey()] ?? null;

        return new JsonResponse([
            'message' => (string) __(
                $editor === null ? 'webx-pages::errors.conflict-anonymous' : 'webx-pages::errors.conflict',
                ['name' => $editor ?? ''],
            ),
            'data' => $form->describe($page, $this->author($request)),
        ], 409);
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
     * Pages whose name or address contains the term, in any language the site has.
     *
     * Ordered by `lft`, which is the order of the tree read top to bottom: matches from the
     * same branch stay together, and that is the only order a flat list of pages has.
     *
     * @return Collection<int, Page>
     */
    private function matches(string $term, Request $request): Collection
    {
        /** @var Collection<int, Page> $found */
        $found = $this->searching($this->listing($request), $term)
            ->orderBy('lft')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        return $found;
    }

    /**
     * Narrow a query to pages whose name or address contains the term. An empty term narrows
     * nothing.
     *
     * Lives apart from `matches()` because the bin is a search too: an editor who types in the
     * box expects the list under it to answer, and which list that is — the tree or the bin — is
     * not something the box knows about.
     *
     * Every language, not the one the panel is open in: the list draws the title a page has,
     * so a page titled in English alone is on the screen in a Russian panel and has to be
     * findable from it.
     *
     * @param  Builder<Page>  $query
     * @return Builder<Page>
     */
    private function searching(Builder $query, string $term): Builder
    {
        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(static function (Builder $nested) use ($like): void {
            // Each half through the package's own scope rather than a hand-written `title->en`:
            // how a translation is stored is `webx-ui/localization`'s to know, and a second
            // spelling of it here is one that drifts.
            $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
        });
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
     * The bin: what was deleted, newest first, narrowed by the term in the search box.
     *
     * Only the pages somebody actually deleted. The branch that went down with one of them is
     * restored with it and has no life of its own in here — listing it would offer an editor a
     * page they cannot bring back on its own.
     *
     * @return Collection<int, Page>
     */
    private function bin(string $term): Collection
    {
        $query = Page::onlyTrashed()->whereNull('trashed_with')->with('routes')->withBranchCount();

        /** @var Collection<int, Page> $trashed */
        $trashed = $this->searching($query, $term)
            ->orderByDesc('deleted_at')
            ->get();

        return $trashed;
    }

    /**
     * @return Builder<Page>
     */
    private function listing(Request $request): Builder
    {
        $query = Page::query()->with('routes')->withCount('children')->withBranchCount();

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
