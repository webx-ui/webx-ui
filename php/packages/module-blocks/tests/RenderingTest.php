<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Facades\Blocks;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Blocks\Tests\Fixtures\Page;

final class RenderingTest extends TestCase
{
    #[Test]
    public function a_block_prints_its_template_on_its_values(): void
    {
        $this->publish('hero', '<section data-wx-block="hero" class="b-hero b-hero--{{ $tone }}"><h1>{{ $title }}</h1></section>');

        $html = $this->render([$this->node('hero', ['title' => 'Hello & welcome', 'tone' => 'dark'])]);

        $this->assertSame(
            '<section data-wx-block="hero" class="b-hero b-hero--dark"><h1>Hello &amp; welcome</h1></section>',
            $html,
        );
    }

    /*
     * A localized field keeps a language map, and a template wants one language. Before this
     * the map reached the template whole, Blade refused to print an array, and the renderer
     * caught that and printed nothing — a block that vanished from the page rather than one
     * that looked wrong.
     */
    #[Test]
    public function a_localized_field_reaches_the_template_in_one_language(): void
    {
        $this->publish('greet', '<p>{{ $title }}</p>', [], [
            'schema' => [['id' => 'title', 'type' => 'wx-input', 'localized' => true]],
        ]);

        $node = $this->node('greet', ['title' => ['en' => 'Hello', 'ru' => 'Привет']]);

        app()->setLocale('ru');
        $this->assertSame('<p>Привет</p>', $this->render([$node]));

        app()->setLocale('en');
        $this->assertSame('<p>Hello</p>', $this->render([$node]));
    }

    /* Down the same chain every localized value is read through: asked for, default, fallback. */
    #[Test]
    public function a_language_nobody_wrote_falls_back_rather_than_blanking_the_block(): void
    {
        $this->publish('greet', '<p>{{ $title }}</p>', [], [
            'schema' => [['id' => 'title', 'type' => 'wx-input', 'localized' => true]],
        ]);

        app()->setLocale('uk');

        $html = $this->render([
            $this->node('greet', ['title' => ['en' => 'Hello', 'uk' => '']]),
        ]);

        $this->assertSame('<p>Hello</p>', $html);
    }

    #[Test]
    public function the_template_sees_the_block_and_the_entity(): void
    {
        $this->publish('meta', '{{ $block->key }}/{{ $block->type }}/v{{ $block->version }}/{{ $entity?->title }}/{{ $block->value("project-name") }}');

        $page = new Page(['title' => 'About']);

        $html = $this->render([$this->node('meta', ['project-name' => 'WebX'], 'abc')], $page);

        $this->assertSame('abc/meta/v1/About/WebX', $html);
    }

    #[Test]
    public function a_field_the_content_does_not_have_yet_is_null_rather_than_an_error(): void
    {
        // The block gained `subtitle` after this page was written: the old page must still print.
        $this->publish('hero', '<h1>{{ $title }}</h1>@if($subtitle)<p>{{ $subtitle }}</p>@endif', [], [
            'schema' => [['id' => 'title', 'type' => 'wx-input'], ['id' => 'subtitle', 'type' => 'wx-input']],
        ]);

        $this->assertSame('<h1>Old</h1>', $this->render([$this->node('hero', ['title' => 'Old'])]));
        $this->assertSame('<h1>New</h1><p>more</p>', $this->render([$this->node('hero', ['title' => 'New', 'subtitle' => 'more'])]));
    }

    #[Test]
    public function a_value_cannot_take_the_place_of_what_the_template_relies_on(): void
    {
        $this->publish('shadow', '{{ $block->key }}:{{ is_object($__env) ? "env" : "gone" }}');

        $html = $this->render([$this->node('shadow', ['block' => 'not me', '__env' => 'nor me'], 'k')]);

        $this->assertSame('k:env', $html);
    }

    #[Test]
    public function an_unknown_type_is_left_out_and_noted(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(static fn (string $message): bool => str_contains($message, '"gone"'));

        $this->publish('text', '<p>{{ $body }}</p>');

        $html = $this->render([
            $this->node('text', ['body' => 'one']),
            $this->node('gone', ['body' => 'two']),
            $this->node('text', ['body' => 'three']),
        ]);

        $this->assertSame('<p>one</p><p>three</p>', $html);
    }

    #[Test]
    public function a_disabled_type_still_renders_where_it_stands(): void
    {
        $this->publish('old', '<p>still here</p>', ['is_enabled' => false]);

        $this->assertSame('<p>still here</p>', $this->render([$this->node('old')]));
    }

    #[Test]
    public function the_site_prints_the_published_version_and_the_preview_the_draft(): void
    {
        $block = $this->publish('hero', '<h1>v1 {{ $title }}</h1>');
        $block->saveVersion(['template' => '<h1>v2 {{ $title }}</h1>']);

        $blocks = [$this->node('hero', ['title' => 'T'], 'h')];

        $this->assertSame('<h1>v1 T</h1>', $this->render($blocks));
        $this->assertSame('<!--wx:h--><h1>v2 T</h1><!--/wx:h-->', $this->render($blocks, preview: true));
    }

