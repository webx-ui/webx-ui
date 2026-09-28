<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Regions;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Tests\Fixtures\RegionPage;

/**
 * The tag and what it prints (§4, §5 of the regions spec): the fallback whenever the region has
 * nothing to show, the blocks with their own bundle when it has, the fallback again when one of
 * them throws — and no HTML cached anywhere on the way.
 */
final class RegionsTest extends RegionTestCase
{
    #[Test]
    public function the_class_tag_and_the_anonymous_standalone_both_resolve_in_the_namespace(): void
    {
        $this->assertStringContainsString('Header from code', $this->tag());

        // The compiler looks for a class first, then a view: the bare document is still a view.
        $html = Blade::render('<x-webx-blocks::standalone><p>inside</p></x-webx-blocks::standalone>');

        $this->assertStringContainsString('<!doctype html>', $html);
        $this->assertStringContainsString('<p>inside</p>', $html);
    }

    #[Test]
    public function a_region_nobody_declared_prints_its_fallback_and_says_so_in_the_log(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(static fn (string $message): bool => str_contains($message, '[heder]'));

        $this->assertStringContainsString('Header from code', $this->tag('name="heder" fallback="region-site::header"'));
    }

    #[Test]
    public function the_fallback_stands_until_there_is_something_published_to_show(): void
    {
        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>', [], ['styles' => '.b-bar { color: red; }']);

        // Declared, never saved.
        $this->assertStringContainsString('Header from code', $this->tag());

        // Saved, not published.
        $this->region('header', [$this->node('bar', ['text' => 'Draft bar'])], published: false);
        $this->assertStringContainsString('Header from code', $this->tag());

        // Published, but every block switched off.
        $this->region('header', [['key' => 'h1', 'type' => 'bar', 'hidden' => true, 'values' => ['text' => 'Off']]]);
        $this->assertStringContainsString('Header from code', $this->tag());

        // Published and visible: the blocks, with a stylesheet of the region's own before them.
        $this->region('header', [$this->node('bar', ['text' => 'Live bar'])]);
        $html = $this->tag();

        $this->assertStringNotContainsString('Header from code', $html);
        $this->assertStringContainsString('<div class="b-bar">Live bar</div>', $html);
        $this->assertMatchesRegularExpression('#^<link rel="stylesheet" href="[^"]+/blocks/[a-f0-9]{16}\.css"><div class="b-bar">#', $html);

        // No markers anywhere on the live site.
        $this->assertStringNotContainsString('<!--', $html);
    }

    #[Test]
    public function an_empty_region_without_a_fallback_prints_nothing(): void
    {
        $this->assertSame('', trim($this->tag('name="footer"')));
    }

    #[Test]
    public function the_attributes_of_the_tag_reach_the_fallback_and_the_blocks(): void
    {
        $this->assertStringContainsString('data-tone="dark" data-attribute="dark"', $this->tag('name="header" fallback="region-site::header" tone="dark"'));

        $this->publish('mark', '<i>{{ $region?->name }}:{{ $region?->data(\'tone\') }}:{{ $region?->data(\'size\', \'m\') }}</i>');
        $this->region('header', [$this->node('mark')]);

        $this->assertStringContainsString('<i>header:dark:m</i>', $this->tag('name="header" fallback="region-site::header" tone="dark"'));
    }

    #[Test]
    public function one_failing_block_makes_the_whole_region_print_its_fallback_on_the_site(): void
    {
        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>');
        $this->publish('bomb', '<p>@if ($boom) {{ throw new RuntimeException(\'Boom\') }} @endif fine</p>');

        $this->region('header', [$this->node('bar', ['text' => 'Menu']), $this->node('bomb', ['boom' => true])]);
        $this->region('footer', [$this->node('bar', ['text' => 'Menu']), $this->node('bomb', ['boom' => true])]);

        Log::shouldReceive('warning')->twice()->withArgs(static fn (string $message): bool => str_contains($message, 'failed; the whole region prints its fallback'));
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $html = $this->tag();

        // Not the menu with a hole beside it: the header from code, whole.
        $this->assertStringContainsString('Header from code', $html);
        $this->assertStringNotContainsString('Menu', $html);

        // Without a fallback the region falls into nothing.
        $this->assertSame('', trim($this->tag('name="footer"')));
    }

