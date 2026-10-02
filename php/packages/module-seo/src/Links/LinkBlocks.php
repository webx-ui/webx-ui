<?php

declare(strict_types=1);

namespace WebxUi\Seo\Links;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Models\SeoLinkBlock;
use WebxUi\Seo\Models\SeoLinkItem;
use WebxUi\Seo\Targets\UrlTarget;
use WebxUi\Seo\Targets\UrlTargets;
use WebxUi\Settings\Settings;

/**
 * Interlinking as the site prints it and the panel lists it (§18.4).
 *
 * What a page prints is kept under a generation, like the sitemap: whether a link is broken
 * depends on rows of other modules — an entity unpublished, a category renamed — so nothing here
 * could name the keys to forget. The generation moves on every save that could matter, and the
 * old entries run out on their own.
 */
final class LinkBlocks
{
    /** @var array<string, bool> */
    private array $broken = [];

    public function __construct(
        private readonly UrlTargets $targets,
        private readonly Cache $cache,
        private readonly Config $config,
        private readonly Settings $settings,
    ) {}

    /**
     * The block of the page being answered: the entity the registry found for the request, or
     * the address when there is none. Null when the page has no active block or nothing in it
     * survives the check for broken links.
     *
     * @return array{heading: string, links: list<array{href: string, anchor: string}>}|null
     */
    public function forRequest(Request $request): ?array
    {
        $resolution = Resolution::of($request);
        $route = $resolution?->route;

        if ($route !== null && ! $route->isAlias()) {
            return $this->forDonor(new UrlTarget($route->locale, '/'.$route->path, $route->entity_type, $route->entity_id));
        }

        // A page a module answers with a route of its own: asked the way a saved address is, so
        // a block bound to the entity behind it is still found. Without the query — page two of
        // a list carries the block of the list.
        $current = $this->targets->current($request);
        [$path] = explode('?', $current->path, 2);

        try {
            $donor = $this->targets->resolve($path, $current->locale)->target;
        } catch (Throwable) {
            $donor = new UrlTarget($current->locale, $path);
        }

        return $this->forDonor($donor);
    }

    /**
     * @return array{heading: string, links: list<array{href: string, anchor: string}>}|null
     */
    public function forDonor(UrlTarget $donor): ?array
    {
        $load = function () use ($donor): array {
            $block = SeoLinkBlock::query()->where('is_active', true)->ofTarget($donor)->with('items')->first();

            if (! $block instanceof SeoLinkBlock) {
                return [];
            }

            $links = [];

            foreach ($block->items as $item) {
                $target = $item->target();
                $href = $this->isBroken($target) ? null : $this->targets->href($target);

                if ($href !== null) {
                    $links[] = ['href' => $href, 'anchor' => $item->anchor];
                }
            }

            return $links === [] ? [] : ['heading' => $block->heading, 'links' => $links];
        };

        try {
            $found = $this->caching()
                ? $this->cache->remember($this->key($donor), $this->ttl(), $load)
                : $load();
        } catch (Throwable) {
            // Not migrated yet, or a store that is down: a page without its links, not a 500.
            return null;
        }

        if (! is_array($found) || ! is_array($found['links'] ?? null) || $found['links'] === []) {
            return null;
        }

        /** @var list<array{href: string, anchor: string}> $links */
        $links = $found['links'];
        $heading = is_string($found['heading'] ?? null) && $found['heading'] !== '' ? $found['heading'] : $this->defaultHeading($donor->locale);

        return ['heading' => $heading, 'links' => $links];
    }

    /** `seo.links-heading` in the donor's language, then the word the module ships. */
    public function defaultHeading(string $locale): string
    {
        $setting = $this->settings->get('seo.links-heading', null, $locale);

        return is_string($setting) && trim($setting) !== ''
            ? trim($setting)
            : (string) trans('webx-seo::site.links-heading', [], $locale);
    }

    public function isBroken(UrlTarget $target): bool
    {
        $key = implode('|', $target->toRow());

        return $this->broken[$key] ??= $this->targets->isBroken($target);
    }

    /**
     * A block as the panel and an agent read it.
     *
     * @return array<string, mixed>
     */
    public function describe(SeoLinkBlock $block, bool $withItems = true): array
    {
        $donor = $block->target();
        $items = $block->items->map(fn (SeoLinkItem $item): array => $this->describeItem($item))->all();

        $described = [
            'id' => $block->id,
            'donor' => $this->describeTarget($donor),
            'heading' => $block->heading,
            'is_active' => $block->is_active,
            'links_count' => count($items),
            'broken_count' => count(array_filter($items, static fn (array $item): bool => $item['acceptor']['broken'] === true)),
            'updated_at' => $block->updated_at?->toAtomString(),
        ];

        if ($withItems) {
            $described['items'] = $items;
        }

        return $described;
    }

