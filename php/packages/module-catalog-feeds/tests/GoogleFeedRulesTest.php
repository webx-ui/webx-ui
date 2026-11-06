<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Demo\CatalogDemo;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogBrands\Demo\BrandsDemo;
use WebxUi\CatalogFeeds\Delivery\FeedWatch;
use WebxUi\CatalogFeeds\Demo\FeedsDemo;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\CatalogStock\Demo\StockDemo;
use WebxUi\CatalogStock\StockServiceProvider;

/**
 * Google's rules, read off a finished file: the table of §14.2 of the feeds spec, one test a row.
 *
 * There is no upload to Merchant Center, so this is what stands in for one. In the gate the file is
 * built here on the demo shop. `WEBX_FEED_FILE` hands it a file from a real site instead, and
 * `WEBX_FEED_CURRENCY` that site's currency when it is not the one the tests run with.
 *
 * The rules are written out here rather than taken from the code they check: the check digit of a
 * GTIN read through `Gtin` would agree with `Gtin` whatever Google says.
 *
 * A failure is a fault of the feed or of the demo, not of this test: the rule is Google's.
 */
final class GoogleFeedRulesTest extends TestCase
{
    private const G = 'http://base.google.com/ns/1.0';

    private const REQUIRED = ['id', 'title', 'description', 'link', 'image_link', 'availability', 'price', 'condition', 'brand'];

