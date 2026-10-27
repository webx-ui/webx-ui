<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Tests;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\QueryTally;
use WebxUi\Cache\CacheGroups;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\Catalog\Support\RequestMemo;
use WebxUi\CatalogLabels\Catalog\Labels;
use WebxUi\CatalogLabels\Models\Label;

/**
 * The labels of a page for the template (WEBX_UI_MODULE_CATALOG.md §8.4): every label of every
 * product — badges and service ones — in one query on `prepare()`, at most one more on `extend()`,
 * none on a warm cache, and none at all from the template that reads them through `Labels::of()`.
 *
 * «A request» here is the memo of the registry and of the request forgotten: what the store holds
 * stays — the next visitor of a site.
 */
final class PageLabelsTest extends TestCase
{
    private Label $sale;

    private Label $new;

    private Label $mounting;

    /** @var list<Product> */
    private array $products;

    protected function setUp(): void
    {
        parent::setUp();

        $laptops = $this->category('laptops');
        $this->sale = $this->label('Sale', ['color' => 'danger', 'position' => 1]);
        $this->new = $this->label('New', ['color' => 'success', 'position' => 2]);
        // A service label: not a badge, not in the filter — the line «mounting included» of a theme.
        $this->mounting = $this->label('Mounting included', ['code' => 'mounting', 'is_badge' => false, 'is_visible' => false, 'position' => 3]);

        $this->products = [
            $this->product('One', $laptops, $this->mounting, $this->sale),
            $this->product('Two', $laptops, $this->new),
            $this->product('Three', $laptops),
            $this->product('Four', $laptops, $this->sale, $this->mounting),
            $this->product('Five', $laptops, $this->new, $this->sale),
        ];
        $this->request();
    }

    #[Test]
    public function the_page_loads_every_label_in_one_query_and_the_template_reads_them_without_one(): void
    {
        [$one, $two, $three] = $this->products;
        $parts = $this->app->make(StorefrontParts::class);

        $this->assertSame(1, $this->labelQueries(fn () => $parts->prepare(new Collection([$one, $two, $three]))));

        $template = '@foreach (app(\WebxUi\CatalogLabels\Catalog\Labels::class)->of($product) as $label)[{{ $label->code }}:{{ $label->is_badge ? "badge" : "service" }}]@endforeach';
        $html = '';
        $queries = $this->labelQueries(function () use ($template, $one, $two, $three, &$html): void {
            foreach ([$one, $two, $three] as $product) {
                $html .= Blade::render($template, ['product' => $product]).'|';
            }
        });

        $this->assertSame(0, $queries);
        // In the order of the reference book, whatever order they were put on in.
        $this->assertSame('[sale:badge][mounting:service]|[new:badge]||', $html);
        $this->assertSame(['Sale', 'Mounting included'], array_map(static fn (Label $label): string => $label->displayName('en'), app(Labels::class)->of($one->id)));
    }

    #[Test]
    public function the_badges_did_not_change(): void
    {
        [$one, $two, $three] = $this->products;
        $parts = $this->app->make(StorefrontParts::class);
        $parts->prepare(new Collection([$one, $two, $three]));

        $this->assertSame(
            '<ul class="wx-catalog-badges"><li class="wx-catalog-badge wx-catalog-badge--danger" data-label="sale">Sale</li></ul>',
            $this->squeeze($parts->render('catalog.card.badges', ['product' => $one])),
        );
        $this->assertStringContainsString('wx-catalog-badge--success" data-label="new">New<', $parts->render('catalog.card.badges', ['product' => $two]));
        $this->assertSame('', trim($parts->render('catalog.card.badges', ['product' => $three])));
    }

    #[Test]
    public function extend_adds_the_labels_of_more_products_in_one_query_at_most(): void
    {
        [$one, $two, $three, $four, $five] = $this->products;
        $parts = $this->app->make(StorefrontParts::class);
        $parts->prepare(new Collection([$one, $two]));

        // A strip of recommended products: one query for the ones the page did not have.
        $this->assertSame(1, $this->labelQueries(fn () => $parts->extend(new Collection([$two, $four, $five]))));
        // The same strip again, or a product of the page: nothing to load.
        $this->assertSame(0, $this->labelQueries(fn () => $parts->extend(new Collection([$one, $four]))));

        $this->assertSame(0, $this->labelQueries(function () use ($four, $five): void {
            $this->assertSame(['sale', 'mounting'], $this->codes($four));
            $this->assertSame(['sale', 'new'], $this->codes($five));
        }));
        $this->assertStringContainsString('data-label="sale"', $parts->render('catalog.card.badges', ['product' => $four]));
        $this->assertStringNotContainsString('mounting', $parts->render('catalog.card.badges', ['product' => $four]));
        $this->assertSame('', trim($parts->render('catalog.card.badges', ['product' => $three])));
    }