    #[Test]
    public function a_region_leaves_the_bundle_of_the_page_as_it_found_it(): void
    {
        $this->publish('page-text', '<p class="b-page-text">{{ $text }}</p>', [], ['styles' => '.b-page-text { margin: 0; }']);
        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>', [], ['styles' => '.b-bar { color: red; }']);
        $this->region('header', [$this->node('bar', ['text' => 'Menu'])]);

        $renderer = $this->app->make(Renderer::class);
        $renderer->render([$this->node('page-text', ['text' => 'Body'])]);

        $this->tag();

        // The page's `@webxBlocks` is the page's types, and the header's are the header's.
        $this->assertSame(['page-text'], array_keys($renderer->usedTypes()));
    }

    #[Test]
    public function the_entity_is_the_page_the_visitor_is_on_and_nothing_is_cached_between_requests(): void
    {
        $this->publish('here', '<p class="here">{{ $entity?->title ?? \'no entity\' }} at [{{ $region?->path() }}]</p>');
        $this->region('header', [$this->node('here')]);

        RegionPage::query()->create(['slug' => 'about', 'title' => 'About'])->publish();
        RegionPage::query()->create(['slug' => 'team', 'title' => 'Team'])->publish();

        Route::get('/contact-form', static fn (): string => Blade::render('<x-webx-blocks::region name="header" />'))->middleware('web');

        $this->get('/about')->assertOk()->assertSee('<p class="here">About at [about]</p>', false);

        // A second address in the same process: the header is drawn again, not served from memory.
        $this->get('/team')->assertOk()->assertSee('<p class="here">Team at [team]</p>', false);

        // A route of the site's own: no entity, and the template is ready for that.
        $this->get('/contact-form')->assertOk()->assertSee('<p class="here">no entity at [contact-form]</p>', false);
    }

    #[Test]
    public function the_published_tree_is_cached_and_publication_forgets_it(): void
    {
        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>');
        $region = $this->region('header', [$this->node('bar', ['text' => 'One'])]);

        $this->assertStringContainsString('One', $this->tag());

        // Written behind the model's back: the cache still has the tree it read.
        DB::table('block_regions')->where('id', $region->id)->update(['blocks' => json_encode([$this->node('bar', ['text' => 'Sneaked'])])]);
        $this->assertStringContainsString('One', $this->tag());

        // Publishing drops it.
        $this->region('header', [$this->node('bar', ['text' => 'Two'])]);
        $this->assertStringContainsString('Two', $this->tag());

        // So does taking it off, and the fallback is back.
        $region->refresh()->unpublish();
        $this->assertStringContainsString('Header from code', $this->tag());

        // And a version restored and published.
        $region->refresh()->restoreVersion(1);
        $region->refresh()->publish();
        $this->assertStringContainsString('One', $this->tag());
    }

    #[Test]
    public function one_tree_serves_every_language(): void
    {
        $this->app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'ru']]);

        $this->publish('greet', '<p>{{ $title }}</p>', [], [
            'schema' => [['id' => 'title', 'type' => 'wx-input', 'localized' => true]],
        ]);
        $this->region('header', [$this->node('greet', ['title' => ['en' => 'Hello', 'ru' => 'Привет']])]);

        app()->setLocale('ru');
        $this->assertStringContainsString('<p>Привет</p>', $this->tag());

        app()->setLocale('en');
        $this->assertStringContainsString('<p>Hello</p>', $this->tag());
    }

    #[Test]
    public function the_fallback_the_layout_names_is_remembered_for_the_panel(): void
    {
        $regions = $this->app->make(Regions::class);

        $this->assertNull($regions->fallbackOf('header'));

        $this->tag();

        $this->assertSame('region-site::header', $regions->fallbackOf('header'));
    }

    #[Test]
    public function prune_removes_the_rows_whose_name_left_the_config(): void
    {
        $this->region('header', []);
        $this->region('sidebar', [], published: false);

        $this->artisan('webx:blocks:regions', ['--prune' => true])->assertSuccessful();

        $this->assertSame(['header'], Region::query()->pluck('name')->all());
    }
}
