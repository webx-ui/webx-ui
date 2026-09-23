<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use WebxUi\Menu\MenuCache;

/**
 * The two reset buttons, and the mark they are labelled with (§9).
 *
 * The mark is the whole of what these buttons say out loud: a reset that leaves the row reading
 * "built today at 08:10" is a reset nobody believes the second time. So each test here presses
 * the button and then reads the list, rather than reading the cache directly.
 */
class CacheApiTest extends TestCase
{
    public function test_the_list_says_when_each_menu_was_built(): void
    {
        $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about']);

        $listed = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertTrue($listed->json('data.0.cache.enabled'));
        $this->assertNull($listed->json('data.0.cache.built_at'), 'Nobody has read this menu yet.');

        // Reading it on the site is what builds it.
        $this->assertSame(['About'], $this->labels('header'));

        $listed = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertNotNull($listed->json('data.0.cache.built_at'));
    }

    public function test_resetting_one_menu_leaves_the_others_alone(): void
    {
        $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about'], $this->menu('header'));
        $this->item(['title' => ['en' => 'Terms'], 'target' => 'url', 'url' => '/terms'], $this->menu('footer'));

        $this->labels('header');
        $this->labels('footer');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('header/cache/flush'))
            ->assertNoContent();

        $cache = $this->app->make(MenuCache::class);

        $this->assertNull($cache->builtAt('header'));
        $this->assertNotNull($cache->builtAt('footer'), 'The footer was not the one asked about.');
    }

    public function test_one_menu_is_reset_in_every_language(): void
    {
        $this->useLocales('en', 'uk');

        $this->item(['title' => ['en' => 'About', 'uk' => 'Про нас'], 'target' => 'url', 'url' => '/about']);

        $this->labels('header', 'en');
        $this->labels('header', 'uk');

        $cache = $this->app->make(MenuCache::class);

        $this->assertNotNull($cache->builtAt('header'));

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('header/cache/flush'))
            ->assertNoContent();

        // `builtAt` answers with the oldest language it finds, so one left behind would show.
        $this->assertNull($cache->builtAt('header'));
    }

    public function test_every_menu_at_once(): void
    {
        $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about'], $this->menu('header'));
        $this->item(['title' => ['en' => 'Terms'], 'target' => 'url', 'url' => '/terms'], $this->menu('footer'));

        $this->labels('header');
        $this->labels('footer');

        $this->actingAs($this->editor(), 'cms')->postJson($this->api('cache/flush'))->assertNoContent();

        $cache = $this->app->make(MenuCache::class);

        $this->assertNull($cache->builtAt('header'));
        $this->assertNull($cache->builtAt('footer'));
    }

    public function test_resetting_is_writing(): void
    {
        $this->postJson($this->api('cache/flush'))->assertUnauthorized();

        $viewer = $this->editor(['menu.view']);

        $this->actingAs($viewer, 'cms')->postJson($this->api('cache/flush'))->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api('header/cache/flush'))->assertForbidden();
    }

    public function test_a_menu_that_is_not_here_has_no_cache_to_reset(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('nowhere/cache/flush'))
            ->assertNotFound();
    }

    public function test_the_list_says_when_the_cache_is_off(): void
    {
        $this->app['config']->set('webx-menu.cache.enabled', false);

        $listed = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertFalse($listed->json('data.0.cache.enabled'));
    }
}
