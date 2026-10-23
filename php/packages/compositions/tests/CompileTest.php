<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Compositions\Compiler;
use WebxUi\Compositions\Exceptions\CompositionException;

/**
 * The compiled set (COMPOSITIONS §6): when it is built again, what a broken file does to a site
 * already serving, the file of the site's own over the composition, `place`, and the commands.
 */
final class CompileTest extends TestCase
{
    /** §6.2: a new file of a handle, a changed file — the next request compiles, as Blade does. */
    #[Test]
    public function a_changed_source_is_compiled_again_on_the_next_request(): void
    {
        $this->assertStringContainsString('<body class="site">', $this->page());

        $this->theme('layout', 'default', ['attrs' => ['body' => ['class' => 'first']]]);
        $this->assertStringContainsString('<body class="site first">', $this->page());

        $this->theme('layout', 'default', ['attrs' => ['body' => ['class' => 'second']]]);
        touch($this->local.'/compositions/layout/default.json', time() + 10);
        $this->assertStringContainsString('<body class="site second">', $this->page());
    }

    #[Test]
    public function without_the_check_a_request_keeps_the_compiled_set(): void
    {
        config()->set('webx-compositions.check', false);
        $this->page();

        $this->theme('layout', 'default', ['attrs' => ['body' => ['class' => 'later']]]);

        $this->assertStringContainsString('<body class="site">', $this->page());

        $this->artisan('webx:composition:compile')->assertSuccessful();
        $this->assertStringContainsString('<body class="site later">', $this->page());
    }

    /** §3.6: a finding is logged and the page renders; nothing on a request throws for a stale id. */
    #[Test]
    public function a_patch_naming_a_node_that_is_not_there_does_not_take_the_page_down(): void
    {
        $log = Log::spy();
        $this->theme('layout', 'default', ['remove' => ['seo.links'], 'attrs' => ['body' => ['class' => 'ok']]]);

        $this->assertStringContainsString('<body class="site ok">', $this->page());
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_contains($message, 'remove seo.links — no node with this id'))->once();
    }

    /** §3.6: a tree that cannot be built keeps the last set serving, and says why. */
    #[Test]
    public function a_broken_file_leaves_the_last_set_serving(): void
    {
        $this->theme('layout', 'default', ['attrs' => ['body' => ['class' => 'good']]]);
        $this->assertStringContainsString('<body class="site good">', $this->page());

        $log = Log::spy();
        $this->theme('layout', 'default', '{"attrs": ');
        touch($this->local.'/compositions/layout/default.json', time() + 10);

        $this->assertStringContainsString('<body class="site good">', $this->page());
        $log->shouldHaveReceived('error')->withArgs(static fn (string $message): bool => str_contains($message, 'invalid JSON'))->once();

        $this->artisan('webx:composition:compile')->assertFailed();
    }

    #[Test]
    public function without_a_set_to_fall_back_on_a_broken_file_is_an_exception(): void
    {
        $this->theme('layout', 'default', ['root' => ['id' => 'html', 'tag' => 'html']]);

        $this->expectException(CompositionException::class);
        $this->expectExceptionMessage('a second base of "layout"');

        $this->app->make(Compiler::class)->compile();
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function refused(): array
    {
        return [
            'unknown key' => [['attr' => []], 'unknown key "attr"'],
            'base in a handle' => [['extends' => 'layout'], 'only default.json can be a base'],
            'two kinds' => [['add' => [['id' => 'x', 'to' => 'body', 'html' => '<hr>', 'view' => 'v']]], 'a node is one kind'],
            'expression' => [['attrs' => ['body' => [':class' => '$x']]], 'values are literals, not expressions'],
            'protection in a patch' => [['add' => [['id' => 'x', 'to' => 'body', 'html' => '<hr>', 'protected' => true]]], 'only a base protects'],
            'bad id' => [['add' => [['id' => 'X_1', 'to' => 'body', 'html' => '<hr>']]], 'a node needs an id'],
            'two positions' => [['move' => [['id' => 'footer', 'first' => true, 'after' => 'main']]], 'one of before, after, first, last'],
        ];
    }

    /** @param array<string, mixed> $patch */
    #[Test]
    #[DataProvider('refused')]
    public function the_schema_refuses_what_cannot_be_read_as_meant(array $patch, string $message): void
    {
        $this->theme('layout', 'blog.article', $patch);

        $this->expectException(CompositionException::class);
        $this->expectExceptionMessage($message);

        $this->tree('blog.article');
    }

    /** §6.4: a file of the site's own is above the dispatcher in the lookup, and prints instead. */
    #[Test]
    public function a_layout_file_of_the_sites_own_wins_and_place_brings_the_pieces_of_its_handle(): void
    {
        $own = $this->local.'/views';
        @mkdir($own.'/components', 0777, true);
        file_put_contents($own.'/components/layout.blade.php', <<<'BLADE'
            <main class="own">{{ $slot }}<x-webx-composition::place id="main" only="added" /></main>
            <x-webx-composition::place id="body" only="added" />
            BLADE);
        config()->set('view.paths', [$own, ...config('view.paths')]);
        $this->app['view']->getFinder()->setPaths(config('view.paths'));
        $this->theme('layout', 'blog.article', ['add' => [['id' => 'promo', 'to' => 'body', 'view' => 'components.promo']]]);

        $html = $this->page('blog.article');

        // The handle comes with the layout's attributes, as Blade hands them to a component inside.
        $this->assertSame("<main class=\"own\"><p>Body</p><p class=\"b-article\">For articles</p>\n<aside class=\"note\">a</aside>\n<aside class=\"note\">b</aside>\n</main>\n<div class=\"promo\">Promo</div>\n", $html);
    }

    #[Test]
    public function place_with_all_prints_every_child_but_the_slots(): void
    {
        $html = Blade::render('<x-webx-composition::place id="main" />');

        $this->assertSame("<aside class=\"note\">a</aside>\n<aside class=\"note\">b</aside>\n", $html);
    }

    #[Test]
    public function the_command_compiles_every_variant_and_says_what_it_found(): void
    {
        $this->theme('layout', 'blog.article', ['remove' => ['nothing']]);

        $this->artisan('webx:composition:compile')
            ->expectsOutputToContain('Compiled 4 views of 2 compositions.')
            ->expectsOutputToContain('remove nothing — no node with this id')
            ->assertSuccessful();

        $path = $this->app->make(Compiler::class)->path();
        $this->assertFileExists($path.'/manifest.php');

        $this->artisan('webx:composition:clear')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($path);
    }
}
