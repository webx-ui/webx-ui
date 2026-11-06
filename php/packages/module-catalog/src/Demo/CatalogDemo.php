<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Demo;

use Illuminate\Http\UploadedFile;
use ReflectionClass;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Gallery\Gallery;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Slug;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Reserved;

/**
 * A shop to look at (§15): fifteen categories three levels deep, a hundred and fifty products with
 * prices and pictures, and every state the panel has a word for — a few unpublished, a few in
 * «Deleted», two without a category so that the filter "no category" is not empty.
 *
 * Made up in code rather than read from a file: the names are a noun of the shelf and an adjective
 * from a short list, the prices a formula — nothing that looks like somebody's real catalogue. The
 * pictures are the library's two gradients (`module-media/resources/demo`), put into each gallery
 * through the same door an editor's upload uses.
 *
 * What a product feed has to get right is here too (§14.2 of the feeds spec): a sale, a real GTIN
 * and a shop's own code, a product with no identifier at all, and the two names of {@see SAMPLES}.
 */
final class CatalogDemo
{
    /** Parent → children; the leaves hold the products. */
    private const TREE = [
        'Clothing' => [
            'Men' => ['Shirts', 'Trousers'],
            'Women' => ['Dresses', 'Skirts'],
        ],
        'Shoes' => [
            'Sneakers' => [],
            'Boots' => [],
        ],
        'Home' => [
            'Kitchen' => ['Cookware', 'Knives'],
            'Textiles' => [],
        ],
    ];

    /** The singular each leaf's products are named with. */
    private const NOUNS = [
        'Shirts' => 'shirt', 'Trousers' => 'trousers', 'Dresses' => 'dress', 'Skirts' => 'skirt',
        'Sneakers' => 'sneakers', 'Boots' => 'boots', 'Cookware' => 'pan', 'Knives' => 'knife',
        'Textiles' => 'towel',
    ];

    private const ADJECTIVES = [
        'Classic', 'Linen', 'Everyday', 'Travel', 'Summer', 'Winter', 'Studio', 'Harbour',
        'Field', 'City', 'Coastal', 'Mountain', 'Heritage', 'Soft', 'Light', 'Sturdy', 'Weekend',
    ];

    private const PRODUCTS = 150;

    private const PICTURES = ['demo-wide.jpg', 'demo-square.jpg'];

    /**
     * Name => shelf. One written in Cyrillic, whose address still comes out in Latin letters, and
     * one with everything a file format has to escape.
     */
    private const SAMPLES = [
        'Кружка керамическая' => 'Cookware',
        'Knife & board set "Pro" <20 cm>' => 'Knives',
    ];

    /**
     * The one video of the demo: Big Buck Bunny, the Blender Foundation's open film on its own
     * channel. A link rather than a file — megabytes do not belong in a package.
     */
    public const VIDEO = 'aqz-KE-bpKQ';

    public function __construct(
        private readonly Gallery $gallery,
        private readonly Locales $locales,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        // A catalogue with anything in it is somebody's catalogue.
        if (Product::withTrashed()->exists() || Category::withTrashed()->exists()) {
            $ledger->note('The catalogue already has products or categories; the demo left it alone.');

            return;
        }

        $locale = $this->locales->defaultCode();
        $leaves = [];

        foreach (self::TREE as $top => $children) {
            $parent = $this->category($top, null, $locale, $ledger);

            foreach ($children as $middle => $grandchildren) {
                $child = $this->category($middle, $parent, $locale, $ledger);

                if ($grandchildren === []) {
                    $leaves[$middle] = $child;

                    continue;
                }

                foreach ($grandchildren as $leaf) {
                    $leaves[$leaf] = $this->category($leaf, $child, $locale, $ledger);
                }
            }
        }

        $names = array_keys($leaves);

        for ($i = 0; $i < self::PRODUCTS; $i++) {
            $shelf = $names[$i % count($names)];
            $product = $this->product($i, $shelf, $leaves[$shelf], $leaves[$names[($i + 3) % count($names)]], $locale, $ledger);

            // Every twenty-fifth unpublished, every thirty-seventh in «Deleted», two with nowhere
            // to be: the list's tabs each have something on them.
            if ($i % 25 === 7) {
                $product->forceFill(['is_published' => false])->save();
            } elseif ($i % 37 === 11) {
                $product->delete();
            }
        }

        foreach (['Unsorted sample', 'Loose sample'] as $n => $name) {
            $this->product(self::PRODUCTS + $n, null, null, null, $locale, $ledger, $name);
        }

        $i = self::PRODUCTS + 2;

        foreach (self::SAMPLES as $name => $shelf) {
            $this->product($i++, $shelf, $leaves[$shelf], null, $locale, $ledger, $name);
        }
    }

