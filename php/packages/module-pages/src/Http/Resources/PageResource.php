<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Localization\Locales;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;

/**
 * One row of the section: what a page is called, where it is, what state it is in, and what may
 * be done to it.
 *
 * Two answers that look like one and are not. The title is the draft's — what the editor is
 * working on, which is what they look for in a list — while the address is the registry's,
 * because that is what the site answers at right now. A page renamed in a draft and not yet
 * published shows its new name and its old address, and that is the truth rather than a bug.
 *
 * @mixin Page
 */
final class PageResource extends JsonResource
{
    /**
     * @param  array<int, string>  $editors  Page id → who wrote its newest version, for the list at once.
     */
    public function __construct(Page $page, private readonly array $editors = [])
    {
        parent::__construct($page);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Page $page */
        $page = $this->resource;

        $locale = $this->locale();
        $shown = $page->hasDraft() ? $page->withDraft() : $page;
        $canonical = $this->canonical($page, $locale);
        $id = (int) $page->getKey();

        return [
            'id' => $id,
            'parent_id' => $page->parent_id,
            'depth' => $page->depth,
            'is_home' => $page->isRoot(),
            'title' => $this->title($shown, $locale),
            'slug' => (string) $shown->getTranslation('slug', $locale, fallback: false),
            // Null rather than an empty string when the page names no slug in this language:
            // the two mean different things, and only the home page is legitimately at `''`.
            'path' => $canonical?->path,
            'url' => $canonical === null ? null : $page->url($locale),
            'status' => $page->status(),
            'published_at' => $page->published_at?->toAtomString(),
            'updated_at' => $page->updated_at?->toAtomString(),
            'edited_by' => $this->editors[$id] ?? null,
            // Present only where the query counted it; a row from the bin has no level below
            // it to open, and `0` there would be a claim rather than a silence.
            'children_count' => (int) ($page->getAttribute('children_count') ?? 0),
            // The whole branch, which is what a delete takes (§7) — and it is arithmetic on the
            // bounds rather than a query, because that is what a nested set is for.
            'descendants_count' => intdiv($page->rgt - $page->lft - 1, 2),
            'deleted_at' => $page->deleted_at?->toAtomString(),
            'trashed_with' => $page->trashed_with,
            'can' => $page->capabilities(),
        ];
    }

    /**
     * The name to show, and something to show when there is none.
     *
     * A page created in another language and never translated into this one would otherwise be
     * an empty row nobody can click on the right part of; the slug is what the editor called it
     * somewhere, and failing that its number is at least an identity.
     */
    private function title(Page $page, string $locale): string
    {
        foreach ([$page->getTranslation('title', $locale), $page->getTranslation('slug', $locale)] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$page->getKey();
    }

    /**
     * The address the registry holds for this language, out of what was loaded with the page.
     *
     * Read from the relation rather than asked for row by row: a level of the tree is one
     * query for every address in it, and the same rows answer `has an address in this language
     * at all`, which §8 makes a real state and not an accident.
     */
    private function canonical(Page $page, string $locale): ?Route
    {
        if (! $page->relationLoaded('routes')) {
            return $page->routeCanonical($locale);
        }

        return $page->routes
            ->first(static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL);
    }

    /** The language the panel is asking in, normalised the way the registry stores it. */
    private function locale(): string
    {
        return app(Locales::class)->current();
    }
}
