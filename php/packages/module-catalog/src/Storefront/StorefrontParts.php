<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Product;

/**
 * The satellites' parts of the storefront, by point, and what each prepared for this page.
 *
 * A point in a template is still `@webxPart` — a site customises the whole point as a component
 * when it has blocks — and its fallback partial prints {@see render()}: every part registered for
 * the point, in the order the modules booted. So a site can take a point over entirely, and
 * without that the satellites add to it without knowing about each other.
 *
 * Prepared data belongs to one page, so each {@see prepare()} replaces the last one's whole: a
 * worker serving the next request from the same process must not print this one's badges.
 */
final class StorefrontParts
{
    public const POINTS = [
        'catalog.card.badges',
        'catalog.card.meta',
        'catalog.product.aside',
        'catalog.product.tabs',
        'catalog.product.unavailable',
        'catalog.listing.top',
        'catalog.listing.bottom',
    ];

    /** @var array<string, list<StorefrontPart>> */
    private array $parts = [];

    /** @var array<string, array<string, mixed>> part class → what it prepared */
    private array $prepared = [];

    /** @var array<int, true> the products of this page the parts were told about */
    private array $preparedIds = [];

    public function __construct(private readonly ViewFactory $views) {}

    public function register(StorefrontPart $part): void
    {
        $this->parts[$part->point()][] = $part;
    }

    /** @return list<StorefrontPart> */
    public function at(string $point): array
    {
        return $this->parts[$point] ?? [];
    }

    /**
     * Every part, told about the products of this page once.
     *
     * @param  Collection<int, Product>  $products
     */
    public function prepare(Collection $products): void
    {
        $this->prepared = [];
        $this->preparedIds = [];

        foreach ($this->parts as $parts) {
            foreach ($parts as $part) {
                $this->prepared[spl_object_hash($part)] = $part->prepare($products);
            }
        }

        $this->remember($products);
    }

    /**
     * More products for the same page — a strip of recommended ones over the list — told to every
     * part once: what they prepared for the page's own products stays, and what they prepare for
     * these is put beside it. A product already prepared is not asked about again.
     *
     * @param  Collection<int, Product>  $products
     */
    public function extend(Collection $products): void
    {
        $more = $products->reject(fn (Product $product): bool => isset($this->preparedIds[(int) $product->getKey()]))->values();

        if ($more->isEmpty()) {
            return;
        }

        foreach ($this->parts as $parts) {
            foreach ($parts as $part) {
                $this->prepared[spl_object_hash($part)] = array_replace_recursive($this->prepared[spl_object_hash($part)] ?? [], $part->prepare($more));
            }
        }

        $this->remember($more);
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function remember(Collection $products): void
    {
        foreach ($products as $product) {
            $this->preparedIds[(int) $product->getKey()] = true;
        }
    }

    /**
     * What the fallback partial of a point prints: each part's view in turn.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(string $point, array $data): string
    {
        $html = '';

        foreach ($this->at($point) as $part) {
            $html .= $this->views->make($part->view(), [...($this->prepared[spl_object_hash($part)] ?? []), ...$data])->render();
        }

        return $html;
    }
}
