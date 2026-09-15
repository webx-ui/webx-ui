<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Resolver;
use WebxUi\Routing\Tests\Fixtures\Category;
use WebxUi\Routing\Tests\Fixtures\Page;
use WebxUi\Routing\Tests\Fixtures\PageHandler;

/**
 * Turning a request into an answer (§8).
 *
 * Every one of these goes through the real fallback route and the real middleware, because that
 * is the half of it that cannot be checked by calling the resolver directly: a fallback is only
 * worth anything if the project's own routes beat it.
 */
class ResolutionTest extends TestCase
{
    #[Test]
    public function an_exact_address_beats_a_prefix(): void
    {
        $about = $this->page('about');
        $mission = $this->page('mission', $about);

        // The page at `about` would match `about/mission` by prefix, and must not: a page is
        // its own page, not a tail handed to its parent (§2, decision 7).
        $this->get('/about/mission')
            ->assertOk()
            ->assertJsonPath('id', $mission->getKey())
            ->assertJsonPath('tail', '');
    }

    #[Test]
    public function a_tail_reaches_the_handler_of_a_type_that_takes_one(): void
    {
        Category::create(['name' => 'Parts', 'slug' => 'parts']);

        // The whole answer to catalogue filters: one row, and the grammar of the tail is the
        // catalogue's business, not routing's.
        $this->get('/parts/brands-bobcat/stock-in-stock')
            ->assertOk()
            ->assertJsonPath('tail', 'brands-bobcat/stock-in-stock');
    }

    #[Test]
    public function a_type_that_takes_no_tail_answers_404(): void
    {
        $this->page('about');

        $this->get('/about/whatever')->assertNotFound();
    }

    #[Test]
    public function the_home_page_does_not_swallow_the_site(): void
    {
        // A root page has an address of `''`, which is a prefix of every address there is.
        $home = $this->page('');
        Category::create(['name' => 'Parts', 'slug' => 'parts']);

        $this->get('/')->assertOk()->assertJsonPath('id', $home->getKey());
        $this->get('/parts')->assertOk()->assertJsonPath('tail', '');
        $this->get('/nothing-here')->assertNotFound();
    }

    #[Test]
    public function an_alias_answers_301_and_takes_the_tail_with_it(): void
    {
        $category = Category::create(['name' => 'Parts', 'slug' => 'parts']);

        $category->update(['slug' => 'components']);

        // A renamed category takes its pages of filters along; the alternative is a few
        // thousand dead addresses for one rename.
        $this->assertRedirectsTo($this->get('/parts/brands-bobcat'), '/components/brands-bobcat');
        $this->assertRedirectsTo($this->get('/parts'), '/components');
    }

    #[Test]
    public function a_second_rename_still_redirects_once(): void
    {
        $category = Category::create(['name' => 'Parts', 'slug' => 'parts']);

        $category->update(['slug' => 'components']);
        $category->update(['slug' => 'spares']);

        $this->assertRedirectsTo($this->get('/parts'), '/spares');
    }

    #[Test]
    public function a_spelling_that_is_not_the_canonical_one_is_a_301_that_keeps_the_query(): void
    {
        $this->page('about');

        $this->assertRedirectsTo($this->get('/About?page=2'), '/about?page=2');

        // A trailing slash and a doubled one have to be asked for below the HTTP helper: it
        // tidies the address it is given before the application ever sees it, which is exactly
        // the tidying under test here.
        $response = $this->app->make(Resolver::class)->resolve(Request::create('/About//?page=2', 'GET'));

        $this->assertSame(301, $response->getStatusCode());
        $this->assertStringEndsWith('/about?page=2', (string) $response->headers->get('Location'));
    }

    #[Test]
    public function a_route_of_the_project_wins(): void
    {
        $category = Category::create(['name' => 'Search', 'slug' => 'search']);

        // Registered after the address was taken, which is the only way round: saving a page on
        // an address the project already routes is refused (§10, and ReservedTest).
        Router::middleware('web')->get('search', fn (): string => 'the project answers');

        $this->get('/search')->assertOk()->assertSee('the project answers');
        $this->assertSame('search', $category->routeCanonical()?->path);
    }

    #[Test]
    public function a_draft_answers_404_and_the_preview_token_opens_it(): void
    {
        $page = $this->page('about');
        $page->update(['published' => false]);

        // The registry has no idea any of this happened, and that is the point (§2, decision 9).
        $this->get('/about')->assertNotFound();
        $this->get('/about?token='.PageHandler::PREVIEW)->assertOk()->assertJsonPath('preview', true);
    }

    #[Test]
    public function the_language_prefix_is_stripped_before_the_lookup(): void
    {
        $this->useLocales(['en', 'uk']);

        // Both spellings from the start. Adding the Ukrainian one later would be a rename, and
        // a rename leaves an alias — which is correct, and would be a different test.
        $page = new Page(['title' => 'About']);
        $page->setTranslation('slug', 'en', 'about');
        $page->setTranslation('slug', 'uk', 'pro-nas');
        $page->saveAsRoot();

        $this->get('/about')->assertOk()->assertJsonPath('id', $page->getKey());
        $this->get('/uk/pro-nas')->assertOk()->assertJsonPath('id', $page->getKey());

        // The Ukrainian address does not answer at the root, and the English one does not answer
        // under the prefix: two languages, two addresses, one entity.
        $this->get('/pro-nas')->assertNotFound();
        $this->get('/uk/about')->assertNotFound();
    }

    #[Test]
    public function an_address_written_in_another_alphabet_survives_the_round_trip(): void
    {
        Category::create(['name' => 'Ремни', 'slug' => 'ремни']);

        $this->get('/'.rawurlencode('ремни'))->assertOk()->assertJsonPath('tail', '');
    }

    /** @param  TestResponse<Response>  $response */
    private function assertRedirectsTo(TestResponse $response, string $path): void
    {
        $response->assertStatus(301);

        $location = (string) $response->headers->get('Location');

        $this->assertSame(
            $path,
            rawurldecode((string) parse_url($location, PHP_URL_PATH)).$this->queryOf($location),
            "Expected a redirect to {$path}, got {$location}",
        );
    }

    private function queryOf(string $location): string
    {
        $query = parse_url($location, PHP_URL_QUERY);

        return is_string($query) && $query !== '' ? '?'.$query : '';
    }
}
