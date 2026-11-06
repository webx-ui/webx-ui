<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Variables;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection as Models;
use Illuminate\Support\Collection;
use WebxUi\Admin\Contracts\AssetUrls;
use WebxUi\Admin\Screens\Types\Html as RichText;
use WebxUi\Admin\Shortcodes\PageText;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Catalog\Exchange\Columns\CategoryColumn;
use WebxUi\Catalog\Exchange\Columns\CategoryPaths;
use WebxUi\Catalog\Exchange\Columns\ImagesColumn;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductBarcode;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\Catalog\Support\RequestMemo;
use WebxUi\Templates\Cdata;
use WebxUi\Templates\Html;
use WebxUi\Templates\Variable;

/**
 * The variables of the core (§4.2–§4.3 of the feeds spec) that are more than a column of the
 * exchange. What several of them read — the description, the tree of categories — is worked out
 * once a page, on the memo {@see ProductVariables::values()} forgets when the page is done.
 *
 * Addresses do not encode anything: a slug is ASCII by the rule of the platform.
 *
 * `availability`, `platform_category`, `google_product_category` and `custom_label_*` are settled
 * by the settings of a feed, which overrides them; here they say what a page has without one.
 */
final readonly class CoreVariables
{
    private const TITLE = 150;

    private const LONG_TEXT = 5000;

    private const PRODUCT_TYPE = 750;

    private const ADDITIONAL_IMAGES = 10;

    private const GTINS = 10;

    private const CUSTOM_LABELS = 5;

    public function __construct(private Container $container, private Config $config) {}

    public function register(ProductVariables $variables): void
    {
        $mpn = $this->variable('mpn', 'WX-9001', fn (Product $product): string => $this->on('mpn') ? (string) $product->mpn : '');

        $variables->register($mpn);
        $variables->register(new IdentifierExists($mpn));
        $variables->fallback($this->variable('availability', 'in_stock', static fn (): string => 'in_stock'));
        // A preset names it on any site; only the brands satellite has something to say.
        $variables->fallback($this->variable('brand', '', static fn (): string => ''));

        foreach ([...$this->identity(), ...$this->addresses(), ...$this->prices(), ...$this->texts(), ...$this->codes(), ...$this->places(), ...$this->reserved()] as $variable) {
            $variables->register($variable);
        }
    }

    /** @return list<ProductVariable> */
    private function identity(): array
    {
        return [
            $this->variable('id', '1024', static fn (Product $product): string => (string) $product->id),
            // Empty until the variants of webx-ui#66 fill it: the name stays, so no template changes.
            $this->variable('group_id', '', static fn (): string => ''),
        ];
    }

    /** @return list<ProductVariable> */
    private function addresses(): array
    {
        $pictures = $this->pictures(...);

        return [
            new ComputedVariable(
                new Variable('url', 'webx-catalog::variables.url', 'https://example.com/brake-pads'),
                static function (Collection $products, string $locale): array {
                    self::load($products, ['routes']);

                    return self::each($products, static fn (Product $product): string => $product->hasUrlIn($locale) ? $product->listedUrl($locale) : '');
                },
            ),
            new ComputedVariable(
                new Variable('image_link', 'webx-catalog::variables.image_link', 'https://example.com/storage/brake-pads.jpg'),
                static fn (Collection $products, string $locale): array => self::each($products, static fn (Product $product): string => $pictures($product, $products, $locale)[0]['url'] ?? ''),
            ),
            new ComputedVariable(
                self::pictureList('images'),
                static fn (Collection $products, string $locale): array => self::each($products, static fn (Product $product): array => $pictures($product, $products, $locale)),
            ),
            new ComputedVariable(
                self::pictureList('additional_images'),
                static fn (Collection $products, string $locale): array => self::each(
                    $products,
                    static fn (Product $product): array => array_slice($pictures($product, $products, $locale), 1, self::ADDITIONAL_IMAGES),
                ),
            ),
        ];
    }

    /** @return list<ProductVariable> */
    private function prices(): array
    {
        $price = fn (Product $product): ?float => $this->priced() && $product->price !== null ? (float) $product->price : null;
        $old = fn (Product $product): ?float => $this->priced() && $product->old_price !== null ? (float) $product->old_price : null;
        $list = static fn (Product $product): ?float => $old($product) !== null && $old($product) > $price($product) ? $old($product) : $price($product);
        $sale = static fn (Product $product): ?float => $old($product) !== null && $old($product) > $price($product) ? $price($product) : null;

        return [
            $this->variable('price', '1200.00', static fn (Product $product): string => self::number($price($product))),
            $this->variable('old_price', '1500.00', static fn (Product $product): string => self::number($old($product))),
            $this->variable('price_with_currency', '1200.00 USD', fn (Product $product): string => $this->withCurrency($price($product))),
            $this->variable('list_price', '1500.00', static fn (Product $product): string => self::number($list($product))),
            $this->variable('list_price_with_currency', '1500.00 USD', fn (Product $product): string => $this->withCurrency($list($product))),
            $this->variable('sale_price', '1200.00', static fn (Product $product): string => self::number($sale($product))),
            $this->variable('sale_price_with_currency', '1200.00 USD', fn (Product $product): string => $this->withCurrency($sale($product))),
            // Empty until there is an engine of promotions (decision 14): a sale without dates always holds.
            $this->variable('sale_price_effective_date', '', static fn (): string => ''),
        ];
    }

    /** @return list<ProductVariable> */
    private function texts(): array
    {
        $html = $this->descriptions(...);
        $plain = static fn (Collection $products, string $locale): array => array_map(Html::plain(...), $html($products, $locale));
        $summary = fn (Product $product, string $locale): string => $this->container->make(PageText::class)->plain(self::text($product, 'summary', $locale));

        return [
            new ComputedVariable(
                new HtmlVariable('description_html', 'webx-catalog::variables.description_html', '<p>Ceramic brake pads.</p>'),
                static fn (Collection $products, string $locale): array => $html($products, $locale),
            ),
            new ComputedVariable(
                new HtmlVariable('description_cdata', 'webx-catalog::variables.description_cdata', '<p>Ceramic brake pads.</p>'),
                static fn (Collection $products, string $locale): array => array_map(Cdata::of(...), $html($products, $locale)),
            ),
            new ComputedVariable(
                new Variable('description_text', 'webx-catalog::variables.description_text', 'Ceramic brake pads.'),
                static fn (Collection $products, string $locale): array => $plain($products, $locale),
            ),
            new ComputedVariable(
                new Variable('summary', 'webx-catalog::product.summary', 'Front axle set.'),
                static fn (Collection $products, string $locale): array => self::each($products, static fn (Product $product): string => $summary($product, $locale)),
            ),
            new ComputedVariable(
                new Variable('title', 'webx-catalog::variables.title', 'Ceramic brake pads'),
                static fn (Collection $products, string $locale): array => self::each($products, static fn (Product $product): string => self::cut(self::text($product, 'name', $locale), self::TITLE)),
            ),
            new ComputedVariable(
                new Variable('long_text', 'webx-catalog::variables.long_text', 'Ceramic brake pads.'),
                static function (Collection $products, string $locale) use ($plain, $summary): array {
                    $texts = $plain($products, $locale);

                    return self::each($products, static function (Product $product) use ($texts, $summary, $locale): string {
                        $text = $texts[(int) $product->id] ?? '';

                        return self::cut($text !== '' ? $text : $summary($product, $locale), self::LONG_TEXT);
                    });
                },
            ),
        ];
    }

    /** @return list<ProductVariable> */
    private function codes(): array
    {
        return [
            $this->variable('gtin', '4006381333931', static fn (Product $product): string => (string) Gtin::main($product)),
            new ComputedVariable(
                new Variable('gtins', 'webx-catalog::variables.gtins', [['value' => '4006381333931']], ['value' => 'webx-catalog::variables.fields.value']),
                static function (Collection $products): array {
                    self::load($products, Product::barcodeRelations());

                    return self::each($products, static function (Product $product): array {
                        $gtins = array_values(array_unique(array_filter(array_map(Gtin::of(...), $product->allBarcodes()))));

                        return array_map(static fn (string $gtin): array => ['value' => $gtin], array_slice($gtins, 0, self::GTINS));
                    });
                },
            ),
            new ComputedVariable(
                new Variable('barcodes', 'webx-catalog::product.barcodes', [['value' => '4006381333931']], ['value' => 'webx-catalog::variables.fields.value']),
                static function (Collection $products): array {
                    $relations = Product::barcodeRelations();
                    self::load($products, $relations);

                    return self::each($products, static fn (Product $product): array => $relations === [] ? [] : $product->barcodes
                        ->map(static fn (ProductBarcode $row): array => ['value' => $row->barcode])
                        ->values()
                        ->all());
                },
            ),
        ];
    }

    /** @return list<ProductVariable> */
    private function places(): array
    {
        return [
            new ComputedVariable(
                new Variable('category', 'webx-catalog::product.category', 'Brakes/Pads'),
                static fn (Collection $products, string $locale): array => self::each(
                    $products,
                    static fn (Product $product): string => $product->category_id === null ? '' : self::tree($locale)->path((int) $product->category_id),
                ),
            ),
            new ComputedVariable(
                new Variable('categories', 'webx-catalog::product.categories', 'Brakes/Pads;Sale'),
                static function (Collection $products, string $locale): array {
                    $extra = CategoryColumn::extra($products);

                    return self::each($products, static fn (Product $product): string => implode(';', array_map(
                        self::tree($locale)->path(...),
                        $extra[(int) $product->id] ?? [],
                    )));
                },
            ),
            $this->variable('category_id', '12', static fn (Product $product): string => (string) $product->category_id),
            new ComputedVariable(
                new Variable('product_type', 'webx-catalog::variables.product_type', 'Brakes > Pads'),
                static fn (Collection $products, string $locale): array => self::each($products, static fn (Product $product): string => $product->category_id === null
                    ? ''
                    : mb_substr(implode(' > ', self::tree($locale)->names((int) $product->category_id)), 0, self::PRODUCT_TYPE)),
            ),
        ];
    }

    /** @return list<ProductVariable> */
    private function reserved(): array
    {
        $empty = static fn (): string => '';
        $variables = [
            $this->variable('platform_category', '2556', $empty),
            $this->variable('google_product_category', '2556', $empty),
        ];

        for ($label = 0; $label < self::CUSTOM_LABELS; $label++) {
            $variables[] = new ComputedVariable(
                new Variable('custom_label_'.$label, 'webx-catalog::variables.custom_label', ''),
                static fn (): array => [],
            );
        }

        return $variables;
    }

    /**
     * A variable that is a function of one product.
     *
     * @param  Closure(Product): string  $value
     */
    private function variable(string $name, string $example, Closure $value): ProductVariable
    {
        return new ComputedVariable(
            new Variable($name, 'webx-catalog::variables.'.$name, $example),
            static fn (Collection $products): array => self::each($products, $value),
        );
    }

    private static function pictureList(string $name): Variable
    {
        return new Variable(
            $name,
            'webx-catalog::variables.'.$name,
            [['url' => 'https://example.com/storage/brake-pads.jpg', 'alt' => 'Brake pads']],
            ['url' => 'webx-catalog::variables.fields.url', 'alt' => 'webx-catalog::variables.fields.alt'],
        );
    }

    /**
     * The pictures of a product without its videos, the main one first.
     *
     * @param  Collection<int, Product>  $products
     * @return list<array{url: string, alt: string}>
     */
    private function pictures(Product $product, Collection $products, string $locale): array
    {
        self::load($products, ['images']);
        $pictures = [];

        foreach ($product->images as $image) {
            /** @var ProductImage $image */
            if (! $image->hasVideo()) {
                $alt = $image->getTranslation('alt', $locale, false);
                $pictures[] = ['url' => ImagesColumn::absolute($image->url()), 'alt' => is_string($alt) ? $alt : ''];
            }
        }

        return $pictures;
    }

    /**
     * The descriptions of a page as the site prints them: the bracket of a shortcode means nothing
     * to a marketplace. `PageText::html()` would ask where the library's pictures live once per
     * product; here the keys of the whole page are asked at once. A block shortcode stays on the
     * site: a section of cards inside a product's description is nothing a marketplace can show.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, string>
     */
    private function descriptions(Collection $products, string $locale): array
    {
        $ids = $products->map(static fn (Product $product): int => (int) $product->id)->all();

        return RequestMemo::remember('variables.descriptions.'.$locale.'.'.RequestMemo::ids($ids), function () use ($products, $locale): array {
            $stored = self::each($products, static fn (Product $product): string => self::text($product, 'description', $locale));
            $keys = [];

            foreach ($stored as $html) {
                RichText::rewrite($html, static function (array $found) use (&$keys): array {
                    $keys = [...$keys, ...$found];

                    return [];
                });
            }

            $urls = $keys !== [] && $this->container->bound(AssetUrls::class)
                ? $this->container->make(AssetUrls::class)->urls(array_values(array_unique($keys)))
                : [];
            $shortcodes = $this->container->make(Shortcodes::class);

            return array_map(
                static fn (string $html): string => $html === '' ? '' : $shortcodes->htmlIn(RichText::rewrite($html, static fn (): array => $urls), blocks: false),
                $stored,
            );
        });
    }

    /** The tree of categories in this language, read once a page however many variables walk it. */
    private static function tree(string $locale): CategoryPaths
    {
        return RequestMemo::remember('variables.categories.'.$locale, static fn (): CategoryPaths => new CategoryPaths($locale));
    }

    /**
     * Relations for the whole page: the first variable to ask loads them, the rest find them there.
     *
     * @param  Collection<int, Product>  $products
     * @param  list<string>  $relations
     */
    private static function load(Collection $products, array $relations): void
    {
        if ($relations !== []) {
            (new Models($products->all()))->loadMissing($relations);
        }
    }

    /**
     * @template T
     *
     * @param  Collection<int, Product>  $products
     * @param  Closure(Product): T  $value
     * @return array<int, T>
     */
    private static function each(Collection $products, Closure $value): array
    {
        $values = [];

        foreach ($products as $product) {
            $values[(int) $product->id] = $value($product);
        }

        return $values;
    }

    /** A language the product has no text in is empty, not another language's text. */
    private static function text(Product $product, string $field, string $locale): string
    {
        $text = $product->getTranslation($field, $locale, false);

        return is_string($text) ? $text : '';
    }

    /** Whole words up to the limit; one word longer than the limit is cut where it stands. */
    private static function cut(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit + 1);
        $words = (string) preg_replace('/\s+\S*$/u', '', $cut);

        return $words !== '' && $words !== $cut ? $words : mb_substr($text, 0, $limit);
    }

    private static function number(?float $price): string
    {
        return $price === null ? '' : number_format($price, 2, '.', '');
    }

    private function withCurrency(?float $price): string
    {
        $currency = $this->config->get('webx-catalog.price.currency');

        return $price === null || ! is_string($currency) || $currency === '' ? '' : self::number($price).' '.strtoupper($currency);
    }

    private function priced(): bool
    {
        return (bool) $this->config->get('webx-catalog.price.enabled', true);
    }

    private function on(string $field): bool
    {
        return (bool) $this->config->get('webx-catalog.fields.'.$field, true);
    }
}