    private function category(string $name, ?Category $parent, string $locale, DemoLedger $ledger): Category
    {
        $category = new Category([
            'name' => [$locale => $name],
            'slug' => [$locale => $this->freeSlug(Slug::make($name, $locale), $locale)],
            'description' => [$locale => '<p>'.$name.' of the demo shop: made up, to show how a category looks.</p>'],
            'is_published' => true,
        ]);

        $parent === null ? $category->saveAsRoot() : $category->appendTo($parent);
        $ledger->created($category, 'Category '.$name);

        return $category->refresh();
    }

    /**
     * A category's slug is filled from its name by the panel's form, not by the model, so the demo
     * names one itself — without it a category has no address. Slugs are flat and shared with
     * pages, so "home" or "men" may already be somebody's on the site.
     */
    private function freeSlug(string $slug, string $locale): string
    {
        $taken = static fn (string $path): bool => app(Reserved::class)->taken($path)
            || Route::query()->where('locale', $locale)->where('path', $path)->exists();

        return $taken($slug) ? $slug.'-shop' : $slug;
    }

    /**
     * Every sixth a code from the range kept for a shop's own, which a marketplace is not given; the
     * rest of every third a GTIN with its check digit right, under a prefix GS1 has given to nobody.
     * Made up either way: no such product exists.
     */
    private function barcode(int $i): ?string
    {
        if ($i % 3 !== 0) {
            return null;
        }

        if ($i % 2 === 0) {
            return sprintf('200%010d', $i + 1);
        }

        $digits = sprintf('190%09d', $i + 1);
        $sum = 0;

        foreach (str_split(strrev($digits)) as $at => $digit) {
            $sum += (int) $digit * ($at % 2 === 0 ? 3 : 1);
        }

        return $digits.((10 - $sum % 10) % 10);
    }

    private function product(int $i, ?string $shelf, ?Category $main, ?Category $extra, string $locale, DemoLedger $ledger, ?string $name = null): Product
    {
        $name ??= self::ADJECTIVES[$i % count(self::ADJECTIVES)].' '.self::NOUNS[(string) $shelf].' '.(intdiv($i, 9) + 1);
        $price = 9 + (($i * 37) % 180) + 0.9;

        $product = Product::query()->create([
            'name' => [$locale => $name],
            'summary' => [$locale => 'A demo product: no such thing is for sale.'],
            'description' => [$locale => '<p>'.e($name).' is part of the demo catalogue. Its price, its article number and its picture are made up.</p>'],
            'sku' => sprintf('DEMO-%04d', $i + 1),
            'barcode' => $this->barcode($i),
            // With the barcodes this leaves every mix: both, either one, neither.
            'mpn' => $i % 2 === 0 || $i % 12 === 3 ? sprintf('DEMO-MPN-%04d', $i + 1) : null,
            'category_id' => $main?->getKey(),
            'price' => $price,
            'old_price' => $i % 6 === 0 ? round($price * 1.25, 2) : null,
            'unit' => $shelf === 'Cookware' ? 'set' : 'pcs',
            'priority' => $i % 17 === 0 ? 10 : 0,
            'is_published' => $main !== null,
        ]);
        $ledger->created($product, $name);

        // Every sixth comes in a second packaging, with a code of its own.
        if ($i % 6 === 0) {
            $product->barcodes()->create(['barcode' => sprintf('201%010d', $i + 1), 'position' => 0]);
        }

        // Every fifth is filed on a second shelf too, the way a real catalogue files a gift set.
        if ($extra !== null && $i % 5 === 0 && $extra->getKey() !== $main?->getKey()) {
            $product->categories()->attach($extra->getKey());
        }

        $picture = self::PICTURES[$i % 2];
        $path = dirname((string) (new ReflectionClass(MediaServiceProvider::class))->getFileName(), 2).'/resources/demo/'.$picture;

        if (is_file($path)) {
            // Test mode: a file of the package, not something that came up a socket.
            $image = $this->gallery->upload($product, new UploadedFile($path, $picture, 'image/jpeg', null, true));
            $image->setTranslation('alt', $locale, $name);

            // The first product plays a video over its own demo picture: nothing is fetched.
            if ($i === 0) {
                $image->forceFill(['video_provider' => 'youtube', 'video' => self::VIDEO]);
            }

            $image->save();
            // Recorded after the product, so removing them goes first. The file is its own entry:
            // a picture's row going does not take its bytes (the panel's delete erases them itself).
            $ledger->created($image, $name.' picture');
            $ledger->wrote(ProductImage::disk(), $image->path, $name.' picture file');
        }

        return $product;
    }
}
