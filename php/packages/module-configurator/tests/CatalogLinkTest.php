<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Remote\HostResolver;
use WebxUi\Admin\Testing\FakeHostResolver;
use WebxUi\Catalog\CatalogServiceProvider;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Models\ProductLink;
use WebxUi\Configurator\Panel\ConfiguratorWriter;
use WebxUi\Configurator\Pricing\Quoter;
use WebxUi\Configurator\Schema\Problem;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The configurator and the catalogue, both ways (§22), with the catalogue installed: a product
 * opens a configurator with a preset, and an option shows a product «in real life». The other
 * half — no catalogue, nothing breaks — is in SiteTest and ValidatorTest, which run without it.
 */
final class CatalogLinkTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            SettingsServiceProvider::class,
            SeoServiceProvider::class,
            ThemeServiceProvider::class,
            WidgetsServiceProvider::class,
            CatalogServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-seo.cache.enabled', false);
        $app->instance(HostResolver::class, new FakeHostResolver);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ProductImage::disk());
    }

    #[Test]
    public function the_product_form_keeps_the_configurator_and_where_it_starts(): void
    {
        $this->configurator();
        $product = $this->product('Corner kit', 'KIT-1');
        $editor = $this->editor(['catalog.view', 'catalog.manage', 'configurators.view']);

        $this->actingAs($editor, 'cms')->putJson("/api/cms/catalog/products/{$product->id}", ['values' => [
            'configurator.key' => 'shower',
            'configurator.preset' => ['construction' => 'niche', 'h' => 1900, 'not a key' => 'x'],
        ]])->assertOk();

        $link = ProductLink::query()->findOrFail($product->id);
        $this->assertSame('shower', $link->configurator);
        $this->assertSame(['construction' => 'niche', 'h' => 1900], $link->preset);

        $values = $this->actingAs($editor, 'cms')->getJson("/api/cms/catalog/products/{$product->id}")->assertOk()->json('data.values');
        $this->assertSame('shower', $values['configurator.key']);
        $this->assertSame(['construction' => 'niche', 'h' => 1900], $values['configurator.preset']);

        // A key nobody has is refused; an empty one takes the link away.
        $this->actingAs($editor, 'cms')->putJson("/api/cms/catalog/products/{$product->id}", ['values' => ['configurator.key' => 'doors']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('configurator.key');

        $this->actingAs($editor, 'cms')->putJson("/api/cms/catalog/products/{$product->id}", ['values' => ['configurator.key' => null]])->assertOk();
        $this->assertFalse(ProductLink::query()->whereKey($product->id)->exists());
    }

    #[Test]
    public function the_product_page_sends_the_visitor_to_the_configurator_with_its_preset(): void
    {
        $configurator = $this->configurator();
        $product = $this->product('Corner kit', 'KIT-1');
        ProductLink::query()->create(['product_id' => $product->id, 'configurator' => 'shower', 'preset' => ['construction' => 'niche']]);

        // No page yet: nowhere to send anybody, no button.
        $this->get($product->url('uk'))->assertOk()->assertDontSee('webx-configurator-open', false);

        app(ConfiguratorWriter::class)->update($configurator, ['page' => ['target' => 'url', 'url' => '/shower-configurator']]);

        $this->get($product->url('uk'))
            ->assertOk()
            ->assertSee('<a class="webx-configurator-open" href="http://localhost/shower-configurator?configurator=shower&amp;preset%5Bconstruction%5D=niche#webx-configurator-shower" data-webx-configurator-open="shower">Розрахувати в конструкторі</a>', false);

        // Taken off the site: the button goes with it.
        $configurator->refresh()->unpublish();
        $this->get($product->url('uk'))->assertOk()->assertDontSee('webx-configurator-open', false);
    }

    #[Test]
    public function the_address_starts_the_configurator_and_the_engine_reads_it_as_initial(): void
    {
        $this->configurator();

        $html = $this->render('/shower-configurator?configurator=shower&preset[construction]=niche&preset[h]=9000&preset[glass]=nothing');

        // The niche is chosen; the height is clamped into its limits; a value nobody has is dropped.
        $this->assertMatchesRegularExpression('~data-webx-configurator-option="niche"~', $html);
        $this->assertMatchesRegularExpression('~class="webx-configurator__option webx-configurator__option--card is-selected" data-webx-configurator-option="niche"~', $html);
        $this->assertMatchesRegularExpression('~name="configurator\[values\]\[h\]" value="2400"~', $html);
        $this->assertMatchesRegularExpression('~webx-configurator__option--swatch is-selected" data-webx-configurator-option="clear"~', $html);

        // Another configurator's preset is not this one's.
        $other = $this->render('/shower-configurator?configurator=doors&preset[construction]=niche');
        $this->assertMatchesRegularExpression('~webx-configurator__option--card is-selected" data-webx-configurator-option="corner"~', $other);
    }

    #[Test]
    public function an_option_shows_its_product_in_real_life_and_only_while_the_site_shows_it(): void
    {
        $product = $this->product('Graphite glass sample', 'GLASS-1', pictures: 5);
        $schema = $this->schema();
        $schema['steps'][2]['options'][1]['product'] = 'GLASS-1';
        $this->configurator($schema);

        $html = $this->render();

        // Printed for the option, hidden while another glass is chosen; four pictures of five.
        $this->assertMatchesRegularExpression('~data-webx-configurator-live="glass:graphite"\s+hidden~', $html);
        $this->assertStringContainsString('Графіт наживо', $html);
        $this->assertSame(4, substr_count($html, 'class="webx-configurator__live-image"'));
        $this->assertStringContainsString('<a class="webx-configurator__live-product" href="'.$product->url().'">Graphite glass sample</a>', $html);

        // Chosen from the start: shown.
        $this->assertMatchesRegularExpression('~data-webx-configurator-live="glass:graphite"\s*>~', $this->render('/?configurator=shower&preset[glass]=graphite'));

        // Unpublished, then in the bin: no block at all — not a block with a dead link.
        $product->forceFill(['is_published' => false])->save();
        $this->assertStringNotContainsString('webx-configurator__live', $this->render());

        $product->forceFill(['is_published' => true])->save();
        $this->assertStringContainsString('webx-configurator__live', $this->render());

        $product->delete();
        $this->assertStringNotContainsString('webx-configurator__live', $this->render());
    }

    #[Test]
    public function an_article_number_the_catalogue_does_not_have_is_refused_only_when_new(): void
    {
        $this->mediaFile();
        $this->product('Graphite glass sample', 'GLASS-1');
        $schema = $this->schema();
        $schema['steps'][2]['options'][1]['product'] = 'GLASS-1';
        $schema['steps'][3]['options'][1]['product'] = 'GONE-1';

        $codes = static fn (array $problems): array => array_map(static fn (Problem $problem): string => "{$problem->path} {$problem->code}", $problems);
        $quoter = $this->app->make(Quoter::class);

        $this->assertSame(['steps.3.options.1.product product.unknown'], $codes($quoter->problems($schema)));
        // Published with it once and the product went since: the draft still saves and publishes.
        $this->assertSame([], $codes($quoter->problems($schema, ['GONE-1'])));
    }

    #[Test]
    public function an_agent_sees_both_sides_of_the_link_and_the_key_carries_them(): void
    {
        $this->product('Graphite glass sample', 'GLASS-1');
        $kit = $this->product('Corner kit', 'KIT-1');
        $schema = $this->schema();
        $schema['steps'][2]['options'][1]['product'] = 'GLASS-1';

        $writer = app(ConfiguratorWriter::class);
        $draft = $writer->create('shower-draft', ['uk' => 'Душові'], $schema);
        ProductLink::query()->create(['product_id' => $kit->id, 'configurator' => 'shower-draft', 'preset' => ['construction' => 'niche']]);

        // A key changed before the first publication takes the products that open it along.
        $writer->update($draft, ['key' => 'shower']);
        $this->assertSame('shower', ProductLink::query()->findOrFail($kit->id)->configurator);

        $this->agent('configurators_update', ['configurator' => 'shower', 'page' => '/shower-configurator', 'force' => true])->assertOk();

        $got = $this->content($this->agent('configurators_get', ['configurator' => 'shower']));

        $this->assertSame(['target' => 'url', 'entity_type' => null, 'entity_id' => null, 'url' => '/shower-configurator', 'hash' => null, 'new_tab' => false, 'rel' => []], $got['page']);
        $this->assertSame('GLASS-1', $got['option_products'][0]['sku']);
        $this->assertSame(['step' => 'glass', 'option' => 'graphite'], array_intersect_key($got['option_products'][0], ['step' => 1, 'option' => 1]));
        $this->assertTrue($got['option_products'][0]['product']['visible']);
        $this->assertSame($kit->id, $got['opened_from_products'][0]['product_id']);
        $this->assertSame('Corner kit', $got['opened_from_products'][0]['product']['name']);
        $this->assertSame(['construction' => 'niche'], $got['opened_from_products'][0]['preset']);

        // The page is not content: setting it left the draft as it was.
        $this->assertSame(Configurator::STATUS_DRAFT, $draft->refresh()->status());
    }

    #[Test]
    public function the_demo_links_its_constructions_to_the_catalogues_demo_products(): void
    {
        foreach (range(1, 4) as $n) {
            $this->product("Demo product {$n}", sprintf('DEMO-%04d', $n));
        }

        $this->artisan('webx:demo', ['--module' => ['configurators']])->assertSuccessful();

        $schema = Configurator::query()->sole()->liveSchema() ?? [];
        $this->assertSame(['DEMO-0001', 'DEMO-0002', 'DEMO-0003', 'DEMO-0004'], array_values(array_filter(array_map(
            static fn (array $option): ?string => $option['product'] ?? null,
            $schema['steps'][0]['options'],
        ))));

        // The third opens the configurator on its own construction.
        $third = Product::query()->where('sku', 'DEMO-0003')->sole();
        $this->assertSame(['construction' => $schema['steps'][0]['options'][2]['value']], ProductLink::query()->findOrFail($third->id)->preset);

        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();
        $this->assertSame(0, DB::table(Configurator::LINKS)->count());
    }

    /**
     * @param  array<string, mixed>|null  $schema
     */
    private function configurator(?array $schema = null): Configurator
    {
        $this->mediaFile();
        $writer = app(ConfiguratorWriter::class);

        return $writer->publish($writer->create('shower', ['uk' => 'Душові'], $schema ?? $this->schema()));
    }

    /** A product the site shows — published, in a published category — with pictures in its gallery. */
    private function product(string $name, string $sku, int $pictures = 1): Product
    {
        $category = Category::query()->where('slug->uk', 'glass')->first();

        if (! $category instanceof Category) {
            $category = new Category(['name' => ['uk' => 'Glass'], 'slug' => ['uk' => 'glass'], 'is_published' => true]);
            $category->saveAsRoot();
        }

        $product = Product::query()->create(['name' => ['uk' => $name], 'sku' => $sku, 'category_id' => $category->id, 'is_published' => true, 'price' => 10]);

        foreach (range(1, $pictures) as $n) {
            ProductImage::query()->create(['product_id' => $product->id, 'path' => "catalog/{$sku}-{$n}.jpg", 'alt' => ['uk' => "{$name} {$n}"], 'position' => $n]);
        }

        return $product->refresh();
    }

    /** The configurator rendered for a request to this address — what the block's page would print. */
    private function render(string $address = '/', string $blade = '<x-webx-configurator key="shower" />'): string
    {
        $this->app->forgetScopedInstances();
        $this->app->instance('request', Request::create($address));
        $this->app->setLocale('uk');

        return Blade::render($blade);
    }
}
