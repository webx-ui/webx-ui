<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Preview\PreviewGrant;
use WebxUi\Settings\Settings;
use WebxUi\Snippets\Models\Snippet;
use WebxUi\Snippets\Output\Codes;
use WebxUi\Snippets\Panel\SnippetOrder;
use WebxUi\Widgets\Consent;
use WebxUi\Widgets\Widgets;

/**
 * The site's half (§5–§6): the places of theme-default's layout and of the config, the order,
 * the paths a code covers, the languages, the environment, consent, and where a code is never
 * printed.
 */
final class OutputTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/', static fn (): string => Blade::render('<x-layout><p>Page</p></x-layout>'));
        $router->get('/custom', static fn (): string => Blade::render((string) file_get_contents(self::fixture('site/custom.blade.php'))));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('webx:theme:sync')->assertSuccessful();
    }

    #[Test]
    public function the_three_places_of_the_layout_print_where_the_spec_puts_them(): void
    {
        $this->snippet('head', '<meta name="site-check" content="head">');
        $this->snippet('body_start', '<noscript>body start</noscript>');
        $this->snippet('body_end', '<script>window.chat = 1</script>');

        $html = (string) $this->get('/')->assertOk()->getContent();

        // Right after charset and viewport, above the theme's stylesheets.
        // Consent Mode's default goes first, on the place's own marker (decision 9).
        $this->assertMatchesRegularExpression('~<meta name="viewport" content="width=device-width, initial-scale=1">\s*<script>window\.dataLayer=[^<]*"consent","default"[^<]*</script>\s*<meta name="site-check" content="head">~', $html);
        $this->assertLessThan(strpos($html, '<style data-webx-theme>'), strpos($html, 'site-check'));

        // Right after <body>, before "Skip to content".
        $this->assertMatchesRegularExpression('~<body class="site">\s*<noscript>body start</noscript>\s*<a class="site-skip"~', $html);

        // The layout's last line: after the footer and the page's scripts. The widgets' banner
        // and scripts are added before </body> once the page is finished, so they come after it.
        $this->assertLessThan(strpos($html, 'data-webx-consent-root'), strpos($html, 'window.chat'));
        $this->assertGreaterThan(strpos($html, 'site-footer'), strpos($html, 'window.chat'));
        $this->assertStringNotContainsString(Widgets::EARLY, $html);
    }

    #[Test]
    public function a_place_of_the_config_prints_with_the_same_component(): void
    {
        config()->set('webx-snippets.places', ['before-footer' => 'Before the footer']);
        $this->snippet('before-footer', '<div class="promo">Promo</div>');

        $this->assertMatchesRegularExpression('~<p>Page</p>\s*<div class="promo">Promo</div>~', (string) $this->get('/custom')->assertOk()->getContent());
    }

    #[Test]
    public function codes_go_by_position_then_id_and_a_switched_off_one_is_not_printed(): void
    {
        $this->snippet('body_end', '<i>second</i>', ['position' => 2]);
        $this->snippet('body_end', '<i>first</i>', ['position' => 1]);
        $this->snippet('body_end', '<i>third</i>', ['position' => 2]);
        $this->snippet('body_end', '<i>off</i>', ['enabled' => false]);

        $this->at('/');

        $this->assertSame("<i>first</i>\n<i>second</i>\n<i>third</i>", Blade::render('<x-webx-snippets place="body_end" />'));
    }

    #[Test]
    public function an_empty_place_is_an_empty_string_without_markers(): void
    {
        $this->at('/');

        $this->assertSame('', Blade::render('<x-webx-snippets place="head" />'));
        $this->assertSame('', Blade::render('<x-webx-snippets place="body_end" />'));
    }

    #[Test]
    public function only_on_and_except_are_masks_of_the_path_without_its_query(): void
    {
        $this->snippet('body_end', '<i>blog</i>', ['only_paths' => ['/blog/*', '/']]);
        $this->snippet('body_end', '<i>not drafts</i>', ['except_paths' => ['/blog/drafts/*']]);

        $this->at('/blog/news?utm=1');
        $this->assertSame("<i>blog</i>\n<i>not drafts</i>", Blade::render('<x-webx-snippets place="body_end" />'));

        $this->at('/');
        $this->assertSame("<i>blog</i>\n<i>not drafts</i>", Blade::render('<x-webx-snippets place="body_end" />'));

        $this->at('/blog/drafts/one');
        $this->assertSame('<i>blog</i>', Blade::render('<x-webx-snippets place="body_end" />'));

        $this->at('/contacts');
        $this->assertSame('<i>not drafts</i>', Blade::render('<x-webx-snippets place="body_end" />'));
    }

    #[Test]
    public function the_language_prefix_is_not_part_of_the_path_a_mask_sees(): void
    {
        $this->snippet('body_end', '<i>blog</i>', ['only_paths' => ['/blog/*']]);
        $this->snippet('body_end', '<i>home</i>', ['only_paths' => ['/']]);

        $this->at('/ru/blog/news', 'ru');
        $this->assertSame('<i>blog</i>', Blade::render('<x-webx-snippets place="body_end" />'));

        $this->at('/ru', 'ru');
        $this->assertSame('<i>home</i>', Blade::render('<x-webx-snippets place="body_end" />'));
    }

    #[Test]
    public function a_code_with_languages_is_printed_only_in_them(): void
    {
        $this->snippet('body_end', '<i>ru only</i>', ['locales' => ['ru']]);
        $this->snippet('body_end', '<i>all</i>');

        $this->at('/');
        $this->assertSame('<i>all</i>', Blade::render('<x-webx-snippets place="body_end" />'));

        $this->at('/ru/', 'ru');
        $this->assertSame("<i>ru only</i>\n<i>all</i>", Blade::render('<x-webx-snippets place="body_end" />'));
    }

    #[Test]
    public function outside_production_only_codes_marked_for_dev_and_stage_are_printed(): void
    {
        $this->snippet('body_end', '<i>counter</i>', ['on_stage' => false]);
        $this->snippet('body_end', '<i>check</i>');

        $this->at('/');
        $this->assertSame('<i>check</i>', Blade::render('<x-webx-snippets place="body_end" />'));

        // `testing` named as an environment that prints everything, the way `production` is.
        config()->set('webx-snippets.environments', ['production', 'testing']);
        $this->assertSame("<i>counter</i>\n<i>check</i>", Blade::render('<x-webx-snippets place="body_end" />'));
    }

    #[Test]
    public function a_code_that_waits_for_consent_is_inert_until_its_category_is_agreed_to(): void
    {
        $this->snippet('body_end', '<script src="https://counter.example/c.js"></script>', ['consent' => 'statistics']);
        $this->snippet('body_end', '<i>necessary</i>');

        $html = (string) $this->get('/')->getContent();
        $this->assertStringContainsString('<template data-webx-consent="statistics"><script src="https://counter.example/c.js"></script></template>', $html);
        $this->assertStringContainsString('<i>necessary</i>', $html);
        // The banner offers the category, because the page asks for it.
        $this->assertStringContainsString('data-webx-consent-row="statistics">', $html);

        $this->withUnencryptedCookie(Consent::COOKIE, json_encode(['v' => '1', 'c' => ['statistics']], JSON_THROW_ON_ERROR));
        $html = (string) $this->get('/')->getContent();
        $this->assertStringContainsString('<script src="https://counter.example/c.js"></script>', $html);
        $this->assertStringNotContainsString('<template data-webx-consent="statistics">', $html);
    }

    #[Test]
    public function with_the_banner_off_every_code_is_printed_as_it_is(): void
    {
        $this->app->make(Settings::class)->save(['consent.enabled' => false]);
        $this->snippet('body_end', '<i>pixel</i>', ['consent' => 'marketing']);

        $this->at('/');

        $this->assertSame('<i>pixel</i>', Blade::render('<x-webx-snippets place="body_end" />'));
    }

    #[Test]
    public function consent_modes_default_comes_before_a_tag_of_the_head_place(): void
    {
        $this->snippet('head', '<script>window.dataLayer.push({tag: 1})</script>', ['consent' => 'necessary']);

        $html = (string) $this->get('/')->getContent();

        $this->assertSame(1, substr_count($html, '"consent","default"'));
        $this->assertLessThan(strpos($html, 'window.dataLayer.push({tag: 1})'), strpos($html, '"consent","default"'));
        $this->assertStringNotContainsString(Widgets::EARLY, $html);
    }

    #[Test]
    public function the_panel_its_api_and_the_block_preview_never_get_a_code(): void
    {
        $this->snippet('head', '<i>tag</i>');

        foreach (['/cms', '/cms/pages/1', '/cms/not-a-page', '/api/cms/pages', '/_preview/page/1', '/_preview/region/footer', '/_preview/block-stage'] as $path) {
            $this->at($path);
            $this->assertSame('', Blade::render('<x-webx-snippets place="head" />'), $path);
        }

        // A preview mounted under another path still carries its grant.
        $this->at('/somewhere');
        $this->app->make('request')->attributes->set(PreviewGrant::ATTRIBUTE, new PreviewGrant('page', '1', null, time() + 60));
        $this->assertSame('', Blade::render('<x-webx-snippets place="head" />'));

        // A page whose path only starts with the same letters is a page.
        $this->at('/cmsx');
        $this->assertStringContainsString('<i>tag</i>', Blade::render('<x-webx-snippets place="head" />'));
    }

    #[Test]
    public function a_place_nobody_declares_prints_nothing_and_keeps_its_codes(): void
    {
        // Saved while `promo` was declared; the config has lost it since.
        DB::table('snippets_codes')->insert([
            'name' => 'Promo', 'place' => 'promo', 'code' => '<i>promo</i>', 'consent' => 'necessary',
            'on_stage' => true, 'enabled' => true, 'position' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->at('/');

        $this->assertSame('', Blade::render('<x-webx-snippets place="promo" />'));
        $this->assertSame(1, Snippet::query()->where('place', 'promo')->count());

        config()->set('webx-snippets.places', ['promo' => 'Promo']);
        $this->assertSame('<i>promo</i>', Blade::render('<x-webx-snippets place="promo" />'));
    }

    #[Test]
    public function saving_switching_off_reordering_and_deleting_forget_the_cached_list(): void
    {
        $one = $this->snippet('body_end', '<i>one</i>');
        $two = $this->snippet('body_end', '<i>two</i>');
        $this->at('/');
        $render = static fn (): string => Blade::render('<x-webx-snippets place="body_end" />');

        $this->assertSame("<i>one</i>\n<i>two</i>", $render());
        $this->assertTrue(cache()->has(Codes::KEY));

        $one->update(['code' => '<i>one, edited</i>']);
        $this->assertSame("<i>one, edited</i>\n<i>two</i>", $render());

        $this->app->make(SnippetOrder::class)->reorder('body_end', [$two->id, $one->id]);
        $this->assertSame("<i>two</i>\n<i>one, edited</i>", $render());

        $two->update(['enabled' => false]);
        $this->assertSame('<i>one, edited</i>', $render());

        $one->delete();
        $this->assertSame('', $render());

        $one->restore();
        $this->assertSame('<i>one, edited</i>', $render());
    }

    #[Test]
    public function the_places_a_page_printed_are_marked_seen(): void
    {
        $this->get('/')->assertOk();

        $this->assertSame(['head', 'body_start', 'body_end'], array_keys((array) cache()->get('webx-snippets.seen')));
    }
}
