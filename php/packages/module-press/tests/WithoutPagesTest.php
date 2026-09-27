<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Press\Models\Outlet;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\RouteTypes;

/**
 * `webx-press.pages = false` (decision 11): no route type, no address, nothing for a menu to point
 * at, no slug on the form — and a logo in a block leads to the outlet's own site.
 */
final class WithoutPagesTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-press.pages', false);
    }

    #[Test]
    public function there_is_no_page_no_address_and_nothing_to_point_at(): void
    {
        $outlet = $this->outlet('Tatler', [$this->row('One')], values: ['website_url' => 'https://tatler.example']);

        $this->assertNull($this->app->make(RouteTypes::class)->for($outlet));
        $this->assertSame(0, Route::query()->where('path', 'like', 'press%')->count());
        $this->get('/press/tatler')->assertNotFound();
        $this->assertNotContains(Outlet::TYPE, array_map(static fn ($source): string => $source->type(), $this->app->make(LinkSources::class)->all()));

        $card = press()->first();
        $this->assertIsArray($card);
        $this->assertNull($card['url']);
        $this->assertSame('https://tatler.example', $card['link']);
        $article = press()->articles()->first();
        $this->assertIsArray($article);
        $this->assertArrayHasKey('url', $article['outlet']);
        $this->assertNull($article['outlet']['url']);
    }

    #[Test]
    public function the_form_has_no_slug_and_says_there_is_no_prefix(): void
    {
        $outlet = $this->outlet('Tatler', [$this->row('One')]);
        $editor = $this->editor();

        $screen = (string) json_encode($this->actingAs($editor, 'cms')->getJson('/api/cms/screens/'.Outlet::SCREEN)->assertOk()->json());
        $this->assertStringNotContainsString('"name":"slug"', $screen);

        $this->actingAs($editor, 'cms')->getJson($this->api($outlet->id))
            ->assertOk()
            ->assertJsonPath('data.prefix', null)
            ->assertJsonPath('data.outlet.url', null);
    }
}
