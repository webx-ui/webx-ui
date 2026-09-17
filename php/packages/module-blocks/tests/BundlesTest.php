<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Facades\Blocks;
use WebxUi\Blocks\Models\BlockBundle;
use WebxUi\Blocks\Rendering\Bundles;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Blocks\Tests\Fixtures\Page;

final class BundlesTest extends TestCase
{
    #[Test]
    public function the_same_set_of_types_gives_one_hash_and_one_row(): void
    {
        $this->publish('hero', '<h1></h1>', [], ['styles' => '.hero{}']);
        $this->publish('text', '<p></p>', [], ['styles' => '.text{}']);

        $first = $this->bundleFor([$this->node('hero'), $this->node('text')]);
        $second = $this->bundleFor([$this->node('text'), $this->node('hero'), $this->node('text')]);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->hash, $second->hash);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $first->hash);
        $this->assertSame(1, BlockBundle::query()->count());
        $this->assertSame([['hero', 1], ['text', 1]], $first->types);
    }

    #[Test]
    public function styles_are_glued_in_sort_order_then_by_slug(): void
    {
        $this->publish('zebra', '<z></z>', ['sort' => 0], ['styles' => '.zebra{}']);
        $this->publish('apple', '<a></a>', ['sort' => 0], ['styles' => '.apple{}']);
        $this->publish('last', '<l></l>', ['sort' => 10], ['styles' => '.last{}']);
        $this->publish('first', '<f></f>', ['sort' => -1], ['styles' => '.first{}']);

        $bundle = $this->bundleFor([$this->node('zebra'), $this->node('last'), $this->node('apple'), $this->node('first')]);

        $this->assertNotNull($bundle);
        $this->assertSame(
            "/* first v1 */\n.first{}\n/* apple v1 */\n.apple{}\n/* zebra v1 */\n.zebra{}\n/* last v1 */\n.last{}\n",
            $bundle->css,
        );
    }

    #[Test]
    public function publishing_a_version_changes_the_hash_only_where_the_block_stands(): void
    {
        $hero = $this->publish('hero', '<h1></h1>', [], ['styles' => '.hero{color:red}']);
        $this->publish('text', '<p></p>', [], ['styles' => '.text{}']);

        $heroPage = [$this->node('hero')];
        $textPage = [$this->node('text')];

        $heroBefore = $this->bundleFor($heroPage)?->hash;
        $textBefore = $this->bundleFor($textPage)?->hash;

        $hero->saveVersion(['styles' => '.hero{color:blue}']);
        $hero->publish();

        $heroAfter = $this->bundleFor($heroPage);
        $textAfter = $this->bundleFor($textPage);

        $this->assertNotNull($heroAfter);
        $this->assertNotSame($heroBefore, $heroAfter->hash);
        $this->assertStringContainsString('color:blue', $heroAfter->css);
        $this->assertSame($textBefore, $textAfter?->hash);
    }

    #[Test]
    public function nested_blocks_join_the_bundle_and_a_set_without_styles_or_scripts_is_nothing(): void
    {
        $this->publish('section', '<s>@blocks</s>', [], ['styles' => '.section{}']);
        $this->publish('text', '<p>{{ $body }}</p>', [], ['styles' => '.text{}']);
        $this->publish('bare', '<b></b>');

        $bundle = $this->bundleFor([$this->node('section', ['content' => [$this->node('text', ['body' => 'a'])]])]);

        $this->assertNotNull($bundle);
        $this->assertSame([['section', 1], ['text', 1]], $bundle->types);

        $this->assertNull($this->bundleFor([$this->node('bare')]));
        $this->assertNull($this->bundleFor([]));
    }

    #[Test]
    public function a_hidden_block_does_not_bring_its_styles_to_the_page(): void
    {
        // The skip happens before the type is counted as used, so a switched-off block costs
        // the page nothing at all — not a tag, not a byte of CSS.
        $this->publish('section', '<s>@blocks</s>', [], ['styles' => '.section{}']);
        $this->publish('text', '<p>{{ $body }}</p>', [], ['styles' => '.text{}']);

        $bundle = $this->bundleFor([
            $this->node('section', ['content' => [$this->node('text', ['body' => 'a'])]]),
            ['hidden' => true] + $this->node('text', ['body' => 'b']),
        ]);

        $this->assertNotNull($bundle);
        $this->assertSame([['section', 1], ['text', 1]], $bundle->types);

        $this->assertNull($this->bundleFor([['hidden' => true] + $this->node('text', ['body' => 'b'])]));
    }

    #[Test]
    public function a_script_becomes_an_initialiser_per_instance_behind_the_runtime(): void
    {
        $this->publish('hero', '<h1></h1>', [], ['script' => "el.classList.add('ready')"]);
        $this->publish('text', '<p></p>', [], ['styles' => '.text{}']);

        $bundle = $this->bundleFor([$this->node('hero'), $this->node('text')]);

        $this->assertNotNull($bundle);
        $this->assertNotNull($bundle->js);
        $this->assertStringStartsWith('/*', $bundle->js);
        $this->assertStringContainsString('window.webx = {', $bundle->js);
        $this->assertStringContainsString("webx.block(\"hero\", async (el, values) => {\nel.classList.add('ready')\n});", $bundle->js);
        $this->assertStringNotContainsString('webx.block("text"', $bundle->js);

        $this->assertNull($this->bundleFor([$this->node('text')])?->js);
    }

    #[Test]
    public function the_directive_prints_the_tags_of_what_the_page_rendered(): void
    {
        $this->publish('hero', '<h1>{{ $title }}</h1>', [], ['styles' => '.hero{}', 'script' => 'el.dataset.ready = "1"']);
        $this->publish('text', '<p></p>', [], ['styles' => '.text{}']);
        $page = $this->views();

        $html = view()->file($page, ['blocks' => [$this->node('hero', ['title' => 'Hi'])]])->render();
        $hash = $this->bundleFor([$this->node('hero')])?->hash;

        $this->assertNotNull($hash);
        $this->assertStringContainsString("<head><link rel=\"stylesheet\" href=\"http://localhost/blocks/{$hash}.css\"></head>", $html);
        $this->assertStringContainsString("<h1>Hi</h1><script type=\"module\" src=\"http://localhost/blocks/{$hash}.js\"></script></body>", $html);

        // A page without a script has no script tag; a page without blocks has nothing.
        Blocks::flush();
        $html = view()->file($page, ['blocks' => [$this->node('text')]])->render();
        $this->assertStringContainsString('.css"></head>', $html);
        $this->assertStringNotContainsString('<script', $html);

        Blocks::flush();
        $html = view()->file($page, ['blocks' => []])->render();
        $this->assertSame('<html><head></head><body></body></html>', trim($html));
    }

    #[Test]
    public function the_runtime_can_be_printed_on_its_own_and_a_small_set_inlined(): void
    {
        $this->publish('hero', '<h1></h1>', [], ['styles' => '.hero{}', 'script' => 'el.innerHTML = "</script><b>"']);

        $bundles = $this->app->make(Bundles::class);
        $runtime = (string) $bundles->tags('runtime');

        $this->assertMatchesRegularExpression('#^<script src="http://localhost/blocks/runtime\.js\?v=[a-f0-9]{8}"></script>$#', $runtime);

        $this->app['config']->set('webx-blocks.bundles.inline_below', 100000);
        $this->render([$this->node('hero')]);
        $tags = (string) $bundles->tags();

        $this->assertStringStartsWith('<style>/* hero v1 */', $tags);
        $this->assertStringContainsString('<script type="module">/*', $tags);
        $this->assertStringContainsString('el.innerHTML = "<\/script><b>"', $tags);
        $this->assertStringNotContainsString('rel="stylesheet"', $tags);
    }

    #[Test]
    public function the_routes_serve_the_bundle_immutably_and_answer_404_for_what_is_not_there(): void
    {
        $this->publish('hero', '<h1></h1>', [], ['styles' => '.hero{}']);
        $bundle = $this->bundleFor([$this->node('hero')]);

        $this->assertNotNull($bundle);

        $css = $this->get("/blocks/{$bundle->hash}.css");
        $css->assertOk();
        $css->assertHeader('Content-Type', 'text/css; charset=UTF-8');
        $this->assertStringContainsString('immutable', (string) $css->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=31536000', (string) $css->headers->get('Cache-Control'));
        $this->assertSame("/* hero v1 */\n.hero{}\n", $css->getContent());

        // No script in the set: the JS of that hash does not exist, and says so without caching it.
        $js = $this->get("/blocks/{$bundle->hash}.js");
        $js->assertNotFound();
        $this->assertStringContainsString('no-store', (string) $js->headers->get('Cache-Control'));

        $this->get('/blocks/0123456789abcdef.css')->assertNotFound();
        $this->get('/blocks/not-a-hash.css')->assertNotFound();

        $runtime = $this->get('/blocks/runtime.js?v=deadbeef');
        $runtime->assertOk();
        $runtime->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
        $this->assertStringContainsString('window.webx = {', (string) $runtime->getContent());
    }

    #[Test]
    public function the_preview_bundles_the_draft(): void
    {
        $hero = $this->publish('hero', '<h1></h1>', [], ['styles' => '.hero{color:red}']);
        $hero->saveVersion(['styles' => '.hero{color:blue}']);

        $live = $this->bundleFor([$this->node('hero')]);
        $draft = $this->bundleFor([$this->node('hero')], preview: true);

        $this->assertNotNull($live);
        $this->assertNotNull($draft);
        $this->assertNotSame($live->hash, $draft->hash);
        $this->assertStringContainsString('color:red', $live->css);
        $this->assertStringContainsString('color:blue', $draft->css);
        $this->assertSame([['hero', 2]], $draft->types);
    }

    #[Test]
    public function a_handled_request_forgets_what_it_printed(): void
    {
        $this->publish('hero', '<h1></h1>', [], ['styles' => '.hero{}']);
        $this->render([$this->node('hero')]);

        $this->assertSame(['hero' => 1], Blocks::used());

        $this->get('/blocks/runtime.js')->assertOk();

        $this->assertSame([], Blocks::used());
    }

    #[Test]
    public function pruning_drops_the_bundles_of_superseded_versions_and_warming_builds_what_the_entities_need(): void
    {
        $hero = $this->publish('hero', '<h1></h1>', [], ['styles' => '.hero{v:1}']);
        $this->publish('text', '<p></p>', [], ['styles' => '.text{}']);

        $stale = $this->bundleFor([$this->node('hero'), $this->node('text')]);
        $kept = $this->bundleFor([$this->node('text')]);

        $hero->saveVersion(['styles' => '.hero{v:2}']);
        $hero->publish();

        $this->assertNotNull($stale);
        $this->assertNotNull($kept);

        $this->artisan('webx:blocks:bundles')->expectsOutputToContain('2 bundle(s) stored')->assertSuccessful();
        $this->artisan('webx:blocks:bundles', ['--prune' => true])->expectsOutputToContain('Pruned 1 bundle(s).')->assertSuccessful();

        $this->assertNull(BlockBundle::query()->find($stale->hash));
        $this->assertNotNull(BlockBundle::query()->find($kept->hash));

        Page::query()->create(['title' => 'Home', 'blocks' => [$this->node('hero'), $this->node('text')]]);
        Page::query()->create(['title' => 'About', 'blocks' => [$this->node('text')]]);
        Page::query()->create(['title' => 'Empty', 'blocks' => []]);

        $this->artisan('webx:blocks:bundles', ['--warm' => true])->expectsOutputToContain('Nothing to warm')->assertSuccessful();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);
        $this->artisan('webx:blocks:bundles', ['--warm' => true])->expectsOutputToContain('Warmed 2 bundle(s) for 3 entities.')->assertSuccessful();

        $this->assertSame(2, BlockBundle::query()->count());
        $this->assertStringContainsString('.hero{v:2}', (string) BlockBundle::query()->where('hash', '!=', $kept->hash)->value('css'));

        $this->app['config']->set('webx-blocks.entities', ['Not\\A\\Model']);
        $this->artisan('webx:blocks:bundles', ['--warm' => true])->assertFailed();
    }

    #[Test]
    public function clearing_forgets_the_types_and_drops_the_compiled_templates(): void
    {
        $compiler = $this->app->make(TemplateCompiler::class);
        $this->publish('hero', '<h1>one</h1>');
        $this->render([$this->node('hero')]);

        $this->assertNotEmpty(glob($compiler->directory().'/hero-1-*.php'));

        $this->artisan('webx:blocks:clear')->expectsOutputToContain('Block types forgotten.')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($compiler->directory());
    }

    /**
     * @param  array<array-key, mixed>  $blocks
     */
    private function bundleFor(array $blocks, bool $preview = false): ?BlockBundle
    {
        Blocks::flush();
        $this->render($blocks, preview: $preview);

        return $this->app->make(Bundles::class)->build(Blocks::usedTypes());
    }

    /** A layout with the directive in both places, and a page that extends it: the page's path. */
    private function views(): string
    {
        $directory = $this->app->make(TemplateCompiler::class)->directory().'/../blocks-views';
        File::ensureDirectoryExists($directory);

        File::put($directory.'/layout.blade.php', "<html><head>@webxBlocks('styles')</head><body>@yield('content')@webxBlocks('scripts')</body></html>");
        File::put($directory.'/page.blade.php', "@extends('layout')\n@section('content'){!! \WebxUi\Blocks\Facades\Blocks::render(\$blocks) !!}@endsection");

        $this->app['view']->addLocation($directory);
        $this->beforeApplicationDestroyed(static function () use ($directory): void {
            File::deleteDirectory($directory);
        });

        return $directory.'/page.blade.php';
    }
}
