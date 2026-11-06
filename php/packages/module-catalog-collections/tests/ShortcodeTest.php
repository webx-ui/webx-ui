<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\PageText;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Admin\Shortcodes\ShortcodeText;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Storefront\CollectionShortcode;

/**
 * A collection in a text (§4.3): where it prints, where it does not, and the row the panel offers.
 */
final class ShortcodeTest extends TestCase
{
    private const BRACKET = '[product-collection key=picks]';

    private Shortcodes $shortcodes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shortcodes = $this->app->make(Shortcodes::class);
    }

    /**
     * @param  list<Product>  $products
     * @param  array<string, mixed>  $attributes
     */
    private function collection(array $products, array $attributes = []): ProductCollection
    {
        $collection = ProductCollection::query()->create(['title' => 'Picks', 'key' => 'picks', ...$attributes])->refresh();

        foreach ($products as $position => $product) {
            $collection->products()->attach($product->id, ['position' => $position]);
        }

        // The list is written past the model here, and a path with no save moves the group itself.
        $collection->touch();

        return $collection;
    }

    private function editor(): CmsUser
    {
        return CmsUser::query()->create([
            'name' => 'Editor',
            'email' => 'editor@example.test',
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function alone_in_a_paragraph_it_takes_the_place_of_the_paragraph(): void
    {
        $this->collection([$this->product('Trail camera')]);

        $html = $this->shortcodes->htmlIn('<p>Before</p><p>'.self::BRACKET.'</p><p>After</p>');

        $this->assertStringStartsWith('<p>Before</p>', $html);
        $this->assertStringEndsWith('<p>After</p>', $html);
        $this->assertStringContainsString('Trail camera', $html);
        $this->assertDoesNotMatchRegularExpression('/<p>\s*<section/', $html);
        $this->assertDoesNotMatchRegularExpression('/<\/section>\s*<\/p>/', $html);
    }

    #[Test]
    public function with_text_beside_it_the_paragraph_is_cut_around_the_section(): void
    {
        $this->collection([$this->product('Trail camera')]);

        $html = $this->shortcodes->htmlIn('<p>Our picks: '.self::BRACKET.' and more.</p>');

        $this->assertMatchesRegularExpression('/^<p>Our picks: <\/p>\s*<section.*<\/section>\s*<p> and more\.<\/p>$/s', $html);
    }

    /* The panel's arguments reach the tag: the view over the collection's, the limit under it. */
    #[Test]
    public function its_arguments_are_the_arguments_of_the_tag(): void
    {
        $this->collection([$this->product('First'), $this->product('Second')]);

        $html = $this->shortcodes->htmlIn('<p>[product-collection key=picks view=carousel limit=1]</p>');

        $this->assertStringContainsString('webx-catalog-collection--carousel', $html);
        $this->assertStringContainsString('First', $html);
        $this->assertStringNotContainsString('Second', $html);
    }

    /* §4.4: nothing to show leaves nothing, the empty paragraph included. */
    #[Test]
    public function a_collection_with_nothing_to_show_leaves_no_trace_in_the_text(): void
    {
        $this->collection([$this->product('Trail camera')], ['is_enabled' => false]);
        $this->collection([], ['key' => 'empty', 'title' => 'Empty']);

        $this->assertSame('<p>a</p><p>b</p>', $this->shortcodes->htmlIn('<p>a</p><p>'.self::BRACKET.'</p><p>b</p>'));
        $this->assertSame('<p>a</p><p>b</p>', $this->shortcodes->htmlIn('<p>a</p><p>[product-collection key=empty]</p><p>b</p>'));
        $this->assertSame('<p>a</p><p>b</p>', $this->shortcodes->htmlIn('<p>a</p><p>[product-collection key=nobody]</p><p>b</p>'));
        $this->assertSame('<p>a</p><p>b</p>', $this->shortcodes->htmlIn('<p>a</p><p>[product-collection]</p><p>b</p>'));
    }

    #[Test]
    public function a_collection_deleted_for_good_leaves_the_text_around_the_bracket(): void
    {
        $collection = $this->collection([$this->product('Camera')]);
        $collection->delete();
        $collection->forceDelete();

        $this->assertSame('<p>a</p><p>b</p>', $this->shortcodes->htmlIn('<p>a</p><p>'.self::BRACKET.'</p><p>b</p>'));
    }

    #[Test]
    public function a_line_of_text_prints_nothing_for_it(): void
    {
        $this->collection([$this->product('Trail camera')]);

        $this->assertSame('Sale ', $this->shortcodes->html('Sale '.self::BRACKET));

        $resolved = $this->shortcodes->resolve('Sale '.self::BRACKET);

        $this->assertInstanceOf(ShortcodeText::class, $resolved);
        $this->assertSame('Sale ', $resolved->toHtml());
    }

    /* What excerpts, descriptions, feeds and the search index read. */
    #[Test]
    public function plain_text_and_an_excerpt_have_neither_the_bracket_nor_the_products(): void
    {
        $this->collection([$this->product('Trail camera')]);
        $text = $this->app->make(PageText::class);

        $this->assertSame('Sale ', $this->shortcodes->plain('Sale '.self::BRACKET));
        $this->assertSame('', $this->shortcodes->text('<p>'.self::BRACKET.'</p>'));
        $this->assertSame('Before After', $text->plainFromHtml('<p>Before '.self::BRACKET.'After</p>'));
    }

    /* §4.3: a category's description is printed through `PageText`, its snippet is not. */
    #[Test]
    public function the_meta_description_of_a_category_has_the_words_around_it(): void
    {
        $this->collection([$this->product('Trail camera')]);
        $category = new Category(['name' => 'Cameras', 'slug' => 'cameras', 'is_published' => true, 'description' => ['en' => '<p>Intro words.</p><p>'.self::BRACKET.'</p>']]);
        $category->saveAsRoot();

        $this->assertSame('Intro words.', $category->seoFallback('en')?->description);
        $this->assertStringContainsString('Trail camera', (string) $category->textAbove('en'));
    }

    /* A module's text reaches the site through `PageText`, not through a block. */
    #[Test]
    public function a_description_of_a_module_prints_it(): void
    {
        $this->collection([$this->product('Trail camera')]);

        $html = $this->app->make(PageText::class)->html('<p>'.self::BRACKET.'</p>');

        $this->assertStringStartsWith('<section', trim($html));
        $this->assertStringContainsString('Trail camera', $html);
    }

    /* Every shortcode is drawn for the list agents and the panel read, this one with no key. */
    #[Test]
    public function the_list_of_shortcodes_asks_for_it_with_no_key_and_gets_nothing(): void
    {
        $this->collection([$this->product('Trail camera')]);

        $listed = array_column($this->shortcodes->list(), null, 'name')[CollectionShortcode::NAME];

        $this->assertSame('', $listed['html']);
        $this->assertSame('', $listed['plain']);
        $this->assertTrue($listed['block']);
    }

    #[Test]
    public function the_menu_offers_a_ready_bracket_for_each_collection(): void
    {
        $this->collection([], ['key' => 'zoom', 'title' => 'Zoom lenses']);
        $this->collection([], ['key' => 'off', 'title' => 'Winter', 'is_enabled' => false]);
        $this->collection([], ['key' => 'gone', 'title' => 'Gone'])->delete();
        $this->collection([], ['key' => 'cameras', 'title' => 'Cameras']);

        /** @var list<array{name: string, insert: string|null, description: string|null, block: bool}> $answer */
        $answer = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/shortcodes')->assertOk()->json('data');
        $rows = array_values(array_filter($answer, static fn (array $row): bool => $row['name'] === CollectionShortcode::NAME));

        $this->assertSame([
            '[product-collection key=cameras]',
            '[product-collection key=zoom]',
            '[product-collection key=off]',
        ], array_column($rows, 'insert'));

        $this->assertSame(['Cameras', 'Zoom lenses', 'Winter (off)'], array_column($rows, 'description'));
        $this->assertSame([true, true, true], array_column($rows, 'block'));
    }

    /* No bare name: it would insert a bracket that prints nothing. */
    #[Test]
    public function with_no_collections_the_menu_has_no_row_for_it(): void
    {
        $this->assertNotContains(CollectionShortcode::NAME, array_column($this->shortcodes->menu(), 'name'));
    }
}
