<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Resolution;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Seo\Rendering\SeoSource;
use WebxUi\Seo\Rendering\SeoSources;
use WebxUi\Seo\Tests\Fixtures\ResolvedPage;

/**
 * The seam between the two kinds of redirect a site has.
 *
 * One kind an editor wrote; the other a rename left behind. For a reader they answer the same
 * question — where did this page go — so they are shown in one place, and the difference between
 * them is that only one of the two can be edited there.
 */
final class RouteAliasesTest extends TestCase
{
    #[Test]
    public function it_lists_the_addresses_a_rename_left_behind(): void
    {
        $this->moved('about', 'about-us');

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api('aliases'));

        $response->assertOk();
        $response->assertJsonPath('data.0.pattern', '/about');
        $response->assertJsonPath('data.0.target', '/about-us');
        $response->assertJsonPath('data.0.entity_type', 'page');
        // The address as a browser asks for it, so a row can be opened and not only read.
        $this->assertStringEndsWith('/about-us', (string) $response->json('data.0.target_url'));
        $this->assertSame(1, $response->json('meta.total'));
    }

    #[Test]
    public function the_search_reads_an_address_the_way_the_registry_spells_it(): void
    {
        $this->moved('about', 'about-us');
        $this->moved('parts/belts', 'catalogue/belts');

        $editor = $this->editor();

        // Typed as it is seen on a page rather than as it is stored — a slash on each end and a
        // capital letter — and it still has to find the row.
        $found = $this->actingAs($editor, 'cms')->getJson($this->api('aliases').'?q=/About/');
        $this->assertSame(['/about'], array_column((array) $found->json('data'), 'pattern'));

        // Where it leads is an address too: an editor looking at a page knows the one it has
        // now, not the ones it used to have.
        $byTarget = $this->actingAs($editor, 'cms')->getJson($this->api('aliases').'?q=catalogue');
        $this->assertSame(['/parts/belts'], array_column((array) $byTarget->json('data'), 'pattern'));
    }

    #[Test]
    public function they_are_read_only_and_behind_the_same_permission_as_the_rest(): void
    {
        $this->moved('about', 'about-us');

        // A stranger first: `actingAs` stays on for the rest of the test once it is called.
        $this->getJson($this->api('aliases'))->assertUnauthorized();
        $this->actingAs($this->editor(['seo.view']), 'cms')->getJson($this->api('aliases'))->assertOk();

        // Nothing writes here: these rows are made and unmade by the entity that moved, and a
        // panel that could edit one would be a panel that can make the registry lie.
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('aliases'), ['pattern' => '/x'])
            ->assertStatus(405);
    }

    #[Test]
    public function checking_an_address_says_whether_a_live_page_is_standing_on_it(): void
    {
        $this->canonical('about');

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('test-url'), ['url' => '/about']);

        $response->assertOk();
        $response->assertJsonPath('data.route.path', '/about');
        $response->assertJsonPath('data.route.kind', 'canonical');
        $response->assertJsonPath('data.route.entity_type', 'page');
        $response->assertJsonPath('data.route.exact', true);

        // An address nobody has taken is simply free, and there is nothing to warn about.
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('test-url'), ['url' => '/nowhere'])
            ->assertJsonPath('data.route', null);
    }

    #[Test]
    public function the_head_takes_its_subject_from_whatever_the_registry_resolved(): void
    {
        app(SeoSources::class)->register(new class implements SeoSource
        {
            public function priority(): int
            {
                return 90;
            }

            public function forUrl(string $url, ?object $subject = null, ?string $locale = null): ?SeoData
            {
                return $subject instanceof ResolvedPage ? SeoData::make(['title' => $subject->title]) : null;
            }
        });

        $page = new ResolvedPage(['title' => 'Кто мы такие']);

        $request = Request::create('/about');
        $request->attributes->set(Resolution::ATTRIBUTE, new Resolution($this->canonical('about'), '', null, $page));
        app()->instance('request', $request);

        // No `:for` on the tag, and the entity arrives anyway: the address was looked up once
        // already, and making the template repeat that lookup is how the two disagree.
        $head = (string) app(Seo::class)->head(null, '/about', 'ru');

        $this->assertStringContainsString('<title>Кто мы такие</title>', $head);
    }

    /** A canonical row, as an entity with an address leaves one. */
    private function canonical(string $path, int $id = 1): Route
    {
        return Route::query()->create([
            'locale' => 'ru',
            'path' => $path,
            'kind' => Route::CANONICAL,
            'entity_type' => 'page',
            'entity_id' => $id,
        ]);
    }

    /** An entity that used to live at one address and lives at another now. */
    private function moved(string $was, string $now): Route
    {
        static $id = 0;
        $id++;

        $canonical = $this->canonical($now, $id);

        return Route::query()->create([
            'locale' => 'ru',
            'path' => $was,
            'kind' => Route::ALIAS,
            'target_id' => $canonical->id,
            'entity_type' => 'page',
            'entity_id' => $id,
        ]);
    }

    private function api(string $path): string
    {
        return '/api/cms/seo/'.$path;
    }
}
