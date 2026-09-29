<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Seo;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Purchase\Purchasability;

/**
 * schema.org `Product` for a product's page (§10.3), with an `Offer` when there is something to
 * offer: prices switched on, a price written, and a currency the site named. A price without a
 * currency is a number a search engine cannot read, and it is left out rather than guessed.
 *
 * Videos of the gallery are its `subjectOf`, a `VideoObject` each (§6 of the video spec) — unless
 * the site switched videos off, and then the storefront says nothing about them at all.
 *
 * The trail is `module-seo`'s `BreadcrumbList`, from the product's crumbs, and not repeated here.
 */
final class ProductMarkup
{
    public function __construct(
        private readonly Config $config,
        private readonly Purchasability $purchasability,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function for(Product $product, string $locale): array
    {
        $block = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->displayName($locale),
            'url' => $product->url($locale),
            'sku' => $product->sku,
            'gtin' => (bool) $this->config->get('webx-catalog.fields.barcode', true) ? $product->barcode : null,
            'description' => $this->text($product->getTranslation('summary', $locale)),
            'image' => $product->images->map(static fn ($image): string => $image->url())->values()->all() ?: null,
            'category' => $product->category?->displayName($locale),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        $videos = $this->videos($product, $locale);

        if ($videos !== []) {
            $block['subjectOf'] = $videos;
        }

        $offer = $this->offer($product);

        if ($offer !== null) {
            $block['offers'] = $offer;
        }

        return [$block];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function offer(Product $product): ?array
    {
        $currency = $this->config->get('webx-catalog.price.currency');

        if (! (bool) $this->config->get('webx-catalog.price.enabled', true) || $product->price === null || ! is_string($currency) || $currency === '') {
            return null;
        }

        $verdict = $this->purchasability->for($product);

        return [
            '@type' => 'Offer',
            'price' => number_format((float) $product->price, 2, '.', ''),
            'priceCurrency' => strtoupper($currency),
            'availability' => $verdict->purchasable ? 'https://schema.org/InStock' : 'https://schema.org/Discontinued',
            'url' => $product->url(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function videos(Product $product, string $locale): array
    {
        if (! (bool) $this->config->get('webx-catalog.fields.video', true)) {
            return [];
        }

        $name = $product->displayName($locale);
        $videos = [];

        foreach ($product->images as $image) {
            $video = $image->videoData();

            if ($video === null) {
                continue;
            }

            $alt = $this->text($image->getTranslation('alt', $locale));

            $videos[] = array_filter([
                '@type' => 'VideoObject',
                'name' => $alt ?? $name,
                'description' => $this->text($product->getTranslation('summary', $locale)) ?? $name,
                'thumbnailUrl' => $image->url(),
                'uploadDate' => $image->created_at?->toAtomString(),
                'contentUrl' => $video['embed'] === null ? $video['url'] : null,
                'embedUrl' => $video['embed'],
                'duration' => $video['duration'] !== null ? self::duration($video['duration']) : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return $videos;
    }

    /** ISO 8601, the way schema.org reads a duration: `PT1M5S`. */
    private static function duration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return 'PT'.($hours > 0 ? $hours.'H' : '').($minutes > 0 ? $minutes.'M' : '').($rest > 0 || $seconds === 0 ? $rest.'S' : '');
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim(strip_tags($value)) : null;
    }
}
