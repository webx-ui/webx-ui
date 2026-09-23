<?php

declare(strict_types=1);

namespace WebxUi\Menu\Rendering;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\SiteUrls;
use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;

/**
 * A menu read out of the database and turned into the arrays the cache keeps.
 *
 * Two or three queries per menu, never one per item: the items in one walk, then one `resolve()`
 * per kind of entity they point at. A header is drawn on every page of the site, so asking each
 * item where it goes would be a query per link per request.
 *
 * Arrays and not models, because this is what goes into the cache: a serialised Eloquent model
 * carries attributes nobody reads, breaks when the class is renamed, and makes the record far
 * bigger than the four strings a link actually is.
 */
final readonly class Builder
{
    /**
     * `$site` is null on a panel with no address registry, and a hand-written path then travels
     * as it was written.
     */
    public function __construct(
        private LinkSources $sources,
        private ?SiteUrls $site = null,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function build(string $key, string $locale): array
    {
        $menu = Menu::query()->where('key', $key)->first();

        if (! $menu instanceof Menu) {
            // A template asking for a menu nobody has made yet gets an empty collection rather
            // than an exception: a header with no items beats a white page (§2, decision 10).
            return [];
        }

        /** @var EloquentCollection<int, MenuItem> $items */
        $items = MenuItem::query()->where('menu_id', $menu->getKey())->ordered()->get();

        return $this->nodes($items, $this->candidates($items, $locale), $locale, null);
    }

    /**
     * Every entity these items point at, one query per kind.
     *
     * @param  EloquentCollection<int, MenuItem>  $items
     * @return array<string, array<int, LinkCandidate>>
     */
    private function candidates(EloquentCollection $items, string $locale): array
    {
        $wanted = [];

        foreach ($items as $item) {
            $link = $item->link();

            if ($link->target !== LinkTarget::Entity || $link->entityType === null || $link->entityId === null) {
                continue;
            }

            $wanted[$link->entityType][$link->entityId] = $link->entityId;
        }

        $candidates = [];

        foreach ($wanted as $type => $ids) {
            $source = $this->sources->find($type);

            // A menu built while a module was installed and read after it was removed. The
            // items stay in the table — uninstalling a package is not a reason to rewrite
            // somebody's header — and they are simply not shown.
            $candidates[$type] = $source === null ? [] : $source->resolve(array_values($ids), $locale);
        }

        return $candidates;
    }

    /**
     * The children of one node, drawn out of the ordered walk.
     *
     * An item that is dropped takes its children with it rather than promoting them: what was
     * under "Services" is not something to show at the top level because the editor hid
     * "Services".
     *
     * @param  EloquentCollection<int, MenuItem>  $items
     * @param  array<string, array<int, LinkCandidate>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function nodes(EloquentCollection $items, array $candidates, string $locale, ?int $parentId): array
    {
        $nodes = [];

        foreach ($items as $item) {
            if ($item->parent_id !== $parentId) {
                continue;
            }

            $node = $this->node($item, $candidates, $locale);

            if ($node === null) {
                continue;
            }

            $node['children'] = $this->nodes($items, $candidates, $locale, (int) $item->getKey());
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @param  array<string, array<int, LinkCandidate>>  $candidates
     * @return array<string, mixed>|null
     */
    private function node(MenuItem $item, array $candidates, string $locale): ?array
    {
        if (! $item->visible || ! $this->speaks($item, $locale)) {
            return null;
        }

        $link = $item->link();
        $candidate = null;

        if ($link->target === LinkTarget::Entity) {
            if ($link->entityType === null || $link->entityId === null) {
                return null;
            }

            $candidate = $candidates[$link->entityType][$link->entityId] ?? null;

            // Not the registry's answer but the module's: a draft page has an address and is
            // still a page nobody can open (§2, decision 15).
            if (! $candidate instanceof LinkCandidate || ! $candidate->available) {
                return null;
            }
        }

        $label = $this->label($item, $candidate, $locale);

        // Nothing to write between the tags. An empty link in a header is worse than a missing
        // one, and this is what a half-translated site looks like from the render's side.
        if ($label === null) {
            return null;
        }

        $url = $this->url($link, $candidate, $locale);

        return [
            'label' => $label,
            'url' => $url,
            'path' => SitePath::of($url),
            'heading' => $item->is_heading,
            'variant' => $item->variant,
            'new_tab' => $item->new_tab,
            'rel' => $link->relAttribute(),
        ];
    }

    /** Is this item shown in the language being rendered? Empty `locales` means every one. */
    private function speaks(MenuItem $item, string $locale): bool
    {
        $locales = $item->locales;

        return $locales === null || $locales === [] || in_array($locale, $locales, true);
    }

    /**
     * What the item is called: its own label, else the name of the thing it points at.
     *
     * The item's own label is read without the fallback chain on purpose. Falling back would
     * print the English word on the Ukrainian page, which is the thing §7 refuses; the entity's
     * name is the source's business, and a source may well fall back there.
     */
    private function label(MenuItem $item, ?LinkCandidate $candidate, string $locale): ?string
    {
        $written = $item->getTranslation('title', $locale, false);

        if (is_string($written) && trim($written) !== '') {
            return trim($written);
        }

        if ($candidate instanceof LinkCandidate && trim($candidate->title) !== '') {
            return trim($candidate->title);
        }

        return null;
    }

    private function url(Link $link, ?LinkCandidate $candidate, string $locale): ?string
    {
        $address = match ($link->target) {
            LinkTarget::Entity => $candidate?->url,
            LinkTarget::Url => $link->url === null ? null : ($this->site?->to($link->url, $locale) ?? $link->url),
            LinkTarget::None => null,
        };

        if ($address === null) {
            // An anchor and nothing else: a link to a place on the page it is printed on.
            return $link->target === LinkTarget::Url && $link->hash !== null ? $link->fragment() : null;
        }

        return $address.$link->fragment();
    }
}