    /** Built once: every test reads the same file, and the demo is the slow part of it. */
    private static ?string $built = null;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            BrandsServiceProvider::class,
            StockServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The demo's pictures go in as they are: the pipeline is the media module's test.
        $app['config']->set('webx-media.optimize.enabled', false);
    }

    #[Test]
    public function the_file_is_rss_2_with_the_namespace_of_google(): void
    {
        $rss = $this->rss();

        $this->assertSame('rss', $rss->getName());
        $this->assertSame('2.0', (string) $rss['version']);
        $this->assertSame(self::G, $rss->getDocNamespaces()['g'] ?? null);
        $this->assertCount(1, $rss->channel);

        foreach (['title', 'link', 'description'] as $name) {
            $this->assertNotSame('', trim((string) $rss->channel->{$name}), "channel {$name}");
        }

        $this->assertNotSame([], $this->items());
    }

    #[Test]
    public function every_item_has_the_required_attributes_and_no_empty_one(): void
    {
        foreach ($this->items() as $item) {
            foreach (self::REQUIRED as $name) {
                $this->assertNotSame('', $this->value($item, $name), "{$name} of item {$this->value($item, 'id')}");
            }

            foreach ($item->children(self::G) as $name => $value) {
                $this->assertNotSame('', trim((string) $value), "Empty {$name} in item {$this->value($item, 'id')}");
            }
        }
    }

    #[Test]
    public function an_id_is_unique_and_within_the_limit(): void
    {
        $ids = array_map(fn (SimpleXMLElement $item): string => $this->value($item, 'id'), $this->items());

        $this->assertSame(count($ids), count(array_unique($ids)));

        foreach ($ids as $id) {
            $this->assertLessThanOrEqual(50, mb_strlen($id), $id);
        }
    }

    #[Test]
    public function a_price_is_a_number_with_a_point_and_the_currency_of_the_site(): void
    {
        foreach ($this->items() as $item) {
            $amount = $this->amount($this->value($item, 'price'), 'price of '.$this->value($item, 'id'));

            $this->assertGreaterThan(0, $amount, 'A zero price is refused: '.$this->value($item, 'id'));
        }
    }

    #[Test]
    public function a_sale_price_has_the_same_format_and_is_below_the_price(): void
    {
        foreach ($this->items() as $item) {
            $sale = $this->value($item, 'sale_price');

            if ($sale === '') {
                continue;
            }

            $id = $this->value($item, 'id');

            $this->assertLessThan(
                $this->amount($this->value($item, 'price'), "price of {$id}"),
                $this->amount($sale, "sale_price of {$id}"),
                "The sale price of {$id}",
            );
        }
    }

    #[Test]
    public function availability_is_one_of_the_values_of_google(): void
    {
        foreach ($this->items() as $item) {
            $this->assertContains($this->value($item, 'availability'), ['in_stock', 'out_of_stock', 'preorder', 'backorder']);
        }
    }

    #[Test]
    public function condition_is_one_of_the_values_of_google(): void
    {
        foreach ($this->items() as $item) {
            $this->assertContains($this->value($item, 'condition'), ['new', 'refurbished', 'used']);
        }
    }

    #[Test]
    public function a_title_is_plain_text_within_the_limit(): void
    {
        foreach ($this->items() as $item) {
            $this->assertText($this->value($item, 'title'), 150, 'title of '.$this->value($item, 'id'));
        }
    }

    #[Test]
    public function a_description_is_plain_text_within_the_limit(): void
    {
        foreach ($this->items() as $item) {
            $this->assertText($this->value($item, 'description'), 5000, 'description of '.$this->value($item, 'id'));
        }
    }

    #[Test]
    public function identifier_exists_says_no_exactly_when_there_is_no_gtin_and_no_mpn(): void
    {
        foreach ($this->items() as $item) {
            $this->assertSame(
                $this->value($item, 'gtin') === '' && $this->value($item, 'mpn') === '' ? 'no' : 'yes',
                $this->value($item, 'identifier_exists'),
                'identifier_exists of '.$this->value($item, 'id'),
            );
        }
    }

    #[Test]
    public function a_gtin_is_one_google_takes(): void
    {
        foreach ($this->items() as $item) {
            $gtin = $this->value($item, 'gtin');

            if ($gtin === '') {
                continue;
            }

            $this->assertMatchesRegularExpression('/^(\d{8}|\d{12,14})$/', $gtin);
            $this->assertTrue($this->checkDigitHolds($gtin), "The check digit of {$gtin}");

            // A shorter code is read with leading zeros, and that is where the prefixes stand.
            $this->assertDoesNotMatchRegularExpression('/^(02|04|2|05|98|99)/', str_pad($gtin, 13, '0', STR_PAD_LEFT), "The prefix of {$gtin}");
        }
    }

    #[Test]
    public function a_link_is_absolute_ascii_and_within_the_limit(): void
    {
        foreach ($this->items() as $item) {
            $this->assertAddress($this->value($item, 'link'));
        }
    }

    #[Test]
    public function an_image_link_is_absolute_ascii_and_within_the_limit(): void
    {
        foreach ($this->items() as $item) {
            $this->assertAddress($this->value($item, 'image_link'));

            foreach ($item->children(self::G)->additional_image_link as $more) {
                $this->assertAddress(trim((string) $more));
            }
        }
    }

    /** A rule nothing in the file reaches passes without having said anything. */
    #[Test]
    public function the_file_holds_every_case_the_rules_read(): void
    {
        $items = $this->items();
        $some = static fn (callable $test): int => count(array_filter($items, $test));

        $this->assertGreaterThan(0, $some(fn ($item): bool => $this->value($item, 'sale_price') !== ''), 'A sale.');
        $this->assertGreaterThan(0, $some(fn ($item): bool => $this->value($item, 'gtin') !== ''), 'A GTIN.');
        $this->assertGreaterThan(0, $some(fn ($item): bool => $this->value($item, 'identifier_exists') === 'no'), 'No identifiers.');
        $this->assertGreaterThan(0, $some(fn ($item): bool => $this->value($item, 'availability') === 'out_of_stock'), 'Out of stock.');
        $this->assertGreaterThan(0, $some(fn ($item): bool => $this->value($item, 'availability') === 'in_stock'), 'In stock.');
        $this->assertGreaterThan(0, $some(fn ($item): bool => preg_match('/\p{Cyrillic}/u', $this->value($item, 'title')) === 1), 'A name in Cyrillic, its link held to ASCII above.');
        $this->assertGreaterThan(0, $some(fn ($item): bool => preg_match('/[&<>"]/', $this->value($item, 'title')) === 1), 'A name that has to be escaped.');
    }

    private function assertText(string $text, int $limit, string $what): void
    {
        $length = mb_strlen($text);

        $this->assertGreaterThanOrEqual(1, $length, $what);
        $this->assertLessThanOrEqual($limit, $length, $what);

        // A tag starts with a letter, so `<20 cm>` in a name is text and stays.
        $this->assertDoesNotMatchRegularExpression('~</?[a-z][a-z0-9]*(\s[^<>]*)?/?>~i', $text, "A tag in the {$what}");

        // The parser has undone one level; an entity still here was escaped twice.
        $this->assertDoesNotMatchRegularExpression('/&(amp|lt|gt|quot|#\d+|#x[0-9a-f]+);/i', $text, "Escaped twice, the {$what}");
    }

    private function assertAddress(string $address): void
    {
        $this->assertMatchesRegularExpression('~^https?://[^/\s]+~', $address);
        // What RFC 3986 lets stand in an address; anything else comes percent-encoded.
        $this->assertMatchesRegularExpression('~^[A-Za-z0-9\-._\~:/?#\[\]@!$&\'()*+,;=%]+$~', $address);
        $this->assertLessThanOrEqual(2000, strlen($address), $address);
    }

    private function amount(string $price, string $what): float
    {
        $currency = (string) (getenv('WEBX_FEED_CURRENCY') ?: config('webx-catalog.price.currency'));

        $this->assertMatchesRegularExpression('/^\d+\.\d{2} '.preg_quote($currency, '/').'$/', $price, $what);

        return (float) $price;
    }

    private function checkDigitHolds(string $gtin): bool
    {
        $sum = 0;

        foreach (str_split(strrev(substr($gtin, 0, -1))) as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === (int) substr($gtin, -1);
    }

    private function value(SimpleXMLElement $item, string $name): string
    {
        return trim((string) $item->children(self::G)->{$name});
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function items(): array
    {
        return iterator_to_array($this->rss()->channel->item, false);
    }

    private function rss(): SimpleXMLElement
    {
        $rss = simplexml_load_string($this->file(), SimpleXMLElement::class, LIBXML_NONET);

        $this->assertInstanceOf(SimpleXMLElement::class, $rss, 'The file is XML.');

        return $rss;
    }

    private function file(): string
    {
        $path = getenv('WEBX_FEED_FILE');

        if (is_string($path) && $path !== '') {
            $file = file_get_contents($path);
            $this->assertIsString($file, "No file at {$path}.");

            return $file;
        }

        return self::$built ??= $this->build();
    }

    /** In the order of the real run: the feed comes before the stock, and the watcher builds it. */
    private function build(): string
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->app->make('url')->forceRootUrl('https://example.test');
        $this->app->make('url')->forceScheme('https');

        $ledger = $this->app->make(DemoLedger::class);

        foreach (['catalog' => CatalogDemo::class, 'catalog-brands' => BrandsDemo::class, 'catalog-feeds' => FeedsDemo::class, 'catalog-stock' => StockDemo::class] as $id => $demo) {
            $ledger->forModule($id);
            $this->app->make($demo)->seed($ledger);
        }

        $this->app->make(FeedWatch::class)->tick();

        $feed = Feed::query()->sole();
        $build = FeedBuild::query()->sole();
        $this->assertSame(FeedBuild::DONE, $build->status, (string) $build->error);

        return (string) $this->get('/feeds/'.$feed->access_key.'.xml')->assertOk()->streamedContent();
    }
}