    #[Test]
    public function the_preview_falls_back_to_the_published_version_when_there_is_no_draft(): void
    {
        $this->publish('hero', '<h1>{{ $title }}</h1>');

        $this->assertSame('<!--wx:h--><h1>T</h1><!--/wx:h-->', $this->render([$this->node('hero', ['title' => 'T'], 'h')], preview: true));
    }

    #[Test]
    public function a_failing_block_does_not_take_the_page_with_it(): void
    {
        Exceptions::fake();

        $this->publish('text', '<p>{{ $body }}</p>');
        // Fine on its sample — that is what let it be published — and broken on this page's values.
        $this->publish('broken', "<div>\n@if(\$explode) @php throw new RuntimeException('boom') @endphp @endif\n</div>");

        $html = $this->render([
            $this->node('text', ['body' => 'before']),
            $this->node('broken', ['explode' => true]),
            $this->node('text', ['body' => 'after']),
        ]);

        $this->assertSame('<p>before</p><p>after</p>', $html);
        Exceptions::assertReported(static fn (RuntimeException $e): bool => $e->getMessage() === 'boom');
    }

    #[Test]
    public function in_the_preview_a_failure_is_a_notice_with_the_line(): void
    {
        Exceptions::fake();

        $this->publish('broken', "<div>\n@if(\$explode) @php throw new RuntimeException('boom') @endphp @endif\n</div>");

        $html = $this->render([$this->node('broken', ['explode' => true], 'b')], preview: true);

        $this->assertStringStartsWith('<!--wx:b--><div class="wx-block-error" data-wx-block-error="b" data-wx-block="broken">', $html);
        $this->assertStringContainsString('boom (line 2)', $html);
        $this->assertStringEndsWith('<!--/wx:b-->', $html);
        Exceptions::assertNothingReported();
    }

    #[Test]
    public function blocks_nest_through_the_directive(): void
    {
        $this->publish('section', '<section class="b-section b-section--{{ $background }}">@blocks(\'content\')</section>', ['allow' => ['text']]);
        $this->publish('text', '<p>{{ $body }}</p>');

        $html = $this->render([
            $this->node('section', [
                'background' => 'muted',
                'content' => [
                    $this->node('text', ['body' => 'one']),
                    $this->node('text', ['body' => 'two']),
                ],
            ]),
        ]);

        $this->assertSame('<section class="b-section b-section--muted"><p>one</p><p>two</p></section>', $html);
    }

    #[Test]
    public function a_hidden_block_is_left_out_of_the_page(): void
    {
        $this->publish('text', '<p>{{ $body }}</p>');

        $html = $this->render([
            $this->node('text', ['body' => 'one']),
            ['hidden' => true] + $this->node('text', ['body' => 'two']),
            $this->node('text', ['body' => 'three']),
        ]);

        // Nothing at all, not an empty wrapper: the page reads as if the block were not there.
        $this->assertSame('<p>one</p><p>three</p>', $html);
    }

    #[Test]
    public function a_hidden_block_is_left_out_of_the_preview_too(): void
    {
        // Otherwise there is no telling which of the blocks on screen is the switched-off one.
        $this->publish('text', '<p>{{ $body }}</p>');

        $html = $this->render([
            ['hidden' => true] + $this->node('text', ['body' => 'off'], 'a'),
            $this->node('text', ['body' => 'on'], 'b'),
        ], preview: true);

        $this->assertSame('<!--wx:b--><p>on</p><!--/wx:b-->', $html);
    }

    #[Test]
    public function a_hidden_container_takes_what_is_inside_it_and_keeps_it(): void
    {
        $this->publish('section', '<section>@blocks</section>', ['allow' => ['text']]);
        $this->publish('text', '<p>{{ $body }}</p>');

        $inside = [
            $this->node('text', ['body' => 'one']),
            ['hidden' => true] + $this->node('text', ['body' => 'two']),
        ];
        $tree = [['hidden' => true] + $this->node('section', ['content' => $inside], 'outer')];

        $this->assertSame('', $this->render($tree));

        // Switched back on, the inside is exactly what it was — including the block that was
        // switched off in there on its own.
        $tree[0]['hidden'] = false;

        $this->assertSame('<section><p>one</p></section>', $this->render($tree));
    }

    #[Test]
    public function the_directive_defaults_to_the_content_field_and_tolerates_an_empty_one(): void
    {
        $this->publish('section', '<section>@blocks</section>');

        $this->assertSame('<section></section>', $this->render([$this->node('section')]));
        $this->assertSame('<section></section>', $this->render([$this->node('section', ['content' => 'not a list'])]));
    }

