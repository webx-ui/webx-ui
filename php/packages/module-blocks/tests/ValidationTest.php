<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\ContentValues;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * Every value a block holds is held to its field type's rules, on every door content comes in by,
 * and every block to where it may stand. Before, an agent, an autosave and a publication all
 * stored whatever they were given — a rating of nine out of five, an option nobody offered, a
 * `javascript:` link — and the site printed it.
 */
final class ValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);

        $this->publish('every', '<div data-wx-block="every">{{ $count }}</div>', [], [
            'schema' => [
                ['id' => 'count', 'type' => 'wx-input-number', 'props' => ['min' => 0, 'max' => 10]],
                ['id' => 'tone', 'type' => 'wx-select', 'props' => ['options' => [['value' => 'light', 'label' => 'Light'], ['value' => 'dark', 'label' => 'Dark']]]],
                ['id' => 'stars', 'type' => 'wx-rate', 'props' => ['max' => 5]],
                ['id' => 'days', 'type' => 'wx-checkbox-group', 'props' => ['max' => 2, 'options' => [['value' => 'mon', 'label' => 'Mon'], ['value' => 'tue', 'label' => 'Tue'], ['value' => 'wed', 'label' => 'Wed']]]],
                ['id' => 'rows', 'type' => 'wx-repeater', 'props' => ['max' => 3], 'children' => [['id' => 'name', 'type' => 'wx-input']]],
                ['id' => 'when', 'type' => 'wx-date-picker'],
                ['id' => 'colour', 'type' => 'wx-color-picker'],
                ['id' => 'link', 'type' => 'wx-link'],
                ['id' => 'note', 'type' => 'wx-text', 'props' => ['text' => 'A note, not a field']],
            ],
        ]);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function refused(): iterable
    {
        yield 'a number past its max' => ['count', 50];
        yield 'a number under its min' => ['count', -5];
        yield 'an option nobody offered' => ['tone', 'zzz'];
        yield 'a rating past its max' => ['stars', 9];
        yield 'more boxes than max' => ['days', ['mon', 'tue', 'wed']];
        yield 'more rows than max' => ['rows', [['name' => 'a'], ['name' => 'b'], ['name' => 'c'], ['name' => 'd']]];
        yield 'not a date' => ['when', 'not a date'];
        yield 'not a colour' => ['colour', 'red"><script>alert(1)</script>'];
        yield 'a javascript: link' => ['link', ['target' => 'url', 'url' => 'javascript:alert(1)']];
    }

    #[Test]
    #[DataProvider('refused')]
    public function an_agents_value_that_breaks_its_field_is_refused_naming_the_block_and_field(string $field, mixed $value): void
    {
        $page = Page::query()->create(['title' => 'Test']);

        $this->agent('set_content', [
            'entity' => 'note',
            'id' => $page->id,
            'blocks' => [['key' => 'k-one', 'type' => 'every', 'values' => [$field => $value]]],
        ])->assertHasErrors(["every [k-one], field [{$field}]"]);

        $this->assertNull($page->refresh()->draft, 'nothing was written');

        // A dry run says the same and writes the same nothing.
        $this->agent('set_content', [
            'entity' => 'note',
            'id' => $page->id,
            'blocks' => [['key' => 'k-one', 'type' => 'every', 'values' => [$field => $value]]],
            'dry_run' => true,
        ])->assertHasErrors(["field [{$field}]"]);
    }

    #[Test]
    public function values_inside_the_bounds_are_written(): void
    {
        $page = Page::query()->create(['title' => 'Test']);

        $this->agent('set_content', [
            'entity' => 'note',
            'id' => $page->id,
            'blocks' => [['key' => 'k-one', 'type' => 'every', 'values' => [
                'count' => 10,
                'tone' => 'dark',
                'stars' => 5,
                'days' => ['mon', 'tue'],
                'rows' => [['name' => 'a']],
                'when' => '2026-10-07',
                'colour' => '#ff0000',
                'link' => ['target' => 'url', 'url' => 'https://example.test/about'],
            ]]],
        ])->assertOk();

        $this->assertSame(10, $page->refresh()->draft['blocks'][0]['values']['count']);
    }

    #[Test]
    public function an_edit_names_the_field_too_and_leaves_the_rest_alone(): void
    {
        $page = Page::query()->create(['title' => 'Test', 'blocks' => [$this->node('every', ['count' => 1], 'k-one')]]);

        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'ops' => [['op' => 'set', 'key' => 'k-one', 'values' => ['stars' => 9]]],
        ])->assertHasErrors(['field [stars]']);

        $this->assertNull($page->refresh()->draft);
    }

    #[Test]
    public function the_panels_save_is_refused_under_the_blocks_key_and_field(): void
    {
        $page = Page::query()->create(['title' => 'Test']);

        try {
            $page->storeBlocks([$this->node('every', ['count' => 50], 'k-one')]);
            $this->fail('A number past its max was kept.');
        } catch (ValidationException $refused) {
            $this->assertArrayHasKey('blocks.k-one.count', $refused->errors());
        }
    }

    #[Test]
    public function a_draft_written_around_the_checks_is_refused_on_publication(): void
    {
        $page = Page::query()->create(['title' => 'Test']);

        // Straight into the column, the way an old draft or a script would have left it.
        $page->setAttribute('draft', ['blocks' => [$this->node('every', ['stars' => 9], 'k-one')]]);
        $page->save();

        try {
            $page->publish();
            $this->fail('An invalid draft was published.');
        } catch (ValidationException $refused) {
            $this->assertArrayHasKey('blocks.k-one.stars', $refused->errors());
        }

        $this->assertNull($page->refresh()->published_at, 'nothing reached the site');
    }

    #[Test]
    public function a_container_takes_only_what_its_field_allows_and_no_more_than_its_max(): void
    {
        $this->publish('quote', '<q data-wx-block="quote">{{ $words }}</q>');
        $this->publish('hero', '<h1 data-wx-block="hero">{{ $title }}</h1>');
        $this->publish('section', '<section data-wx-block="section">@blocks(\'inner\')</section>', [], [
            'schema' => [['id' => 'inner', 'type' => 'wx-blocks', 'props' => ['allow' => ['quote'], 'max' => 2]]],
        ]);

        $page = Page::query()->create(['title' => 'Test']);

        $this->agent('set_content', [
            'entity' => 'note',
            'id' => $page->id,
            'blocks' => [['key' => 's1', 'type' => 'section', 'values' => ['inner' => [
                ['key' => 'h1', 'type' => 'hero', 'values' => []],
            ]]]],
        ])->assertHasErrors(['hero [h1]']);

        $this->agent('set_content', [
            'entity' => 'note',
            'id' => $page->id,
            'blocks' => [['key' => 's1', 'type' => 'section', 'values' => ['inner' => [
                ['type' => 'quote', 'values' => []],
                ['type' => 'quote', 'values' => []],
                ['type' => 'quote', 'values' => []],
            ]]]],
        ])->assertHasErrors(['section [s1], field [inner]']);
    }

    #[Test]
    public function adding_into_a_block_that_is_not_a_container_says_so(): void
    {
        $this->publish('hero', '<h1 data-wx-block="hero">{{ $title }}</h1>');
        $this->publish('quote', '<q data-wx-block="quote">{{ $words }}</q>');

        $page = Page::query()->create(['title' => 'Test', 'blocks' => [$this->node('hero', ['title' => 'Hi'], 'h1')]]);

        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'dry_run' => true,
            'ops' => [['op' => 'add', 'type' => 'quote', 'parent' => 'h1', 'field' => 'content']],
        ])->assertHasErrors(['not a container']);
    }

    #[Test]
    public function a_field_the_container_does_not_declare_is_no_place(): void
    {
        $this->publish('quote', '<q data-wx-block="quote">{{ $words }}</q>');
        $this->publish('section', '<section data-wx-block="section">@blocks(\'inner\')</section>', [], [
            'schema' => [['id' => 'inner', 'type' => 'wx-blocks']],
        ]);

        $page = Page::query()->create(['title' => 'Test', 'blocks' => [$this->node('section', [], 's1')]]);

        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'ops' => [['op' => 'add', 'type' => 'quote', 'parent' => 's1', 'field' => 'content']],
        ])->assertHasErrors(['no wx-blocks field [content]']);

        // Without `field`, the one container field is the place.
        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'ops' => [['op' => 'add', 'type' => 'quote', 'parent' => 's1']],
        ])->assertOk();

        $this->assertSame('quote', $page->refresh()->draft['blocks'][0]['values']['inner'][0]['type']);
    }

    #[Test]
    public function a_type_kept_for_somewhere_else_is_refused_at_the_top_and_max_per_entity_counts(): void
    {
        $this->publish('aside', '<aside data-wx-block="aside">x</aside>', ['allowed_in' => ['region:header']]);
        $this->publish('banner', '<div data-wx-block="banner">x</div>', ['max_per_entity' => 1]);

        $problems = $this->app->make(ContentValues::class)->problems([
            $this->node('aside', [], 'a1'),
            $this->node('banner', [], 'b1'),
            $this->node('banner', [], 'b2'),
        ]);

        $described = array_map(ContentValues::describe(...), $problems);

        $this->assertCount(2, $described);
        $this->assertStringContainsString('aside [a1]', $described[0]);
        $this->assertStringContainsString('banner', $described[1]);
    }

    #[Test]
    public function a_duplicate_copies_the_block_with_new_keys_right_after_it(): void
    {
        $this->publish('quote', '<q data-wx-block="quote">{{ $words }}</q>');
        $this->publish('section', '<section data-wx-block="section">@blocks(\'inner\')</section>', [], [
            'schema' => [['id' => 'inner', 'type' => 'wx-blocks']],
        ]);

        $page = Page::query()->create(['title' => 'Test', 'blocks' => [
            ['key' => 's1', 'type' => 'section', 'values' => ['inner' => [['key' => 'q1', 'type' => 'quote', 'values' => ['words' => 'Hi']]]]],
            ['key' => 'q9', 'type' => 'quote', 'values' => ['words' => 'Last']],
        ]]);

        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'ops' => [['op' => 'duplicate', 'key' => 's1']],
        ])->assertOk()->assertStructuredContent(static function (AssertableJson $json): void {
            // The section's copy and the quote inside it.
            self::assertSame(2, $json->etc()->toArray()['keys_made']);
        });

        $tree = $page->refresh()->draft['blocks'];

        $this->assertCount(3, $tree);
        $this->assertSame(['section', 'section', 'quote'], array_column($tree, 'type'));
        $this->assertNotSame('s1', $tree[1]['key']);
        $this->assertNotSame('q1', $tree[1]['values']['inner'][0]['key'], 'the copy\'s children have keys of their own');
        $this->assertSame('Hi', $tree[1]['values']['inner'][0]['values']['words']);
    }

    #[Test]
    public function a_wx_text_is_no_field_a_value_can_be_kept_under(): void
    {
        $page = Page::query()->create(['title' => 'Test']);

        $this->agent('set_content', [
            'entity' => 'note',
            'id' => $page->id,
            'blocks' => [['key' => 'k-one', 'type' => 'every', 'values' => ['note' => 'x']]],
        ])->assertHasErrors(['no field [note]']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments, ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('blocks_'.$tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }
}
