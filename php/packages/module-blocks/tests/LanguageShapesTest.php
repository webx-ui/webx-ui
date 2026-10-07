<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Foundation\Application;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Panel\DropsTranslations;
use WebxUi\Blocks\Panel\Publisher;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * Switching a field's `localized` after pages were written with it. The values follow the schema
 * when the version is published; until then — and for a value nobody converted — reading copes
 * with either shape, and no write turns a value it could not read into nulls.
 */
final class LanguageShapesTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'ru']]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);
    }

    #[Test]
    public function making_fields_localized_converts_what_the_pages_hold_and_keeps_lists_whole(): void
    {
        $block = $this->publish('qa', '<div data-wx-block="qa">{{ $f_title }}|{{ implode(",", $f_tags ?? []) }}|@foreach ($f_rep ?? [] as $row){{ $row["name"] }}@endforeach</div>', [], [
            'schema' => $this->schema(false),
        ]);

        $page = Page::query()->create(['title' => 'Test', 'blocks' => [$this->node('qa', [
            'f_title' => 'Plain title',
            'f_tags' => ['en', 'ru'],
            'f_rep' => [['name' => 'Row plain']],
        ], 'k1')]]);

        // The flag switched in the draft: nothing converted yet, and the site reads the plain shape.
        $block->saveVersion(['schema' => $this->schema(true)]);
        $changes = $this->app->make(Publisher::class)->languageChanges($block->refresh());

        $this->assertCount(3, $changes['flips']);
        $this->assertCount(1, $changes['entities']);

        $this->app->make(Publisher::class)->publish($block->refresh());

        $values = $page->refresh()->blocks[0]['values'];

        $this->assertSame(['en' => 'Plain title'], $values['f_title']);
        $this->assertSame(['en' => ['en', 'ru']], $values['f_tags'], 'a list of tags is one value, kept under the main language');
        $this->assertSame([['name' => ['en' => 'Row plain']]], $values['f_rep']);
        $this->assertSame('<div data-wx-block="qa">Plain title|en,ru|Row plain</div>', $this->render($page->blocks));
    }

    #[Test]
    public function a_write_in_one_language_keeps_the_values_of_fields_it_could_not_read_as_maps(): void
    {
        $this->publish('qa', '<div data-wx-block="qa">{{ $f_title }}</div>', [], ['schema' => $this->schema(true)]);

        // Written before the flag: plain values under fields that are now localized.
        $page = Page::query()->create(['title' => 'Test', 'blocks' => [$this->node('qa', [
            'f_title' => 'Plain title',
            'f_tags' => ['en', 'ru'],
        ], 'k1')]]);

        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'ops' => [['op' => 'set', 'key' => 'k1', 'values' => ['f_title' => 'Русский'], 'locale' => 'ru']],
        ])->assertOk();

        $values = $page->refresh()->draft['blocks'][0]['values'];

        $this->assertSame(['en' => 'Plain title', 'ru' => 'Русский'], $values['f_title'], 'the plain value is the main language\'s');
        $this->assertSame(['en', 'ru'], $values['f_tags'], 'never [null, null]');
    }

    #[Test]
    public function taking_localized_off_over_two_languages_is_asked_and_then_keeps_the_main_one(): void
    {
        $block = $this->publish('qa', '<div data-wx-block="qa">{{ $f_title }}</div>', [], ['schema' => $this->schema(true)]);

        $page = Page::query()->create(['title' => 'Test', 'blocks' => [$this->node('qa', [
            'f_title' => ['en' => 'English', 'ru' => 'Русский'],
        ], 'k1')]]);

        $block->saveVersion(['schema' => $this->schema(false)]);

        try {
            $this->app->make(Publisher::class)->publish($block->refresh());
            $this->fail('Words in another language were dropped without asking.');
        } catch (DropsTranslations $drops) {
            $this->assertSame((int) $page->id, (int) $drops->entities[0]['id']);
        }

        $this->assertSame(['en' => 'English', 'ru' => 'Русский'], $page->refresh()->blocks[0]['values']['f_title']);

        $this->app->make(Publisher::class)->publish($block->refresh(), dropTranslations: true);

        $this->assertSame('English', $page->refresh()->blocks[0]['values']['f_title']);
        $this->assertSame('<div data-wx-block="qa">English</div>', $this->render($page->blocks));
    }

    #[Test]
    public function a_map_left_under_a_plain_field_is_read_in_one_language_rather_than_failing(): void
    {
        $this->publish('qa', '<div data-wx-block="qa">{{ $f_title }}</div>', [], ['schema' => $this->schema(false)]);

        $html = $this->render([$this->node('qa', ['f_title' => ['en' => 'EN title via locale']])]);

        $this->assertSame('<div data-wx-block="qa">EN title via locale</div>', $html);
    }

    #[Test]
    public function the_agent_hears_about_the_conversion_before_publishing(): void
    {
        $this->publish('qa', '<div data-wx-block="qa">{{ $f_title }}</div>', [], ['schema' => $this->schema(false)]);

        Page::query()->create(['title' => 'Test', 'blocks' => [$this->node('qa', ['f_title' => 'Plain'], 'k1')]]);

        $answer = $this->agent('update', ['slug' => 'qa', 'schema' => $this->schema(true)]);
        $answer->assertOk()->assertSee('localized-changes');

        $this->assertSame(1, Block::query()->where('slug', 'qa')->count());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function schema(bool $localized): array
    {
        $flag = $localized ? ['localized' => true] : [];

        return [
            ['id' => 'f_title', 'type' => 'wx-input'] + $flag,
            ['id' => 'f_tags', 'type' => 'wx-tags-input'] + $flag,
            ['id' => 'f_rep', 'type' => 'wx-repeater', 'children' => [['id' => 'name', 'type' => 'wx-input'] + $flag]],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('blocks_'.$tool));

        return WebxServer::actingAs($this->editor(), 'cms')->tool($bound, $arguments);
    }
}
