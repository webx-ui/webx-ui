<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Bundles;
use WebxUi\Blocks\Rendering\Calls;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Tests\Fixtures\Page;

/**
 * `<x-webx-block>` through the real path: a template in the tables with the tag in it, compiled
 * by Blade and rendered by the renderer, and a view file from disk with the tag and no blocks
 * around it at all.
 */
final class ComponentsTest extends TestCase
{
    private string $views;

    protected function setUp(): void
    {
        parent::setUp();

        // A module's views, with the partial a declared place falls back to.
        $this->views = sys_get_temp_dir().'/webx-components-'.getmypid().'-'.uniqid();
        File::ensureDirectoryExists($this->views.'/partials');
        File::put($this->views.'/partials/card.blade.php', '<article class="std">{{ $card["title"] }}{{ $slot }}</article>');
        View::addNamespace('webx-demo', $this->views);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->views);

        parent::tearDown();
    }

    #[Test]
    public function the_graph_reads_literal_types_only(): void
    {
        $template = <<<'BLADE'
            <x-webx-block type="card" :card="$a > 1" />
            <x-webx-block type='badge'/>
            <x-webx-block :type="$dynamic" />
            <x-webx-block type="{{ $echoed }}" />
            <x-webx-block data-type="nope" type="card">x</x-webx-block>
            BLADE;

        $this->assertSame(['badge', 'card'], Calls::of($template));
        $this->assertSame([3, 4], Calls::dynamic($template));
    }

    #[Test]
    public function a_block_from_the_tables_calls_a_component_with_values(): void
    {
        $this->callable('badge', '<span class="b-badge b-badge--{{ $tone }}">{{ $label }}</span>', [
            ['id' => 'label', 'type' => 'wx-input'],
            ['id' => 'tone', 'type' => 'wx-input'],
        ]);
        $this->publish('hero', '<section data-wx-block="hero"><x-webx-block type="badge" :label="$title" tone="dark" /></section>');

        $html = $this->render([$this->node('hero', ['title' => 'New & fresh'])]);

        $this->assertSame('<section data-wx-block="hero"><span class="b-badge b-badge--dark">New &amp; fresh</span></section>', $html);
    }

    #[Test]
    public function a_view_file_without_any_blocks_calls_a_component(): void
    {
        $this->callable('card', '<div class="b-card">{{ $card["title"] }}</div>', [
            ['id' => 'card', 'type' => 'wx-data', 'props' => ['shape' => 'demo.card']],
        ], ['sample' => ['card' => ['title' => 'Sample']]]);

        $html = Blade::render('<main><x-webx-block type="card" :card="$card" /></main>', ['card' => ['title' => 'Borscht']]);

        $this->assertSame('<main><div class="b-card">Borscht</div></main>', $html);
    }

    #[Test]
    public function the_fallback_prints_when_the_type_is_missing_or_never_published(): void
    {
        $call = '<x-webx-block type="card" :card="$card" fallback="webx-demo::partials.card" />';

        $this->assertSame('<article class="std">Borscht</article>', Blade::render($call, ['card' => ['title' => 'Borscht']]));

        // A draft nobody published is still not on the site.
        $block = Block::query()->create(['slug' => 'card', 'title' => 'Card', 'kind' => 'component']);
        $block->saveVersion(['template' => '<div>custom</div>', 'schema' => [['id' => 'card', 'type' => 'wx-data']]]);

        $this->assertSame('<article class="std">Soup</article>', Blade::render($call, ['card' => ['title' => 'Soup']]));

        // Published, it takes over — and switching it off does not give the fallback back.
        $block->publish();
        $block->update(['is_enabled' => false]);

        $this->assertSame('<div>custom</div>', Blade::render($call, ['card' => ['title' => 'Borscht']]));
    }

    #[Test]
    public function a_missing_type_without_a_fallback_is_a_gap_and_a_log_line(): void
    {
        $log = Log::spy();

        $this->assertSame('<p></p>', Blade::render('<p><x-webx-block type="ghost" /></p>'));

        $log->shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function slots_arrive_as_markup_and_an_undelivered_one_is_empty(): void
    {
        $this->callable('section', '<section>{{ $slot }}|{{ $aside }}|{{ $footer }}</section>', [
            ['id' => 'aside', 'type' => 'wx-slot'],
            ['id' => 'footer', 'type' => 'wx-slot'],
        ]);

        $html = Blade::render(<<<'BLADE'
            <x-webx-block type="section"><b>{{ $name }}</b><x-slot:aside><i>side</i></x-slot:aside></x-webx-block>
            BLADE, ['name' => 'A & B']);

        $this->assertSame('<section><b>A &amp; B</b>|<i>side</i>|</section>', trim($html));
    }

    #[Test]
    public function the_fallback_gets_the_slots_too(): void
    {
        $html = Blade::render(
            '<x-webx-block type="card" :card="$card" fallback="webx-demo::partials.card"><em>new</em></x-webx-block>',
            ['card' => ['title' => 'Soup']],
        );

        $this->assertSame('<article class="std">Soup<em>new</em></article>', $html);
    }

    #[Test]
    public function a_call_inside_a_block_knows_its_entity_and_its_key(): void
    {
        $this->callable('meta', '{{ $block->key }}:{{ $entity?->title }}:{{ $block->depth }}');
        $this->publish('hero', '<x-webx-block type="meta" /><x-webx-block type="meta" />');

        $html = $this->render([$this->node('hero', [], 'h1')], new Page(['title' => 'About']));

        $this->assertSame('h1/meta-1:About:1h1/meta-2:About:1', $html);
    }

    #[Test]
    public function a_cycle_at_run_time_is_refused_and_the_rest_prints(): void
    {
        $card = $this->callable('card', '<i>card</i>');
        $this->callable('grid', '<b>grid<x-webx-block type="card" /></b>');
        $this->publish('shelf', '<div><x-webx-block type="grid" /></div>');

        // Written straight into the pointer: publishing would have refused it (§3.6), and this
        // is the net under that — a database edited by hand, an import from before the check.
        $card->saveVersion(['template' => '<i>card<x-webx-block type="grid" /></i>']);
        $card->forceFill(['published_version_id' => $card->draft_version_id, 'draft_version_id' => null])->save();
        $this->app->make(BlockTypes::class)->forget();

        $this->assertSame('<div><b>grid<i>card</i></b></div>', $this->render([$this->node('shelf')]));

        $html = $this->render([$this->node('shelf')], null, preview: true);

        $this->assertStringContainsString('<b>grid<i>card', $html);
        $this->assertStringContainsString('&quot;grid&quot; calls itself through &quot;card&quot;', $html);
    }

    #[Test]
    public function a_component_that_throws_leaves_a_gap_not_an_empty_page(): void
    {
        $broken = $this->callable('broken', '<i>ok</i>');
        $this->publish('grid', '<ul><li>one</li><x-webx-block type="broken" /><li>two</li></ul>');

        // Past the checks, the way a failure on some page's data gets past them.
        $broken->saveVersion(['template' => '<i>{{ intdiv(1, 0) }}</i>']);
        $broken->forceFill(['published_version_id' => $broken->draft_version_id, 'draft_version_id' => null])->save();
        $this->app->make(BlockTypes::class)->forget();

        $this->assertSame('<ul><li>one</li><li>two</li></ul>', $this->render([$this->node('grid')]));

        $preview = $this->render([$this->node('grid')], null, preview: true);
        $this->assertStringContainsString('data-wx-block-error', $preview);
        $this->assertStringContainsString('(line 1)', $preview);
    }

    #[Test]
    public function the_styles_of_a_called_component_are_in_the_bundle(): void
    {
        $this->callable('badge', '<span class="b-badge">!</span>', [], ['styles' => '.b-badge { color: red; }']);
        $this->publish('hero', '<section><x-webx-block type="badge" /></section>', [], ['styles' => '.b-hero { color: blue; }']);

        $this->render([$this->node('hero')]);

        $bundle = $this->app->make(Bundles::class)->build($this->app->make(Renderer::class)->usedTypes());

        $this->assertNotNull($bundle);
        $this->assertStringContainsString('.b-badge { color: red; }', $bundle->css);
        $this->assertStringContainsString('.b-hero { color: blue; }', $bundle->css);
    }

    #[Test]
    public function the_preview_calls_the_draft_of_a_component(): void
    {
        $badge = $this->callable('badge', '<span>live</span>');
        $badge->saveVersion(['template' => '<span>draft</span>']);
        $this->publish('hero', '<x-webx-block type="badge" />');

        $this->assertSame('<span>live</span>', $this->render([$this->node('hero', [], 'h')]));
        $this->assertSame('<!--wx:h--><span>draft</span><!--/wx:h-->', $this->render([$this->node('hero', [], 'h')], null, preview: true));
    }

    #[Test]
    public function the_graph_is_written_with_every_version(): void
    {
        $this->callable('badge', '<span></span>');
        $hero = $this->publish('hero', '<x-webx-block type="badge" /><x-webx-block type="badge" />');

        $this->assertSame(['badge'], $hero->publishedVersion?->calls());
    }

    /**
     * A component, published, with the schema given — or none, which is a component with no input.
     *
     * @param  list<array<string, mixed>>  $schema
     * @param  array<string, mixed>  $content
     */
    private function callable(string $slug, string $template, array $schema = [], array $content = []): Block
    {
        return $this->publish($slug, $template, ['kind' => 'component'], ['schema' => $schema] + $content);
    }
}
