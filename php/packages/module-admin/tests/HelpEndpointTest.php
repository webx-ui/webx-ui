<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Help\HelpException;
use WebxUi\Admin\Help\HelpRegistry;
use WebxUi\Admin\Tests\Fixtures\Editor;

final class HelpEndpointTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/Fixtures/help/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->help('owner');
        $this->help('dialog');

        Screens::register('shop.item-form', [
            ['id' => 'tabs', 'type' => 'wx-tabs', 'children' => [
                ['id' => 'general', 'type' => 'wx-tab', 'label' => 'trans::webx-admin::nav.system', 'children' => [
                    ['id' => 'card', 'type' => 'wx-card', 'children' => [
                        ['id' => 'code', 'type' => 'wx-input', 'name' => 'code', 'label' => 'Code', 'help' => 'Latin letters.', 'required' => true],
                        ['id' => 'price', 'type' => 'wx-input', 'name' => 'price', 'label' => 'Price', 'can' => 'shop.prices'],
                        ['id' => 'undescribed', 'type' => 'wx-input', 'name' => 'undescribed', 'label' => 'Undescribed'],
                    ]],
                ]],
                ['id' => 'more', 'type' => 'wx-tab', 'label' => 'More', 'children' => [
                    ['id' => 'seo-title', 'type' => 'wx-input', 'name' => 'seo_title', 'label' => 'Title'],
                    ['id' => 'seo-text', 'type' => 'wx-input', 'name' => 'seo_text', 'label' => 'Text', 'visible' => ['when' => 'seo_title', 'not' => '']],
                    ['id' => 'retired', 'type' => 'wx-input', 'name' => 'retired', 'label' => 'Retired', 'visible' => false],
                    ['id' => 'offices', 'type' => 'wx-repeater', 'name' => 'offices', 'label' => 'Offices', 'children' => [
                        ['id' => 'office-city', 'type' => 'wx-input', 'name' => 'city', 'label' => 'City'],
                    ]],
                ]],
                ['id' => 'nothing', 'type' => 'wx-tab', 'label' => 'History', 'children' => [
                    ['id' => 'log', 'type' => 'wx-history'],
                ]],
            ]],
        ]);

        Screens::register('shop.item-dialog', [
            ['id' => 'card', 'type' => 'wx-card', 'children' => [
                ['id' => 'note', 'type' => 'wx-input', 'name' => 'note', 'label' => 'Note'],
            ]],
        ]);
    }

    #[Test]
    public function the_files_get_their_labels_hints_and_tabs_from_the_tree(): void
    {
        $this->actingAs(new Editor(['shop.prices']))
            ->getJson('/api/cms/help/shop.item-form')
            ->assertOk()
            ->assertJsonPath('data.key', 'shop.item-form')
            ->assertJsonPath('data.title', 'Item')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.about', 'Why the section exists.')
            ->assertJsonPath('data.howto.0.title', 'How to add an item')
            ->assertJsonPath('data.groups.0.id', 'general')
            ->assertJsonPath('data.groups.0.label', 'System')
            ->assertJsonPath('data.groups.0.fields.0', [
                'id' => 'code',
                'label' => 'Code',
                'hint' => 'Latin letters.',
                'required' => true,
                'body' => 'What the code changes on the site.',
            ])
            ->assertJsonPath('data.groups.0.fields.1.body', 'What the price changes on the site.')
            ->assertJsonPath('data.groups.1.id', 'more');
    }

    #[Test]
    public function a_field_nobody_described_is_shown_by_its_label_alone(): void
    {
        $this->actingAs(new Editor)
            ->getJson('/api/cms/help/shop.item-form')
            ->assertJsonPath('data.groups.0.fields.1.id', 'undescribed')
            ->assertJsonPath('data.groups.0.fields.1.body', null);
    }

    #[Test]
    public function can_cuts_a_field_out_of_the_help_as_it_does_out_of_the_form(): void
    {
        $this->actingAs(new Editor)
            ->getJson('/api/cms/help/shop.item-form')
            ->assertOk()
            ->assertJsonMissingPath('data.groups.0.fields.2')
            ->assertDontSee('What the price changes on the site.');

        $this->actingAs(new Editor(['shop.prices']))
            ->getJson('/api/cms/help/shop.item-form')
            ->assertJsonPath('data.groups.0.fields.1.id', 'price');
    }

    #[Test]
    public function a_tab_cut_by_can_takes_its_group_with_it(): void
    {
        Screens::extend('shop.item-form', [['op' => 'set', 'target' => 'more', 'can' => 'shop.more']]);

        $this->assertSame(['general', 'shop.item-dialog._screen'], array_column($this->page('shop.item-form')['groups'], 'id'));
    }

    #[Test]
    public function a_satellite_adds_its_group_and_says_what_its_field_does(): void
    {
        $this->assertSame(['general', 'more', 'shop.item-dialog._screen'], array_column($this->page('shop.item-form')['groups'], 'id'));

        $this->help('satellite');

        Screens::extend('shop.item-form', [[
            'op' => 'add',
            'target' => 'tabs',
            'node' => ['id' => 'stock-tab', 'type' => 'wx-tab', 'label' => 'Stock', 'children' => [
                ['id' => 'stock', 'type' => 'wx-input', 'name' => 'stock', 'label' => 'Availability'],
            ]],
        ]]);

        $page = $this->page('shop.item-form');

        $this->assertSame(['general', 'more', 'stock-tab', 'shop.item-dialog._screen'], array_column($page['groups'], 'id'));
        $this->assertSame('Stock', $page['groups'][2]['label']);
        $this->assertSame('What the stock status shows to a buyer.', $page['groups'][2]['fields'][0]['body']);
        $this->assertSame('How to mark an item as sold out', $page['howto'][1]['title']);
    }

    #[Test]
    public function a_condition_keeps_a_field_and_a_node_hidden_for_good_does_not(): void
    {
        $ids = array_column($this->page('shop.item-form')['groups'][1]['fields'], 'id');

        $this->assertContains('seo-text', $ids);
        $this->assertNotContains('retired', $ids);
    }

    #[Test]
    public function the_fields_of_a_repeater_row_follow_the_repeater(): void
    {
        $ids = array_column($this->page('shop.item-form')['groups'][1]['fields'], 'id');

        $this->assertSame(['offices', 'office-city'], array_slice($ids, -2));
    }

    #[Test]
    public function a_tab_with_no_fields_has_no_group(): void
    {
        $this->assertNotContains('nothing', array_column($this->page('shop.item-form')['groups'], 'id'));
    }

    #[Test]
    public function the_shared_descriptions_fit_a_field_the_screen_says_nothing_of(): void
    {
        $fields = $this->page('shop.item-form')['groups'][1]['fields'];

        $this->assertSame("The screen's own words about this field.", $fields[0]['body']);
        $this->assertSame('What the shared text does.', $fields[1]['body']);
    }

    #[Test]
    public function a_screen_with_no_tabs_is_one_group_with_no_label(): void
    {
        $this->actingAs(new Editor)
            ->getJson('/api/cms/help/shop.item-dialog')
            ->assertJsonCount(1, 'data.groups')
            ->assertJsonPath('data.groups.0.label', null)
            ->assertJsonPath('data.groups.0.fields.0.label', 'Note')
            ->assertJsonPath('data.groups.0.fields.0.body', 'What the note is for.');
    }

    #[Test]
    public function an_included_screen_follows_under_its_title_and_a_missing_one_is_passed_over(): void
    {
        // The file includes `shop.item-dialog` and `shop.item-extra`; only the first has a page.
        $groups = $this->page('shop.item-form')['groups'];
        $last = $groups[array_key_last($groups)];

        $this->assertCount(3, $groups);
        $this->assertSame('Item in a dialog', $last['label']);
        $this->assertSame('note', $last['fields'][0]['id']);
    }

    #[Test]
    public function a_page_with_no_tree_takes_its_labels_from_the_file(): void
    {
        $this->actingAs(new Editor)
            ->getJson('/api/cms/help/shop.list')
            ->assertOk()
            ->assertJsonCount(1, 'data.groups')
            ->assertJsonPath('data.groups.0.label', null)
            ->assertJsonPath('data.groups.0.fields.1', [
                'id' => 'Only in stock',
                'label' => 'Only in stock',
                'hint' => null,
                'required' => false,
                'body' => 'Hides what cannot be bought.',
            ]);
    }

    #[Test]
    public function a_key_may_have_three_parts_and_a_page_may_have_no_fields(): void
    {
        $this->actingAs(new Editor)
            ->getJson('/api/cms/help/shop.list.archive')
            ->assertOk()
            ->assertJsonPath('data.title', 'Archive')
            ->assertJsonPath('data.groups', []);
    }

    #[Test]
    public function the_text_and_the_labels_speak_the_panel_language(): void
    {
        $this->actingAs(new Editor)
            ->getJson('/api/cms/help/shop.item-form', ['X-Webx-Locale' => 'ru'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'ru')
            ->assertJsonPath('data.title', 'Товар')
            ->assertJsonPath('data.groups.0.label', 'Система')
            ->assertJsonPath('data.groups.0.fields.0.body', 'Что код меняет на сайте.');
    }

    #[Test]
    public function a_language_with_no_text_gets_the_english_one_and_says_so(): void
    {
        $this->actingAs(new Editor)
            ->getJson('/api/cms/help/shop.item-form', ['X-Webx-Locale' => 'uk'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.title', 'Item');
    }

    #[Test]
    public function an_unknown_key_is_a_404_in_the_words_of_the_screens(): void
    {
        $this->actingAs(new Editor)
            ->getJson('/api/cms/help/shop.nope')
            ->assertNotFound()
            ->assertJsonPath('message', 'There is no screen named shop.nope.');
    }

    #[Test]
    public function a_key_only_a_satellite_speaks_of_is_a_404(): void
    {
        $this->help('satellite');

        $this->actingAs(new Editor)->getJson('/api/cms/help/shop.orphan')->assertNotFound();
    }

    #[Test]
    public function a_broken_file_names_itself_rather_than_showing_half_a_page(): void
    {
        $this->help('project');

        $this->withoutExceptionHandling();
        $this->expectException(HelpException::class);
        $this->expectExceptionMessage('broken.page.md');

        $this->actingAs(new Editor)->getJson('/api/cms/help/broken.page');
    }

    #[Test]
    public function help_asks_the_database_nothing(): void
    {
        $this->actingAs(new Editor(['shop.prices']));

        DB::enableQueryLog();

        $this->getJson('/api/cms/help/shop.item-form')->assertOk();
        $this->getJson('/api/cms/help/shop.list')->assertOk();

        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function there_is_no_guide_until_the_site_says_where_the_guides_are(): void
    {
        $this->assertNull(config('webx-admin.docs_url'));

        $this->actingAs(new Editor)
            ->getJson('/api/cms/help/shop.item-form')
            ->assertOk()
            ->assertJsonPath('data.guide', null);
    }

    #[Test]
    public function the_address_of_the_guide_is_built_from_the_setting(): void
    {
        config(['webx-admin.docs_url' => 'https://docs.example.com/webx/']);

        $this->actingAs(new Editor)
            ->getJson('/api/cms/help/shop.item-form')
            ->assertJsonPath('data.guide', 'https://docs.example.com/webx/guide/shop#items');

        // A page whose file names no guide has none, whatever the site says.
        $this->actingAs(new Editor)
            ->getJson('/api/cms/help/shop.list')
            ->assertJsonPath('data.guide', null);
    }

    #[Test]
    public function the_manifest_carries_the_keys_and_none_of_the_texts(): void
    {
        $this->actingAs(new Editor)
            ->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.help', ['cache.index', 'shop.item-dialog', 'shop.item-form', 'shop.list', 'shop.list.archive'])
            ->assertDontSee('Why the section exists.');
    }

    private function help(string $directory): void
    {
        $this->app->make(HelpRegistry::class)->directory(self::FIXTURES.$directory);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $key): array
    {
        /** @var array<string, mixed> */
        return $this->actingAs(new Editor)->getJson('/api/cms/help/'.$key)->assertOk()->json('data');
    }
}