    #[Test]
    public function nested_blocks_carry_the_entity_and_their_own_markers(): void
    {
        $this->publish('section', '<section>@blocks</section>');
        $this->publish('text', '<p>{{ $entity?->title }}: {{ $body }}</p>');

        $page = new Page(['title' => 'About']);
        $blocks = [$this->node('section', ['content' => [$this->node('text', ['body' => 'x'], 'inner')]], 'outer')];

        $this->assertSame('<section><p>About: x</p></section>', $this->render($blocks, $page));
        $this->assertSame(
            '<!--wx:outer--><section><!--wx:inner--><p>About: x</p><!--/wx:inner--></section><!--/wx:outer-->',
            $this->render($blocks, $page, preview: true),
        );
    }

    #[Test]
    public function nesting_stops_at_the_limit(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(static fn (string $message): bool => str_contains($message, 'deeper than the limit of 2'));
        $this->app['config']->set('webx-blocks.max_depth', 2);

        $this->publish('section', '<s>@blocks</s>');
        $this->publish('text', '<p>{{ $body }}</p>');

        $html = $this->render([
            $this->node('section', ['content' => [
                $this->node('text', ['body' => 'level 1']),
                $this->node('section', ['content' => [
                    $this->node('text', ['body' => 'level 2, too deep']),
                ]]),
            ]]),
        ]);

        $this->assertSame('<s><p>level 1</p><s></s></s>', $html);
    }

    #[Test]
    public function the_trait_casts_the_column_and_lists_the_types_used(): void
    {
        $this->publish('hero', '<h1>{{ $title }}</h1>');
        $this->publish('section', '<s>@blocks</s>');
        $this->publish('text', '<p>{{ $body }}</p>');

        $page = Page::query()->create([
            'title' => 'Home',
            'blocks' => [
                $this->node('hero', ['title' => 'Hi']),
                $this->node('section', ['content' => [
                    $this->node('text', ['body' => 'a']),
                    $this->node('text', ['body' => 'b']),
                ]]),
                'not a node',
            ],
        ]);

        $page = $page->fresh();

        $this->assertNotNull($page);
        $this->assertSame(['hero', 'section', 'text'], $page->blockTypes());
        $this->assertSame('<h1>Hi</h1><s><p>a</p><p>b</p></s>', (string) $page->renderBlocks());
        $this->assertNull($page->draft);
        $this->assertSame([], (new Page(['title' => 'Empty']))->blockTypes());
    }

    #[Test]
    public function the_renderer_records_which_versions_a_response_printed(): void
    {
        $this->publish('hero', '<h1></h1>');
        $text = $this->publish('text', '<p></p>');
        $text->saveVersion(['template' => '<p>v2</p>']);
        $text->publish();

        $renderer = $this->app->make(Renderer::class);
        $this->render([$this->node('hero'), $this->node('text'), $this->node('text')]);

        $this->assertSame(['hero' => 1, 'text' => 2], $renderer->used());

        $renderer->flush();
        $this->assertSame([], Blocks::used());
    }

    #[Test]
    public function every_version_compiles_to_its_own_file(): void
    {
        $compiler = $this->app->make(TemplateCompiler::class);
        $block = $this->publish('hero', '<h1>one</h1>');

        $this->render([$this->node('hero')]);
        $one = $compiler->directory().'/hero-1-'.substr(sha1('<h1>one</h1>'), 0, 12).'.php';
        $this->assertFileExists($one);

        $block->saveVersion(['template' => '<h1>two</h1>']);
        $block->publish();
        $this->assertSame('<h1>two</h1>', $this->render([$this->node('hero')]));
        $two = $compiler->directory().'/hero-2-'.substr(sha1('<h1>two</h1>'), 0, 12).'.php';
        $this->assertFileExists($two);

        // The file is the cache: gone, it is written again from the template in the database.
        unlink($two);
        $this->assertSame('<h1>two</h1>', $this->render([$this->node('hero')]));
    }

    #[Test]
    public function two_databases_sharing_the_directory_do_not_serve_each_other_s_templates(): void
    {
        // A test suite on an in-memory database and the developer's site compile into the same
        // directory, and each has its own `hero` at version 1.
        $this->publish('hero', '<h1>one</h1>');
        $this->assertSame('<h1>one</h1>', $this->render([$this->node('hero')]));

        Block::query()->delete();
        BlockVersion::query()->delete();
        $this->app->make(BlockTypes::class)->forget();

        $this->publish('hero', '<h1>other</h1>');
        $this->assertSame('<h1>other</h1>', $this->render([$this->node('hero')]));
    }

    #[Test]
    public function nothing_is_printed_for_nothing(): void
    {
        $this->assertSame('', $this->render([]));
        $this->assertSame('', (string) Blocks::render(null));
        $this->assertSame('', $this->render([['type' => ''], ['values' => []], 'text']));
    }
}