    #[Test]
    public function a_warm_cache_answers_the_next_request_without_a_query(): void
    {
        $page = new Collection($this->products);
        $parts = $this->app->make(StorefrontParts::class);

        $this->assertSame(1, $this->labelQueries(fn () => $parts->prepare($page)));
        $cold = $this->squeeze($parts->render('catalog.card.badges', ['product' => $this->products[3]]));

        $this->request();
        $this->assertSame(0, $this->labelQueries(fn () => $parts->prepare($page)));
        $this->assertSame($cold, $this->squeeze($parts->render('catalog.card.badges', ['product' => $this->products[3]])));
        $this->assertSame(['sale', 'mounting'], $this->codes($this->products[3]));
        $this->assertSame([], $this->codes($this->products[2]));
    }

    #[Test]
    public function a_product_the_page_did_not_load_is_loaded_by_itself(): void
    {
        $this->assertSame(1, $this->labelQueries(fn () => $this->assertSame(['new'], $this->codes($this->products[1]))));
        $this->assertSame(0, $this->labelQueries(fn () => $this->assertSame(['new'], $this->codes($this->products[1]))));
    }

    #[Test]
    public function an_edit_of_a_label_is_on_the_next_page(): void
    {
        $page = new Collection($this->products);
        $this->app->make(StorefrontParts::class)->prepare($page);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api("labels/{$this->sale->id}"), [
            'values' => ['title' => ['en' => 'Big sale'], 'is_badge' => false],
        ])->assertOk();

        $this->request();
        $parts = $this->app->make(StorefrontParts::class);
        $this->assertSame(1, $this->labelQueries(fn () => $parts->prepare($page)));
        $this->assertSame('', trim($parts->render('catalog.card.badges', ['product' => $this->products[0]])));
        $this->assertSame('Big sale', app(Labels::class)->of($this->products[0])[0]->displayName('en'));

        // A label in the bin is on no page.
        $this->new->products()->detach();
        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api("labels/{$this->new->id}"))->assertNoContent();
        $this->request();
        $this->assertSame([], $this->codes($this->products[1]));
    }

    #[Test]
    public function a_label_put_on_or_taken_off_a_product_is_on_the_next_page(): void
    {
        [$one, $two, $three] = $this->products;
        $page = new Collection([$one, $two, $three]);
        $this->app->make(StorefrontParts::class)->prepare($page);
        $this->assertSame([], $this->codes($three));

        // The form of the product, and in the same request the page sees it too.
        $this->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$three->id}"), [
            'values' => ['labels.ids' => [$this->new->id, $this->mounting->id]],
        ])->assertOk();
        $this->assertSame(['new', 'mounting'], $this->codes($three));

        $this->request();
        $this->assertSame(['new', 'mounting'], $this->codes($three));

        // A bulk action: a write without a model's event, which moves the generation all the same.
        $this->actingAs($this->editor(), 'cms')->postJson($this->api('bulk'), [
            'action' => 'remove-label',
            'params' => ['label_id' => $this->mounting->id],
            'selection' => ['ids' => [$one->id, $three->id]],
        ])->assertOk();
        $this->assertSame(['sale'], $this->codes($one));

        $this->request();
        $parts = $this->app->make(StorefrontParts::class);
        $this->assertSame(1, $this->labelQueries(fn () => $parts->prepare($page)));
        $this->assertSame(['sale'], $this->codes($one));
        $this->assertSame(['new'], $this->codes($three));
    }

    #[Test]
    public function with_the_cache_off_every_request_asks_once(): void
    {
        config(['webx-catalog.cache.enabled' => false]);
        $page = new Collection($this->products);
        $parts = $this->app->make(StorefrontParts::class);

        $this->assertSame(1, $this->labelQueries(fn () => $parts->prepare($page)));
        $this->request();
        $this->assertSame(1, $this->labelQueries(fn () => $parts->prepare($page)));
        $this->assertSame(['sale', 'new'], $this->codes($this->products[4]));
    }

    /** The next visitor: nothing this process read, what the store keeps. */
    private function request(): void
    {
        $this->app->make(CacheGroups::class)->forgetMemo();
        RequestMemo::forget();
    }

    private function labelQueries(Closure $work): int
    {
        $queries = QueryTally::record($work)->queries;

        return count(array_filter($queries, static fn (array $query): bool => str_contains($query['query'], 'catalog_label')));
    }

    /**
     * @return list<string>
     */
    private function codes(Product $product): array
    {
        return array_map(static fn (Label $label): string => (string) $label->code, app(Labels::class)->of($product));
    }

    private function squeeze(string $html): string
    {
        return trim((string) preg_replace('/>\s+</', '><', $html));
    }
}
