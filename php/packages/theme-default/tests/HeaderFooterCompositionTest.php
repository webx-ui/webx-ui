<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\Region;
use WebxUi\Compositions\Builder;
use WebxUi\Compositions\Compiler;
use WebxUi\Compositions\Compositions;
use WebxUi\Compositions\Node;
use WebxUi\Compositions\Testing\ComparesHtml;
use WebxUi\Compositions\Testing\IsolatedCompositions;
use WebxUi\Localization\Locales;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Menu\MenuServiceProvider;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\PagesServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Seo\SeoServiceProvider;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\View\Components\HeaderNav;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The header and the footer as compositions (COMPOSITIONS §10.3), on the modules they read for
 * real: the menus, the pages, the Contacts tab and two languages. Without a patch each prints
 * what its view printed before it was a tree — `Fixtures/before/` holds those views as they
 * were —, up to the whitespace between tags; and every node a theme was copying the header to
 * change is a patch now.
 *
 * The patches are the site config's (`webx-compositions.patches`): the same JSON a theme writes
 * in theme/compositions/header/default.json, one layer higher.
 */
final class HeaderFooterCompositionTest extends TestCase
{
    use ComparesHtml;
    use IsolatedCompositions;
    use RefreshDatabase;

    private const string NAME = 'webx-ui/theme-default';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        // The engine of themes first: it wraps the translation loader.
        return [
            ThemeServiceProvider::class,
            LocalizationServiceProvider::class,
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            BlocksServiceProvider::class,
            SettingsServiceProvider::class,
            SeoServiceProvider::class,
            PagesServiceProvider::class,
            MenuServiceProvider::class,
            WidgetsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.name', 'Harbor Logistics');
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-themes.theme', self::NAME);
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-seo.cache.enabled', false);
        $app['config']->set('webx-menu.menus', [
            'header' => ['title' => 'Header', 'variants' => ['link', 'button']],
            'footer' => ['title' => 'Footer'],
        ]);
        $this->isolateCompositions($app);

        $app->resolving(ThemeLocator::class, static function (ThemeLocator $locator): void {
            $locator->register(self::NAME, dirname(__DIR__));
        });
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    /**
     * One address for both, so that a link to the same page in another language is the same:
     * `?before` prints the view as it was, without it the composition.
     *
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/shell/{part}', static fn (string $part): string => view(request()->has('before') ? "before::{$part}" : "components.{$part}")->render());
    }

    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('before', str_replace('\\', '/', __DIR__).'/Fixtures/before');
    }

    /** @return array<string, array{string}> */
    public static function states(): array
    {
        return [
            'nothing filled in' => ['empty'],
            'the pages below the home page' => ['pages'],
            'menus, contacts and two languages' => ['full'],
        ];
    }

    #[Test]
    #[DataProvider('states')]
    public function without_a_patch_the_header_and_the_footer_print_what_their_views_printed(string $state): void
    {
        $this->fill($state);

        foreach (['header', 'footer'] as $part) {
            $before = $this->part($part, true);
            $after = $this->part($part);

            $this->assertStringContainsString("site-{$part}", $after);
            $this->assertSameHtml($before, $after, "The {$part} differs from its view ({$state}).");
        }

        if ($state === 'full') {
            // Every leaf had something to print: the comparison covered them all.
            $header = $this->part('header');

            foreach (['site-header__topbar', 'site-header__title', 'webx-header-nav', 'site-header__phones', 'site-header__cta', 'site-header__languages'] as $class) {
                $this->assertStringContainsString($class, $header);
            }

            foreach (['site-footer__contacts', 'site-footer__address', 'Delivery', 'site-footer__copy'] as $piece) {
                $this->assertStringContainsString($piece, $this->part('footer'));
            }
        }
    }

    /**
     * The regions stand around the compositions (COMPOSITIONS §10.3): the layout's node `header`
     * is the region, and the composition is its fallback — what shows until an editor publishes
     * blocks there, and again after they are taken away.
     */
    #[Test]
    public function the_regions_print_their_blocks_and_the_compositions_until_there_are_some(): void
    {
        config()->set('webx-blocks.regions', ['header' => ['title' => 'Header'], 'footer' => ['title' => 'Footer']]);
        $page = static fn (): string => Blade::render('<x-layout><p>Body</p></x-layout>');

        $this->assertStringContainsString('<a class="site-header__title"', $page());
        $this->assertStringContainsString('<p class="site-footer__copy">', $page());

        $block = Block::query()->create(['slug' => 'bar', 'title' => 'Bar']);
        $block->saveVersion(['template' => '<div class="b-bar">{{ $text }}</div>', 'schema' => [['id' => 'text', 'type' => 'wx-input']]]);
        $block->publish();

        foreach (['header' => 'Our own header', 'footer' => 'Our own footer'] as $name => $text) {
            $region = Region::query()->firstOrNew(['name' => $name]);
            $region->saveDraft(['blocks' => [['key' => $name, 'type' => 'bar', 'values' => ['text' => $text]]]]);
            $region->publish();
        }

        $html = $page();

        $this->assertStringContainsString('<div class="b-bar">Our own header</div>', $html);
        $this->assertStringContainsString('<div class="b-bar">Our own footer</div>', $html);
        $this->assertStringNotContainsString('site-header', $html);
        $this->assertStringNotContainsString('site-footer', $html);
    }

    #[Test]
    public function the_trees_and_their_contract_are_the_ones_the_guide_names(): void
    {
        $builder = $this->app->make(Compiler::class);

        $this->assertSame(
            ['header', 'header>header.topbar', 'header.topbar>header.contacts', 'header>header.brand', 'header.brand>header.logo', 'header>header.nav', 'header>header.actions', 'header.actions>header.phones', 'header.actions>header.cta', 'header.actions>header.languages'],
            $this->ids('header'),
        );
        $this->assertSame(
            ['footer', 'footer>footer.inner', 'footer.inner>footer.contacts', 'footer.inner>footer.nav', 'footer.nav>footer.menu', 'footer.nav>footer.consent', 'footer.inner>footer.copy'],
            $this->ids('footer'),
        );
        $this->assertSame([], $builder->tree('header')->findings);
        $this->assertSame([], $builder->tree('footer')->findings);

        $contract = $this->app->make(Builder::class)->contract('header');
        $this->assertSame(['nav', 'calls', 'contacts', 'hours', 'email', 'phones', 'languages'], $contract['vars']);
        $this->assertSame('components.header', $contract['dispatcher']);
        $this->assertSame(['contacts', 'address', 'items'], $this->app->make(Builder::class)->contract('footer')['vars']);
    }

    /** The announcement bar leaves the flow above the header for its line above the bar. */
    #[Test]
    public function a_patch_puts_the_announcement_bar_into_the_header(): void
    {
        $this->fill('full');
        $this->patchComposition('layout', ['remove' => ['notice-bar']]);
        $this->patchComposition('header', ['add' => [[
            'id' => 'notice-bar',
            'to' => 'header.topbar',
            'first' => true,
            'component' => 'webx-notice-bar',
            'children' => [['id' => 'notice.stack', 'stack' => 'notice']],
        ]]]);

        $html = Blade::render('@push(\'notice\')<p class="the-notice">Closed on Monday</p>@endpush<x-layout><p>Body</p></x-layout>');

        $this->assertSame(1, substr_count($html, 'the-notice'));
        $this->assertMatchesRegularExpression('~<div class="webx-header__topbar"><section class="webx-notice-bar"[^>]*>.*the-notice.*</section>\s*<div class="site-header__topbar">~s', $html);
        $this->assertSame([], $this->findings());
    }

    #[Test]
    public function a_patch_takes_the_logo_the_phones_and_the_buttons_away(): void
    {
        $this->fill('full');
        $this->patchComposition('header', ['remove' => ['header.logo', 'header.phones', 'header.cta']]);

        $html = $this->part('header');

        $this->assertStringNotContainsString('webx-header__brand', $html, 'an empty brand slot is no brand');
        $this->assertStringNotContainsString('site-header__phones', $html);
        $this->assertStringNotContainsString('site-header__cta', $html);
        $this->assertStringContainsString('site-header__languages', $html);
        $this->assertStringContainsString('webx-header-nav', $html);
    }

    #[Test]
    public function a_patch_replaces_the_logo_the_phones_and_the_button_with_views_of_the_theme(): void
    {
        $this->fill('full');
        View::addNamespace('custom', str_replace('\\', '/', __DIR__).'/Fixtures/custom');
        $this->patchComposition('header', [
            'replace' => [
                'header.logo' => ['view' => 'custom::logo'],
                'header.phones' => ['component' => 'webx-phones', 'props' => ['layout' => 'list']],
                'header.cta' => ['view' => 'custom::cta', 'with' => ['label' => 'Get a quote']],
            ],
        ]);

        $html = $this->part('header');

        $this->assertMatchesRegularExpression('~<div class="webx-header__brand">\s*<a class="custom-logo" href="http://localhost">\s*<img src="/logo.svg" alt="Harbor Logistics">~', $html);
        $this->assertStringNotContainsString('site-header__title', $html);
        $this->assertStringContainsString('webx-phones--list', $html);
        // The view of the theme reads what the leaves get: the menu's calls, with its own word.
        $this->assertStringContainsString('<a class="custom-cta" href="http://localhost/quote">Get a quote</a>', $html);
        $this->assertSame([], $this->findings());
    }

    #[Test]
    public function a_patch_adds_a_node_of_the_theme_and_moves_the_language_switcher(): void
    {
        $this->fill('full');
        View::addNamespace('custom', str_replace('\\', '/', __DIR__).'/Fixtures/custom');
        $this->patchComposition('header', [
            'add' => [['id' => 'custom.search', 'to' => 'header.actions', 'first' => true, 'view' => 'custom::search']],
            'move' => [['id' => 'header.languages', 'to' => 'header.topbar', 'first' => true]],
        ]);

        $html = $this->part('header');

        $this->assertMatchesRegularExpression('~<div class="webx-header__topbar">\s*<[^>]+site-header__languages.*<div class="site-header__topbar">~s', $html);
        $this->assertMatchesRegularExpression('~<div class="webx-header__actions">\s*<form class="custom-search"~', $html);
        $this->assertSame([], $this->findings());
    }

    /** The burger and the drawer are the widget's; its slots and props reach them. */
    #[Test]
    public function a_patch_reaches_the_mobile_menu_through_the_slots_and_props_of_the_widget(): void
    {
        $this->fill('full');
        $this->patchComposition('header', [
            'add' => [['id' => 'custom.drawer', 'to' => 'header', 'tag' => 'x-slot:mobile-bottom', 'children' => [['id' => 'custom.drawer.note', 'html' => '<p class="custom-note">Open 24/7</p>']]]],
            'props' => ['header' => ['side' => 'left']],
        ]);

        $html = $this->part('header');

        $this->assertMatchesRegularExpression('~webx-mobile-menu.*<p class="custom-note">Open 24/7</p>~s', $html);
        $this->assertSame([], $this->findings());

        $this->patchComposition('header', ['props' => ['header' => ['collapse' => 'never']]]);

        $this->assertStringNotContainsString('webx-mobile-menu', $this->part('header'));
    }

    #[Test]
    public function a_patch_takes_a_column_of_the_footer_away_and_adds_one(): void
    {
        $this->fill('full');
        View::addNamespace('custom', str_replace('\\', '/', __DIR__).'/Fixtures/custom');
        $this->patchComposition('footer', [
            'add' => [['id' => 'custom.hours', 'to' => 'footer.inner', 'after' => 'footer.nav', 'view' => 'custom::column']],
            'remove' => ['footer.contacts'],
        ]);

        $html = $this->part('footer');

        $this->assertStringNotContainsString('site-footer__contacts', $html);
        $this->assertMatchesRegularExpression('~</nav>\s*<div class="custom-column">We work every day</div>\s*<p class="site-footer__copy">~', $html);
        $this->assertSame([], $this->findings());
    }

    private function part(string $part, bool $before = false): string
    {
        $this->app->forgetScopedInstances();
        // The panels of the navigation are numbered by the process: both renders start at one.
        (new ReflectionProperty(HeaderNav::class, 'count'))->setValue(null, 0);

        return (string) $this->get("/shell/{$part}".($before ? '?before=1' : ''))->assertOk()->getContent();
    }

    /** @param array<string, mixed> $patch */
    private function patchComposition(string $composition, array $patch): void
    {
        $patches = (array) config('webx-compositions.patches', []);
        $patches[$composition]['default'] = $patch;
        config()->set('webx-compositions.patches', $patches);
        $this->app->forgetScopedInstances();
    }

    /** @return list<string> */
    private function findings(): array
    {
        return array_map(strval(...), $this->app->make(Compositions::class)->findings());
    }

    /** @return list<string> */
    private function ids(string $composition): array
    {
        $ids = [];

        foreach ($this->app->make(Compiler::class)->tree($composition)->root->walk() as $node) {
            /** @var Node $node */
            $ids[] = ($node->parent === null ? '' : $node->parent->id.'>').$node->id;
        }

        return $ids;
    }

    private function fill(string $state): void
    {
        if ($state === 'pages') {
            foreach (['services' => true, 'about' => true, 'draft' => false] as $slug => $published) {
                $page = new Page(['title' => ucfirst($slug), 'slug' => $slug]);
                $page->appendTo(Page::home());

                if ($published) {
                    $page->publish();
                }
            }
        }

        if ($state !== 'full') {
            return;
        }

        config()->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'de']]);
        $this->app->make(Locales::class)->forget();

        $header = Menu::query()->firstOrCreate(['key' => 'header'], ['title' => ['en' => 'Header']]);
        $services = $this->item($header, ['title' => ['en' => 'Services'], 'target' => 'url', 'url' => '/services']);
        $this->item($header, ['title' => ['en' => 'Freight'], 'target' => 'url', 'url' => '/services/freight'], $services);
        $this->item($header, ['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about']);
        $this->item($header, ['title' => ['en' => 'Get a quote'], 'target' => 'url', 'url' => '/quote', 'variant' => 'button']);

        $footer = Menu::query()->firstOrCreate(['key' => 'footer'], ['title' => ['en' => 'Footer']]);
        $this->item($footer, ['title' => ['en' => 'Delivery'], 'target' => 'url', 'url' => '/delivery']);
        $this->item($footer, ['title' => ['en' => 'Privacy'], 'target' => 'url', 'url' => '/privacy', 'new_tab' => true]);

        $this->app->make(Settings::class)->save([
            'contacts.phones' => [['number' => '+800 1234 5678', 'label' => ['en' => 'Sales'], 'primary' => true, 'messengers' => []]],
            'contacts.emails' => [['email' => 'hello@example.com']],
            'contacts.hours' => [['days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'opens' => '09:00', 'closes' => '19:00']],
            'contacts.timezone' => 'UTC',
            'contacts.addresses' => [['address' => ['en' => '1 Example Street, Sample City'], 'latitude' => null, 'longitude' => null, 'map' => null, 'primary' => true]],
            'contacts.socials' => [['network' => 'instagram', 'url' => 'https://www.instagram.com/example']],
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function item(Menu $menu, array $attributes, ?MenuItem $parent = null): MenuItem
    {
        $item = new MenuItem(['menu_id' => $menu->getKey(), ...$attributes]);

        $parent instanceof MenuItem ? $item->appendTo($parent) : $item->saveAsRoot();

        return $item->refresh();
    }
}
