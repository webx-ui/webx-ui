<?php

declare(strict_types=1);

namespace WebxUi\Pages\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;

/**
 * Pages, as something to link to (§3 of the menu spec).
 *
 * The hint is the page's place in the tree, which is the one thing a flat list of titles loses:
 * three pages called "Pricing" are the same word until you can see that one of them is under
 * "Services" and another under "Courses".
 *
 * `available` is this module's answer and nobody else's. The registry holds an address for a
 * draft page too — a slug is content, and it exists before the page is published — so anything
 * that read only `routes` would happily put a link to a page the site answers 404 for.
 */
final class PageLinkSource implements LinkSource
{
    public function type(): string
    {
        return 'page';
    }

    public function model(): string
    {
        return Page::class;
    }

    public function title(): string
    {
        return (string) __('webx-pages::module.title');
    }

    public function icon(): string
    {
        return 'file';
    }

    /** First, because on most sites a link is a link to a page. */
    public function order(): int
    {
        return 100;
    }

    public function permission(): string
    {
        return 'pages.view';
    }

    /**
     * @return list<LinkCandidate>
     */
    public function search(string $query, string $locale, int $limit): array
    {
        $pages = $this->query($locale)
            ->when($query !== '', fn (Builder $found): Builder => $this->matching($found, $query))
            // The order of the tree read top to bottom, so matches from one branch stay together
            // and the home page comes first.
            ->orderBy('lft')
            ->limit($limit)
            ->get();

        return array_values($this->candidates($pages, $locale));
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, LinkCandidate>
     */
    public function resolve(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->candidates($this->query($locale)->whereKey($ids)->get(), $locale);
    }

    /**
     * @return Builder<Page>
     */
    private function query(string $locale): Builder
    {
        // The addresses come with the rows: one query for a page of results rather than one per
        // result, which is the same reason the section's own list loads them.
        return Page::query()->with(['routes' => static fn ($routes) => $routes
            ->where('locale', $locale)
            ->where('kind', Route::CANONICAL)]);
    }

    /**
     * Every language, not the one the panel is open in: the list draws the title a page has, so
     * a page titled in English alone has to be findable from a Russian panel.
     *
     * @param  Builder<Page>  $query
     * @return Builder<Page>
     */
    private function matching(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(static function (Builder $nested) use ($like): void {
            $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
        });
    }

    /**
     * @param  EloquentCollection<int, Page>  $pages
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $pages, string $locale): array
    {
        $paths = $this->treePaths($pages, $locale);
        $candidates = [];

        foreach ($pages as $page) {
            $id = (int) $page->getKey();
            $canonical = $page->routes->first();

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: $this->label($page, $locale),
                url: $canonical instanceof Route ? $page->urlOf($canonical->path, $locale) : null,
                available: $page->isPublished() && $canonical instanceof Route,
                hint: $paths[$id] ?? null,
            );
        }

        return $candidates;
    }

    /**
     * Where each of these pages sits, as the titles above it.
     *
     * One query for the whole set rather than `pathFromRoot()` per row: twenty results would be
     * twenty round trips, and a picker types a letter at a time. The home page is left out of
     * the line — everything on the site is inside it, so saying so says nothing.
     *
     * @param  EloquentCollection<int, Page>  $pages
     * @return array<int, string>
     */
    private function treePaths(EloquentCollection $pages, string $locale): array
    {
        $wanted = $pages->filter(static fn (Page $page): bool => $page->depth > 1);

        if ($wanted->isEmpty()) {
            return [];
        }

        /** @var EloquentCollection<int, Page> $ancestors */
        $ancestors = Page::query()
            ->where(static function (Builder $query) use ($wanted): void {
                foreach ($wanted as $page) {
                    $query->orWhere(static function (Builder $above) use ($page): void {
                        $above->where('lft', '<', $page->lft)->where('rgt', '>', $page->rgt);
                    });
                }
            })
            ->where('depth', '>', 0)
            ->orderBy('lft')
            ->get();

        $hints = [];

        foreach ($wanted as $page) {
            $line = $ancestors
                ->filter(static fn (Page $above): bool => $above->lft < $page->lft && $above->rgt > $page->rgt)
                ->map(fn (Page $above): string => $this->label($above, $locale))
                ->all();

            if ($line !== []) {
                $hints[(int) $page->getKey()] = implode(' / ', $line);
            }
        }

        return $hints;
    }

    /**
     * The name to show, and something to show when there is none — the same three answers the
     * section's own list gives, so a page is called one thing throughout the panel.
     */
    private function label(Page $page, string $locale): string
    {
        if ($page->isRoot()) {
            return (string) __('webx-pages::pages.home');
        }

        foreach ([$page->getTranslation('title', $locale), $page->getTranslation('slug', $locale)] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$page->getKey();
    }
}
