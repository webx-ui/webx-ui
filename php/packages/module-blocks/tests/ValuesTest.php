<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Tests\Fixtures\PictureType;

/**
 * What a block stores is not what its template reads: the type that stored a value is asked to
 * fill the rest of it back in, the same way a screen's values are read for the site.
 */
final class ValuesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(FieldTypes::class)->register('wx-picture', new PictureType);
    }

    #[Test]
    public function a_field_type_fills_in_what_the_value_does_not_store(): void
    {
        $this->publish('figure', '<img src="{{ $image["url"] ?? "" }}" alt="{{ $image["alt"] ?? "" }}">', [], [
            'schema' => [['id' => 'image', 'type' => 'wx-picture']],
        ]);

        $html = $this->render([$this->node('figure', ['image' => ['path' => 'cat.jpg', 'alt' => 'A cat']])]);

        $this->assertSame('<img src="https://files.example.test/cat.jpg" alt="A cat">', $html);
    }

    #[Test]
    public function the_script_of_a_block_is_handed_the_same_values_as_the_template(): void
    {
        $this->publish('figure', '<figure data-wx-values="{{ json_encode($block->values) }}"></figure>', [], [
            'schema' => [['id' => 'image', 'type' => 'wx-picture']],
        ]);

        $html = $this->render([$this->node('figure', ['image' => ['path' => 'cat.jpg']])]);

        $this->assertStringContainsString('https:\/\/files.example.test\/cat.jpg', $html);
    }

    #[Test]
    public function a_field_inside_a_repeater_is_resolved_too(): void
    {
        // The schema of a block names its fields `id`; `wx-repeater` looks for the fields of
        // one item by `name`, so this is the case that breaks when the two are not bridged.
        $this->publish('gallery', '@foreach($items ?? [] as $item)<img src="{{ $item["image"]["url"] ?? "" }}">@endforeach', [], [
            'schema' => [[
                'id' => 'items',
                'type' => 'wx-repeater',
                'children' => [['id' => 'image', 'type' => 'wx-picture']],
            ]],
        ]);

        $html = $this->render([$this->node('gallery', ['items' => [
            ['image' => ['path' => 'one.jpg']],
            ['image' => ['path' => 'two.jpg']],
        ]])]);

        $this->assertSame(
            '<img src="https://files.example.test/one.jpg"><img src="https://files.example.test/two.jpg">',
            $html,
        );
    }

    #[Test]
    public function a_field_in_columns_inside_a_repeater_is_resolved_too(): void
    {
        // Columns are layout, and they have ids: named by the bridge like any node, a row
        // would stop the repeater's walk as if it were a field, and the fields in it would be
        // nobody's.
        $this->publish('gallery', '@foreach($items ?? [] as $item)<img src="{{ $item["image"]["url"] ?? "" }}">@endforeach', [], [
            'schema' => [[
                'id' => 'items',
                'type' => 'wx-repeater',
                'children' => [[
                    'id' => 'columns',
                    'type' => 'wx-row',
                    'children' => [
                        ['id' => 'left', 'type' => 'wx-col', 'props' => ['md' => 12], 'children' => [['id' => 'image', 'type' => 'wx-picture']]],
                        ['id' => 'right', 'type' => 'wx-col', 'props' => ['md' => 12], 'children' => [['id' => 'caption', 'type' => 'wx-input']]],
                    ],
                ]],
            ]],
        ]);

        $html = $this->render([$this->node('gallery', ['items' => [['image' => ['path' => 'one.jpg'], 'caption' => 'x']]])]);

        $this->assertSame('<img src="https://files.example.test/one.jpg">', $html);
    }

    #[Test]
    public function a_field_inside_a_card_is_resolved_too(): void
    {
        // Layout is walked through: a card holds the block's own fields, not an item's.
        $this->publish('figure', '{{ $image["url"] ?? "" }}', [], [
            'schema' => [[
                'id' => 'main',
                'type' => 'wx-card',
                'children' => [['id' => 'image', 'type' => 'wx-picture']],
            ]],
        ]);

        $html = $this->render([$this->node('figure', ['image' => ['path' => 'cat.jpg']])]);

        $this->assertSame('https://files.example.test/cat.jpg', $html);
    }

    #[Test]
    public function a_value_the_schema_does_not_name_arrives_as_it_is(): void
    {
        // A field dropped from the block after the page was written: nobody knows what its
        // value means any more, so nothing is done to it.
        $this->publish('figure', '{{ $image["path"] ?? "" }}/{{ $block->value("gone")["path"] ?? "" }}', [], [
            'schema' => [['id' => 'image', 'type' => 'wx-picture']],
        ]);

        $html = $this->render([$this->node('figure', [
            'image' => ['path' => 'cat.jpg'],
            'gone' => ['path' => 'dog.jpg'],
        ])]);

        $this->assertSame('cat.jpg/dog.jpg', $html);
    }

    #[Test]
    public function the_nested_tree_of_a_container_is_not_touched(): void
    {
        $this->publish('text', '<p>{{ $body }}</p>');
        $this->publish('section', '<section>@blocks("content")</section>', ['allow' => ['text']], [
            'schema' => [['id' => 'content', 'type' => 'wx-blocks']],
        ]);

        $html = $this->render([$this->node('section', [
            'content' => [$this->node('text', ['body' => 'inside'])],
        ])]);

        $this->assertSame('<section><p>inside</p></section>', $html);
    }

    #[Test]
    public function the_panel_draws_a_block_on_resolved_values_as_well(): void
    {
        // The preview and the page print the same thing, or the preview is not one.
        $block = $this->publish('figure', '<img src="{{ $image["url"] ?? "" }}">', [], [
            'schema' => [['id' => 'image', 'type' => 'wx-picture']],
        ]);

        $type = $this->app->make(BlockTypes::class)->find($block->slug);
        $this->assertNotNull($type);

        $html = $this->app->make(Renderer::class)->draw($type, ['image' => ['path' => 'cat.jpg']], 'k1');

        $this->assertStringContainsString('<img src="https://files.example.test/cat.jpg">', $html);
    }

    #[Test]
    public function the_check_before_publishing_runs_on_resolved_values_as_well(): void
    {
        // A template that reads `$image['url']` must pass on a sample that stores only a path.
        $block = $this->publish('figure', '<img src="{{ $image["url"] }}">', [], [
            'schema' => [['id' => 'image', 'type' => 'wx-picture']],
            'sample' => ['image' => ['path' => 'cat.jpg']],
        ]);

        $type = $this->app->make(BlockTypes::class)->find($block->slug);
        $this->assertNotNull($type);

        $this->app->make(Renderer::class)->check($type);

        $this->addToAssertionCount(1);
    }
}