    /**
     * Every block, filtered the way the list screen asks: by part of the donor's address or of an
     * anchor, and to the ones with broken links. Worked out here rather than in SQL because the
     * address of a bound donor is its entity's current one, which no column holds.
     *
     * @return Collection<int, SeoLinkBlock>
     */
    public function search(?string $term = null, bool $brokenOnly = false): Collection
    {
        $term = $term === null ? '' : mb_strtolower(trim($term));

        return SeoLinkBlock::query()
            ->with('items')
            ->orderBy('id')
            ->get()
            ->filter(function (SeoLinkBlock $block) use ($term, $brokenOnly): bool {
                if ($term !== '') {
                    $address = mb_strtolower($this->targets->address($block->target()));
                    $anchors = $block->items->contains(static fn (SeoLinkItem $item): bool => str_contains(mb_strtolower($item->anchor), $term));

                    if (! str_contains($address, $term) && ! $anchors) {
                        return false;
                    }
                }

                return ! $brokenOnly || $block->items->contains(fn (SeoLinkItem $item): bool => $this->isBroken($item->target()));
            })
            ->values();
    }

    /**
     * The donors a heading is set on in bulk (§18.4): the ones picked, or every donor whose
     * address is the prefix or below it — `/catalog/appliances` covers `/catalog/appliances/x`
     * and not `/catalog/appliances-2`.
     *
     * @param  list<int>|null  $ids
     * @return Collection<int, SeoLinkBlock>
     */
    public function selection(?array $ids, ?string $prefix): Collection
    {
        if ($ids !== null && $ids !== []) {
            return SeoLinkBlock::query()->whereKey($ids)->orderBy('id')->get();
        }

        if ($prefix === null || trim($prefix) === '') {
            return new Collection;
        }

        $prefix = UrlNormaliser::normalise($prefix);

        return SeoLinkBlock::query()->orderBy('id')->get()->filter(function (SeoLinkBlock $block) use ($prefix): bool {
            $address = $this->targets->address($block->target());

            return $prefix === '/' || $address === $prefix || str_starts_with($address, $prefix.'/');
        })->values();
    }

    /**
     * Set one heading on the donors picked — or, as a preview, only count them.
     *
     * @param  Collection<int, SeoLinkBlock>  $blocks
     * @return array<string, mixed>
     */
    public function applyHeading(Collection $blocks, ?string $heading, bool $dryRun = true): array
    {
        $heading = $heading === null || trim($heading) === '' ? null : mb_substr(trim($heading), 0, 255);

        if (! $dryRun) {
            foreach ($blocks as $block) {
                $block->heading = $heading;
                $block->save();
            }
        }

        return [
            'ok' => true,
            'applied' => ! $dryRun,
            'count' => $blocks->count(),
            'heading' => $heading,
            'donors' => $blocks->map(fn (SeoLinkBlock $block): string => $this->targets->address($block->target()))->values()->all(),
        ];
    }

    /** Throw away what pages printed; called by whatever could change it. */
    public function refresh(): void
    {
        $this->broken = [];

        if (! $this->caching()) {
            return;
        }

        try {
            $this->cache->forever($this->generationKey(), bin2hex(random_bytes(6)));
        } catch (Throwable) {
            // A store that is down has nothing stale in it either.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function describeItem(SeoLinkItem $item): array
    {
        return [
            'id' => $item->id,
            'acceptor' => $this->describeTarget($item->target()),
            'anchor' => $item->anchor,
            'position' => $item->position,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function describeTarget(UrlTarget $target): array
    {
        return [
            // What a visitor gets now, and what was saved — the hint beside it when they differ.
            'url' => $this->targets->address($target),
            'saved_url' => $this->targets->savedAddress($target),
            'locale' => $target->locale,
            'entity_type' => $target->entityType,
            'entity_id' => $target->entityId,
            'broken' => $this->isBroken($target),
        ];
    }

    private function caching(): bool
    {
        return (bool) $this->config->get('webx-seo.cache.enabled', true);
    }

    private function ttl(): int
    {
        return (int) $this->config->get('webx-seo.cache.ttl', 86400);
    }

    private function key(UrlTarget $donor): string
    {
        return $this->base().'.'.$this->generation().'.'.md5(implode('|', $donor->toRow()));
    }

    private function generation(): string
    {
        $generation = $this->cache->get($this->generationKey());

        return is_string($generation) ? $generation : '0';
    }

    private function generationKey(): string
    {
        return $this->base().'.generation';
    }

    private function base(): string
    {
        return (string) $this->config->get('webx-seo.cache.key', 'webx.seo.rules').'.links';
    }
}
