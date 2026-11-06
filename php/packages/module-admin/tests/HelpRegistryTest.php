<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Help\HelpException;
use WebxUi\Admin\Help\HelpFile;
use WebxUi\Admin\Help\HelpPage;
use WebxUi\Admin\Help\HelpRegistry;

final class HelpRegistryTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/Fixtures/help';

    /**
     * @param  list<string>  $directories
     */
    private function help(array $directories = ['owner', 'satellite', 'project']): HelpRegistry
    {
        $help = new HelpRegistry;

        foreach ($directories as $directory) {
            $help->directory(self::FIXTURES.'/'.$directory);
        }

        return $help;
    }

    /**
     * @param  list<string>  $directories
     */
    private function page(string $key, string $locale, array $directories = ['owner', 'satellite', 'project']): HelpPage
    {
        $page = $this->help($directories)->page($key, $locale);

        $this->assertNotNull($page);

        return $page;
    }

    #[Test]
    public function the_registry_is_one_for_every_provider(): void
    {
        $this->assertSame($this->app->make(HelpRegistry::class), $this->app->make(HelpRegistry::class));
    }

    #[Test]
    public function a_file_comes_apart_into_its_header_and_sections(): void
    {
        $file = HelpFile::read(self::FIXTURES.'/owner/en/shop.item-form.md', 'en');

        $this->assertSame('en', $file->locale);
        $this->assertSame('Item', $file->title);
        $this->assertSame('shop#items', $file->guide);
        $this->assertSame(['shop.item-dialog', 'shop.item-extra'], $file->include);
        $this->assertSame('Why the section exists.', $file->about);
        $this->assertSame([
            ['title' => 'How to add an item', 'body' => "1. Press the button.\n2. Fill in the form."],
        ], $file->howto);
        $this->assertSame(['code', 'price', 'seo-title'], array_keys($file->fields));
        $this->assertSame('What the code changes on the site.', $file->fields['code']);
        $this->assertTrue($file->isOwner());
    }

    #[Test]
    public function a_file_may_have_only_what_a_satellite_brings(): void
    {
        $file = HelpFile::read(self::FIXTURES.'/satellite/en/shop.item-form.md', 'en');

        $this->assertNull($file->title);
        $this->assertSame('', $file->about);
        $this->assertSame([], $file->include);
        $this->assertFalse($file->isOwner());
        $this->assertSame(['stock', 'price'], array_keys($file->fields));
    }

    #[Test]
    public function the_header_takes_quotes_a_colon_and_a_bracketed_list(): void
    {
        $file = HelpFile::parse(<<<'MD'
            ---
            title: "Stock: statuses"
            include: [one.first, 'two.second']
            guide:
            ---

            # about

            Text.
            MD, 'test.md', 'en');

        $this->assertSame('Stock: statuses', $file->title);
        $this->assertSame(['one.first', 'two.second'], $file->include);
        $this->assertNull($file->guide);
    }

    #[Test]
    public function windows_line_ends_and_a_byte_order_mark_change_nothing(): void
    {
        $file = HelpFile::parse("\u{FEFF}---\r\ntitle: Item\r\n---\r\n\r\n# about\r\n\r\nFirst.\r\n\r\nSecond.\r\n", 'test.md', 'en');

        $this->assertSame('Item', $file->title);
        $this->assertSame("First.\n\nSecond.", $file->about);
    }

    #[Test]
    public function a_heading_inside_a_code_fence_is_text(): void
    {
        $file = HelpFile::parse("# about\n\n```\n# not a section\n## nor a heading\n```\n", 'test.md', 'en');

        $this->assertSame("```\n# not a section\n## nor a heading\n```", $file->about);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function brokenFiles(): iterable
    {
        yield 'unclosed header' => ["---\ntitle: Item\n\n# about\n\nText.", 'not closed'];
        yield 'unknown header line' => ["---\nowner: me\n---\n\n# about\n\nText.", 'owner: me'];
        yield 'unknown section' => ["# notes\n\nText.", '# notes'];
        yield 'section twice' => ["# about\n\nOne.\n\n# about\n\nTwo.", 'comes twice'];
        yield 'text before the sections' => ["Text.\n\n# about\n\nText.", 'before the first section'];
        yield 'heading under about' => ["# about\n\n## A heading\n\nText.", '## A heading'];
        yield 'heading before the sections' => ['## code', '## code'];
        yield 'text under fields' => ["# fields\n\nLoose text.\n\n## code\n\nText.", '# fields'];
        yield 'field twice' => ["# fields\n\n## code\n\nOne.\n\n## code\n\nTwo.", '[code]'];
    }

    #[Test]
    #[DataProvider('brokenFiles')]
    public function a_file_of_the_wrong_shape_names_itself_and_the_problem(string $text, string $problem): void
    {
        try {
            HelpFile::parse($text, 'resources/help/en/broken.md', 'en');
            $this->fail('A broken file was taken apart.');
        } catch (HelpException $exception) {
            $this->assertStringContainsString('resources/help/en/broken.md', $exception->getMessage());
            $this->assertStringContainsString($problem, $exception->getMessage());
        }
    }

    #[Test]
    public function a_file_that_is_not_there_says_so(): void
    {
        $this->expectException(HelpException::class);

        HelpFile::read(self::FIXTURES.'/owner/en/absent.md', 'en');
    }

    #[Test]
    public function the_owner_speaks_for_the_page_and_the_others_add_to_it(): void
    {
        $page = $this->page('shop.item-form', 'en');

        $this->assertSame('shop.item-form', $page->key);
        $this->assertSame('Item', $page->title);
        $this->assertSame('Why the section exists.', $page->about);
        $this->assertSame('shop#items', $page->guide);
        $this->assertSame(['shop.item-dialog', 'shop.item-extra'], $page->include);
        $this->assertSame(
            ['How to add an item', 'How to mark an item as sold out'],
            array_column($page->howto, 'title'),
        );
        $this->assertSame(['code', 'price', 'seo-title', 'stock'], array_keys($page->fields));
    }

    #[Test]
    public function a_field_described_twice_comes_from_the_last_part(): void
    {
        $this->assertSame(
            "The project's words about the price.",
            $this->page('shop.item-form', 'en')->field('price'),
        );
        $this->assertSame(
            "The satellite's words about the price.",
            $this->page('shop.item-form', 'en', ['owner', 'satellite'])->field('price'),
        );
    }

    #[Test]
    public function the_owners_scenarios_go_first_whoever_booted_first(): void
    {
        $page = $this->page('shop.item-form', 'en', ['satellite', 'owner']);

        $this->assertSame('Item', $page->title);
        $this->assertSame(
            ['How to add an item', 'How to mark an item as sold out'],
            array_column($page->howto, 'title'),
        );
    }

    #[Test]
    public function a_part_of_a_taken_out_satellite_goes_with_it(): void
    {
        $page = $this->page('shop.item-form', 'en', ['owner']);

        $this->assertNull($page->field('stock'));
        $this->assertCount(1, $page->howto);
    }

    #[Test]
    public function a_key_nobody_owns_has_no_page(): void
    {
        $this->assertSame([], $this->help()->parts('shop.absent', 'en'));
        $this->assertNull($this->help()->page('shop.absent', 'en'));

        // A satellite has a file for it, but no title and no about to open a page with.
        $this->assertCount(1, $this->help()->parts('shop.orphan', 'en'));
        $this->assertNull($this->help()->page('shop.orphan', 'en'));
    }

    #[Test]
    public function a_page_with_no_tree_keeps_its_labels_in_the_order_of_the_file(): void
    {
        $this->assertSame(
            ['Search by name', 'Only in stock'],
            array_keys($this->page('shop.list', 'en')->fields),
        );
    }

    #[Test]
    public function shared_fields_fit_any_screen_and_the_screens_own_file_is_stronger(): void
    {
        $page = $this->page('shop.item-form', 'en', ['owner']);

        $this->assertSame('What the shared text does.', $page->field('seo-text'));
        $this->assertSame("The screen's own words about this field.", $page->field('seo-title'));
        $this->assertSame('What the shared text does.', $this->page('shop.list', 'en', ['owner'])->field('seo-text'));
    }

    #[Test]
    public function shared_fields_of_a_later_directory_win_and_are_no_key(): void
    {
        $help = $this->help();

        $this->assertSame("The project's words about the shared text.", $help->shared('en')['seo-text']);
        $this->assertSame('What the shared title does.', $help->shared('en')['seo-title']);
        $this->assertSame([], $help->parts(HelpRegistry::SHARED, 'en'));
        $this->assertNotContains(HelpRegistry::SHARED, $help->keys());
    }

    #[Test]
    public function a_shared_file_holds_only_fields(): void
    {
        $directory = sys_get_temp_dir().'/webx-help-'.bin2hex(random_bytes(4));
        mkdir($directory.'/en', recursive: true);
        file_put_contents($directory.'/en/_fields.md', "# about\n\nNot here.\n");

        $help = new HelpRegistry;
        $help->directory($directory);

        try {
            $help->shared('en');
            $this->fail('A shared file with an about was accepted.');
        } catch (HelpException $exception) {
            $this->assertStringContainsString('_fields.md', $exception->getMessage());
        } finally {
            unlink($directory.'/en/_fields.md');
            rmdir($directory.'/en');
            rmdir($directory);
        }
    }

    #[Test]
    public function the_language_of_the_panel_comes_first(): void
    {
        $page = $this->page('shop.item-form', 'ru', ['owner', 'satellite']);

        $this->assertSame('ru', $page->locale);
        $this->assertSame('Товар', $page->title);
        $this->assertSame('Зачем нужен раздел.', $page->about);
        $this->assertSame(
            ['Как добавить товар', 'Как отметить, что товар закончился'],
            array_column($page->howto, 'title'),
        );
        $this->assertSame('Что делает общий текст.', $page->field('seo-text'));
    }

    #[Test]
    public function a_language_with_no_file_reads_english_and_says_so(): void
    {
        $page = $this->page('shop.item-form', 'de');

        $this->assertSame('en', $page->locale);
        $this->assertSame('Item', $page->title);
        $this->assertSame('What the shared text does.', $this->page('shop.list', 'de', ['owner'])->field('seo-text'));
    }

    #[Test]
    public function every_part_goes_down_the_chain_on_its_own(): void
    {
        // The project has no Russian file, so its part alone is English; the rest stay Russian.
        $page = $this->page('shop.item-form', 'ru');

        $this->assertSame('ru', $page->locale);
        $this->assertSame('Что код меняет на сайте.', $page->field('code'));
        $this->assertSame('Что статус наличия показывает покупателю.', $page->field('stock'));
        $this->assertSame("The project's words about the price.", $page->field('price'));
        $this->assertSame(['ru', 'ru', 'en'], array_map(
            static fn (HelpFile $part): string => $part->locale,
            $this->help()->parts('shop.item-form', 'ru'),
        ));
    }

    #[Test]
    public function a_file_is_taken_whole_and_never_filled_in_from_english(): void
    {
        // The Ukrainian file has no fields at all, the English one has two.
        $page = $this->page('shop.list', 'uk', ['owner']);

        $this->assertSame('uk', $page->locale);
        $this->assertSame('Товари', $page->title);
        $this->assertSame([], $page->fields);
    }

    #[Test]
    public function the_keys_are_what_english_owns(): void
    {
        $this->assertSame(['shop.item-form', 'shop.list'], $this->help()->keys());
    }

    #[Test]
    public function a_broken_file_stays_out_of_the_keys_and_speaks_when_opened(): void
    {
        $help = $this->help();

        $this->assertNotContains('broken.page', $help->keys());

        $this->expectException(HelpException::class);
        $this->expectExceptionMessage('broken.page.md');

        $help->page('broken.page', 'en');
    }

    #[Test]
    public function a_directory_with_nothing_in_it_is_no_error_and_is_counted_once(): void
    {
        $help = $this->help(['owner', 'owner', 'absent']);

        $this->assertSame(['shop.item-form', 'shop.list'], $help->keys());
        $this->assertCount(1, $help->parts('shop.item-form', 'en'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignKeys(): iterable
    {
        yield 'up the tree' => ['../owner/en/shop.list'];
        yield 'a path' => ['shop/list'];
        yield 'one word' => ['shop'];
        yield 'shared file' => ['_fields'];
        yield 'capitals' => ['Shop.List'];
        yield 'a line end' => ["shop.list\n"];
    }

    #[Test]
    #[DataProvider('foreignKeys')]
    public function a_key_of_another_shape_reaches_no_file(string $key): void
    {
        $this->assertSame([], $this->help()->parts($key, 'en'));
        $this->assertNull($this->help()->page($key, 'en'));
    }

    #[Test]
    public function a_language_of_another_shape_reads_english(): void
    {
        $this->assertSame('en', $this->page('shop.list', '../owner/uk')->locale);
        $this->assertSame('en', $this->page('shop.list', '')->locale);
    }

    #[Test]
    public function a_key_may_have_three_parts(): void
    {
        $directory = sys_get_temp_dir().'/webx-help-'.bin2hex(random_bytes(4));
        mkdir($directory.'/en', recursive: true);
        file_put_contents($directory.'/en/shop.exchange.profiles.md', "---\ntitle: Profiles\n---\n\n# about\n\nText.\n");

        $help = new HelpRegistry;
        $help->directory($directory);

        try {
            $this->assertSame(['shop.exchange.profiles'], $help->keys());
            $this->assertSame('Profiles', $help->page('shop.exchange.profiles', 'en')?->title);
        } finally {
            unlink($directory.'/en/shop.exchange.profiles.md');
            rmdir($directory.'/en');
            rmdir($directory);
        }
    }
}
