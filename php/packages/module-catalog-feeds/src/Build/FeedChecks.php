<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\Templates\Cdata;

/**
 * The third layer of the selection and the warnings of the journal (§5.3, §7.6 of the feeds spec).
 *
 * A reason takes a product out, and there are three of them by decision 15. A warning leaves it in,
 * and only speaks about what the feed prints: a template without a brand has no missing brand.
 */
final class FeedChecks
{
    public const NO_PRICE = 'no_price';

    public const NO_IMAGE = 'no_image';

    public const NO_NAME = 'no_name';

    /** Below this on either side a marketplace calls a picture small. */
    private const SMALL_IMAGE = 500;

    /** Two names of one value: a template prints either. */
    private const PLATFORM_CATEGORY = ['platform_category', 'google_product_category'];

    private const DESCRIPTIONS = ['description_html', 'description_cdata', 'description_text', 'long_text'];

    /** What a warning reads besides what it speaks about: `long_text` is cut from this one. */
    public const COMPANIONS = ['long_text' => 'description_text'];

    /** Why this product stays out, null when it goes in. The gallery is loaded by the page. */
    public static function reason(Product $product, string $locale): ?string
    {
        if ($product->price === null || (float) $product->price <= 0) {
            return self::NO_PRICE;
        }

        if (self::picture($product) === null) {
            return self::NO_IMAGE;
        }

        return self::name($product, $locale) === '' ? self::NO_NAME : null;
    }

    /**
     * @param  array<string, mixed>  $row  the values of the product, the ones the template uses
     * @return list<string>
     */
    public static function warnings(Product $product, array $row, string $locale): array
    {
        $warnings = [];

        if (($row['brand'] ?? null) === '') {
            $warnings[] = 'no_brand';
        }

        $descriptions = array_intersect_key($row, array_flip(self::DESCRIPTIONS));

        if ($descriptions !== [] && array_filter($descriptions, static fn (mixed $text): bool => self::text($text) !== '') === []) {
            $warnings[] = 'no_description';
        }

        if (($row['gtin'] ?? null) === '' && trim((string) $product->barcode) !== '') {
            $warnings[] = 'invalid_gtin';
        }

        if ((array_key_exists('image_link', $row) || array_key_exists('images', $row)) && self::small(self::picture($product))) {
            $warnings[] = 'small_image';
        }

        if (self::cut($product, $row, $locale)) {
            $warnings[] = 'text_cut';
        }

        if (in_array('', array_intersect_key($row, array_flip(self::PLATFORM_CATEGORY)), true)) {
            $warnings[] = 'unmapped_category';
        }

        return $warnings;
    }

    /** The first picture of the gallery; a video has a poster, and it does not count. */
    private static function picture(Product $product): ?ProductImage
    {
        foreach ($product->images as $image) {
            /** @var ProductImage $image */
            if (! $image->hasVideo()) {
                return $image;
            }
        }

        return null;
    }

    private static function small(?ProductImage $image): bool
    {
        return $image !== null && $image->width !== null && $image->height !== null
            && ($image->width < self::SMALL_IMAGE || $image->height < self::SMALL_IMAGE);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function cut(Product $product, array $row, string $locale): bool
    {
        if (array_key_exists('title', $row) && mb_strlen(self::name($product, $locale)) > mb_strlen(self::text($row['title']))) {
            return true;
        }

        return array_key_exists('long_text', $row) && mb_strlen(self::text($row['description_text'] ?? '')) > mb_strlen(self::text($row['long_text']));
    }

    /** Strictly in this language: `displayName()` would give `#id`, a fallback another language. */
    private static function name(Product $product, string $locale): string
    {
        $name = $product->getTranslation('name', $locale, false);

        return is_string($name) ? trim($name) : '';
    }

    private static function text(mixed $value): string
    {
        return $value instanceof Cdata ? $value->toText() : (is_string($value) ? $value : '');
    }
}
