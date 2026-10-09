<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Rendering\TemplateCompiler;

/**
 * The block type this module offers to `module-blocks` (layout regions §5.2): installed once,
 * the site's menus as its choices, and a menu printed the way `<x-webx-menu::menu>` prints one —
 * links with their attributes, the visitor's place marked, headings as text.
 */
final class BlockTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        // Blocks are optional for this package, so only this file brings them in.
        return [...parent::getPackageProviders($app), BlocksServiceProvider::class];
    }

    protected function tearDown(): void
    {
        // One compiled file per version, and every test starts its versions at 1.
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }

    #[Test]
    public function the_offered_type_is_installed_with_the_menus_of_the_site_to_choose_from(): void
    {
        // Made in the panel before the install — one the config does not know about.
        $this->menu('blog-side')->update(['title' => ['en' => 'Blog sidebar']]);

        $block = $this->installBlock()->load('publishedVersion');

        $this->assertSame('Menu', $block->title);
        $this->assertSame('Offered by menu', $block->publishedVersion?->comment);
        $this->assertNull($block->allowed_in, 'usable anywhere, a region of the layout included');

        $schema = (array) $block->publishedVersion->schema;
        $this->assertSame(['menu', 'layout'], array_column($schema, 'id'));
        $this->assertSame(
            [
                ['value' => 'header', 'label' => 'Header'],
                ['value' => 'footer', 'label' => 'Footer'],
                ['value' => 'blog-side', 'label' => 'Blog sidebar'],
            ],
            $schema[0]['props']['options'] ?? null,
            'the declared menus first, then the ones made in the panel',
        );
    }

    /**
     * A theme's prose spaces `li + li`, which in a grid or a row lifts the first cell above the
     * others: the cells of the type's lists keep no margin of their own.
     */
    #[Test]
    public function the_cells_of_its_rows_keep_no_margin(): void
    {
        $styles = (string) $this->installBlock()->load('publishedVersion')->publishedVersion?->styles;

        foreach (['.b-menu__item'] as $cell) {
            $this->assertMatchesRegularExpression('~^'.preg_quote($cell, '~').'(,\n[^{]*)? \{[^}]*\n    margin: 0[ ;]~m', $styles, $cell);
        }
    }

    #[Test]
    public function a_type_the_site_already_has_by_that_slug_is_never_touched(): void
    {
        $own = Block::query()->create(['slug' => 'menu', 'title' => 'Our menu']);
        $own->saveVersion(['template' => '<nav data-wx-block="menu">Ours</nav>']);
        $own->publish();

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['menu']])
            ->expectsOutputToContain('left alone')
            ->assertSuccessful();

        $this->assertSame('Our menu', $own->refresh()->title);
    }

    #[Test]
    public function it_prints_the_menu_with_its_links_its_headings_and_where_the_visitor_is(): void
    {
        $this->installBlock();

        $footer = $this->menu('footer');
        $this->item(['title' => ['en' => 'Blog'], 'target' => 'url', 'url' => '/blog'], $footer);
        $this->item(['title' => ['en' => 'Partner'], 'target' => 'url', 'url' => 'https://example.org/x', 'new_tab' => true], $footer);
        $legal = $this->item(['title' => ['en' => 'Legal'], 'target' => 'none', 'is_heading' => true], $footer);
        $this->item(['title' => ['en' => 'Privacy'], 'target' => 'url', 'url' => '/privacy'], $footer, $legal);

        $this->standingOn('/privacy');

        $html = $this->render(['menu' => 'footer', 'layout' => 'columns']);

        $this->assertStringContainsString('<nav class="b-menu b-menu--columns" data-wx-block="menu">', $html);
        $this->assertMatchesRegularExpression('~<a class="b-menu__link"\s+href="'.preg_quote(url('/blog'), '~').'"\s*>Blog</a>~', $html);
        $this->assertMatchesRegularExpression('~href="https://example.org/x"\s+target="_blank"~', $html);

        // A heading is text, and it is marked active because the page under it is current.
        $this->assertStringContainsString('<span class="b-menu__label">Legal</span>', $html);
        $this->assertMatchesRegularExpression('~b-menu__item--parent\s+is-active\s*">~', $html);
        $this->assertMatchesRegularExpression('~href="'.preg_quote(url('/privacy'), '~').'"\s+aria-current="page"\s*>Privacy</a>~', $html);
        $this->assertSame(1, substr_count($html, 'aria-current='), 'only the path to the visitor is marked');
    }

    #[Test]
    public function what_the_editor_never_touched_is_the_header_in_one_line_and_an_empty_menu_is_nothing(): void
    {
        $this->installBlock();

        $this->assertSame('', trim($this->render()), 'no items, no empty <nav>');

        $this->item(['title' => ['en' => 'Blog'], 'target' => 'url', 'url' => '/blog'], $this->menu('header'));

        $this->assertStringContainsString('<nav class="b-menu b-menu--line" data-wx-block="menu">', $this->render());
    }

    /** The block type this module offers, installed the way a site installs it. */
    private function installBlock(): Block
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['menu']])->assertSuccessful();

        return Block::query()->where('slug', 'menu')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function render(array $values = []): string
    {
        return (string) $this->app->make(Renderer::class)->render([
            ['key' => 'k1', 'type' => 'menu', 'values' => $values],
        ]);
    }
}
