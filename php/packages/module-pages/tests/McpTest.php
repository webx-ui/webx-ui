<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Prompt;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;

/**
 * The section by its other doors (§13): what an agent can do with the pages of a site, and
 * what it is refused.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function the_module_offers_its_tools_the_sitemap_and_a_prompt(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['pages_tree', 'pages_get', 'pages_create', 'pages_update', 'pages_move', 'pages_publish', 'pages_unpublish', 'pages_delete', 'pages_restore'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('pages')),
        );

        $this->assertContains('pages:read', $registry->scopes());
        $this->assertContains('pages:write', $registry->scopes());

        $this->assertContains(
            'pages://sitemap',
            array_map(static fn ($resource): string => $resource->uri, $registry->resources()),
        );

        $this->assertContains(
            'build_page',
            array_map(static fn ($prompt): string => $prompt->name, $registry->prompts()),
        );

        // Every tool of the panel fits in one page of `tools/list`: a client that does not
        // follow the cursor would otherwise decide the rest do not exist. The page size is the
        // smaller of the two, so both have to be past the count.
        $defaults = (new ReflectionClass(WebxServer::class))->getDefaultProperties();

        $this->assertGreaterThan(
            count($registry->tools()),
            min((int) $defaults['defaultPaginationLength'], (int) $defaults['maxPaginationLength']),
        );
    }

    #[Test]
    public function the_tree_answers_the_whole_site_one_level_or_a_search(): void
    {
        $about = $this->page('about');
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);

        $this->agent('tree')
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($about, $shoes): void {
                $content = $json->etc()->toArray();

                // The home page first, because `lft` reads the tree from the top, and the whole
                // branch under it.
                $this->assertSame(4, $content['count']);
                $this->assertTrue($content['pages'][0]['is_home']);
                $this->assertSame('/', $content['pages'][0]['urls']['en']['path']);
                $this->assertSame(['move' => false, 'delete' => false, 'address' => false], $content['pages'][0]['can']);

                $byId = array_column($content['pages'], null, 'id');
                $this->assertSame('/about', $byId[$about->getKey()]['urls']['en']['path']);
                $this->assertSame('/catalog/shoes', $byId[$shoes->getKey()]['urls']['en']['path']);
                $this->assertSame(2, $byId[$shoes->getKey()]['depth']);
            });

        // One level: the catalogue and its children, nothing from the rest of the site.
        $this->agent('tree', ['parent' => '/catalog', 'depth' => 1])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(2, $content['count']);
                $this->assertSame(['Catalog', 'Shoes'], array_map(static fn (array $page): string => $page['title']['en'], $content['pages']));
            });

        $this->agent('tree', ['search' => 'sho'])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($shoes): void {
                $content = $json->etc()->toArray();

                $this->assertSame(1, $content['count']);
                $this->assertSame($shoes->getKey(), $content['pages'][0]['id']);
            });

        // `locale` says which language to answer in, not which one to look in: the tree shows
        // this page whatever is asked for, so a name it carries in any language finds it.
        $this->useLocales('en', 'ru');
        $shoes->setTranslation('title', 'ru', 'Обувь');
        $shoes->save();

        $this->agent('tree', ['search' => 'Обув', 'locale' => 'en'])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($shoes): void {
                $content = $json->etc()->toArray();

                $this->assertSame(1, $content['count']);
                $this->assertSame($shoes->getKey(), $content['pages'][0]['id']);
            });

        $secret = $this->page('secret', published: false);

        $this->agent('tree', ['status' => Page::STATUS_DRAFT])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($secret): void {
                $content = $json->etc()->toArray();

                // The home page and the one that was never published. The migration's root is
                // a page nobody has published either, and the filter says so rather than
                // making an exception for it.
                $this->assertSame(
                    [true, $secret->getKey()],
                    [$content['pages'][0]['is_home'], $content['pages'][1]['id']],
                );
                $this->assertSame(2, $content['count']);
            });
    }

    #[Test]
    public function a_page_is_read_by_its_address_and_by_its_id(): void
    {
        $about = $this->page('about');
        $about->saveDraft(['title' => ['en' => 'About us']]);

        $byPath = $this->agent('get', ['page' => '/about'])->assertOk();
        $byId = $this->agent('get', ['page' => $about->getKey()])->assertOk();

        foreach ([$byPath, $byId] as $response) {
            $response->assertStructuredContent(function (AssertableJson $json) use ($about): void {
                $content = $json->etc()->toArray();

                $this->assertSame($about->getKey(), $content['page']['id']);
                // The title is the draft's — what somebody is working on — while the address is
                // the registry's, which is what the site answers at right now.
                $this->assertSame(['en' => 'About us'], $content['page']['title']);
                $this->assertSame('/about', $content['page']['urls']['en']['path']);
                $this->assertSame(Page::STATUS_MODIFIED, $content['page']['status']);
                $this->assertSame(['en' => 'About us'], $content['values']['title']);
                $this->assertArrayHasKey('blocks', $content['values']);
                $this->assertArrayHasKey('seo', $content['values']);
                $this->assertNotSame('', $content['revision']);
                $this->assertStringContainsString('preview', (string) $content['preview_url']);
                // The trail above it, for a page that is being placed rather than read.
                $this->assertTrue($content['ancestors'][0]['is_home']);
            });
        }

        $this->agent('get', ['page' => '/about', 'blocks' => false])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertArrayNotHasKey('blocks', $json->etc()->toArray()['values']);
            });

        $this->agent('get', ['page' => '/nowhere'])
            ->assertHasErrors(['No page answers at [/nowhere]']);

        $this->agent('get', ['page' => 9999])
            ->assertHasErrors(['No page has the id [9999]']);
    }

    #[Test]
    public function an_agent_creates_a_page_as_a_draft_and_a_dry_run_creates_nothing(): void
    {
        $this->useLocales('en', 'ru');

        $this->agent('create', ['title' => 'Contacts', 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(['en' => 'Contacts'], $content['would_create']['title']);
                $this->assertSame(['en' => 'contacts'], $content['would_create']['slug']);
            });

        $this->assertSame(1, Page::query()->count());

        $editor = $this->editor();

        $this->agent('create', [
            'title' => ['en' => 'Contacts', 'ru' => 'Контакты'],
            'values' => ['seo' => ['title' => ['en' => 'Talk to us']]],
        ], $editor)
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                // The address is made from the title, language by language, and transliterated:
                // a Russian name gives a Latin address rather than a percent-encoded one.
                $this->assertSame(['en' => 'contacts', 'ru' => 'kontakty'], $content['values']['slug']);
                $this->assertSame('/contacts', $content['page']['urls']['en']['path']);
                $this->assertSame('/kontakty', $content['page']['urls']['ru']['path']);
                // A draft, never published: the site shows nothing until a person says so.
                $this->assertSame(Page::STATUS_DRAFT, $content['page']['status']);
                $this->assertSame(['en' => 'Talk to us'], $content['values']['seo']['title']);
                $this->assertSame('Editor', $content['page']['edited_by']);
            });

        // Under a named parent, by address.
        $this->page('catalog');

        $this->agent('create', ['title' => 'Shoes', 'parent' => '/catalog'])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertSame('/catalog/shoes', $json->etc()->toArray()['page']['urls']['en']['path']);
            });

        // An address is chosen deliberately; one already taken is refused rather than suffixed.
        $this->agent('create', ['title' => 'Contacts again', 'slug' => 'contacts'])
            ->assertHasErrors(['already taken']);
    }

    #[Test]
    public function a_refused_value_leaves_no_page_and_no_address_behind(): void
    {
        $routes = Route::query()->count();

        // A translated field given as a bare string: the screen refuses it after the node is in.
        $this->agent('create', ['title' => 'Contacts', 'values' => ['title' => 'Contacts']], $this->editor())
            ->assertHasErrors(['title']);

        $this->assertSame(1, Page::query()->withTrashed()->count());
        $this->assertSame($routes, Route::query()->count());

        // And the address is still free for the call that gets it right.
        $this->agent('create', ['title' => 'Contacts', 'values' => ['title' => ['en' => 'Contacts']]], $this->editor())
            ->assertOk();
    }

    #[Test]
    public function an_update_writes_the_draft_and_an_old_revision_is_refused(): void
    {
        $about = $this->page('about');

        $read = $this->agent('get', ['page' => '/about'])->assertOk();
        $revision = $this->content($read)['revision'];

        $this->agent('update', ['page' => '/about', 'values' => ['title' => ['en' => 'About us']], 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame('draft', $content['would_write']);
                $this->assertSame(['title'], $content['fields']);
            });

        $this->assertFalse($about->refresh()->hasDraft());

        $this->agent('update', [
            'page' => '/about',
            'values' => ['title' => ['en' => 'About us']],
            'revision' => $revision,
        ], $this->editor())
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(['en' => 'About us'], $content['values']['title']);
                // The draft alone: the site still shows what it showed.
                $this->assertSame(Page::STATUS_MODIFIED, $content['page']['status']);
            });

        $this->assertSame('About', $about->refresh()->getTranslation('title', 'en'));

        // The revision from before that write is now a page somebody else has edited.
        $this->agent('update', ['page' => '/about', 'values' => ['title' => ['en' => 'Third']], 'revision' => $revision])
            ->assertHasErrors(['changed since you read it', 'pages_get']);

        $this->assertSame(['en' => 'About us'], $about->refresh()->draftValues()['title']);
    }

    #[Test]
    public function content_is_not_written_through_the_page_tools(): void
    {
        $this->page('about');

        // §13.1: there is one way to change the blocks of a page, and it is not this one. Said
        // out loud, because an agent that got a cheerful answer would think it had saved a tree.
        $this->agent('update', ['page' => '/about', 'values' => ['blocks' => [['type' => 'text', 'values' => []]]]])
            ->assertHasErrors(['blocks_edit_content']);

        $this->agent('create', ['title' => 'News', 'values' => ['blocks' => []]])
            ->assertHasErrors(['blocks_edit_content']);

        $this->assertNull(Page::query()->whereTranslation('title', 'News', 'en')->first());
    }

    #[Test]
    public function moving_a_page_rewrites_the_addresses_under_it(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);
        $red = $this->page('red', $shoes);
        $about = $this->page('about');

        $this->agent('move', ['page' => '/catalog/shoes', 'target' => '/about', 'zone' => 'inside', 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertSame(2, $json->etc()->toArray()['addresses_would_change']);
            });

        $this->assertSame('catalog/shoes', $shoes->refresh()->routeCanonical('en')?->path);

        $this->agent('move', ['page' => '/catalog/shoes', 'target' => $about->getKey(), 'zone' => 'inside'])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(2, $content['addresses_changed']);
                $this->assertSame('/about/shoes', $content['page']['urls']['en']['path']);
            });

        $this->assertSame('about/shoes/red', $red->refresh()->routeCanonical('en')?->path);

        // The old addresses stay as redirects rather than disappearing.
        $this->assertTrue(Route::query()->where('path', 'catalog/shoes/red')->where('kind', Route::ALIAS)->exists());

        $this->agent('move', ['page' => '/about', 'target' => '/about/shoes', 'zone' => 'inside'])
            ->assertHasErrors(['itself']);

        $this->agent('move', ['page' => '/', 'target' => '/about', 'zone' => 'inside'])
            ->assertHasErrors([]);
    }

    #[Test]
    public function publishing_and_unpublishing_change_only_what_the_site_shows(): void
    {
        $about = $this->page('about', published: false);
        $about->saveDraft(['title' => ['en' => 'About us'], 'slug' => ['en' => 'about']]);

        $this->agent('publish', ['page' => '/about', 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(Page::STATUS_DRAFT, $content['status']);
                $this->assertTrue($content['has_waiting_edits']);
            });

        $this->assertFalse($about->refresh()->isPublished());

        $this->agent('publish', ['page' => '/about'], $this->editor())
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(Page::STATUS_PUBLISHED, $content['page']['status']);
                $this->assertFalse($content['page']['has_draft']);
            });

        $this->assertSame('About us', $about->refresh()->getTranslation('title', 'en'));
        $this->assertSame('mcp', $about->publishedVersions()->latest('id')->first()?->source);

        $this->agent('unpublish', ['page' => '/about'])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertSame(Page::STATUS_DRAFT, $json->etc()->toArray()['page']['status']);
            });

        // The address stays in the registry: whether a page answers is the handler's question,
        // and an address released here would be taken by the next page called the same thing.
        $this->assertSame('about', $about->refresh()->routeCanonical('en')?->path);
    }

    #[Test]
    public function deleting_takes_the_branch_and_restoring_brings_it_back(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);
        $this->page('red', $shoes);

        $this->agent('delete', ['page' => '/catalog', 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertSame(3, $json->etc()->toArray()['would_trash']);
            });

        $this->assertSame(4, Page::query()->count());

        $this->agent('delete', ['page' => '/catalog'])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertSame(3, $json->etc()->toArray()['trashed']);
            });

        // The addresses are released with the branch, so the pages cannot be found by them any
        // more — which is exactly what makes them free for somebody else.
        $this->assertSame(0, Route::query()->where('path', 'catalog/shoes')->count());
        $this->agent('get', ['page' => '/catalog'])->assertHasErrors(['No page answers at']);

        $this->agent('tree', ['trashed' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($catalog): void {
                $content = $json->etc()->toArray();

                // Only what somebody actually deleted: the two that went down with it are
                // restored with it and have no life of their own in here.
                $this->assertSame(1, $content['count']);
                $this->assertSame($catalog->getKey(), $content['pages'][0]['id']);
                $this->assertNotNull($content['pages'][0]['deleted_at']);
            });

        // And the bin is searched like the tree is, rather than answering with all of it.
        $this->agent('tree', ['trashed' => true, 'search' => 'zzzznothing'])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertSame(0, $json->etc()->toArray()['count']);
            });

        $this->agent('restore', ['page' => '/catalog'])
            ->assertHasErrors(['must be the id']);

        $this->agent('restore', ['page' => $catalog->getKey()])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertSame(3, $json->etc()->toArray()['restored']);
            });

        $this->assertSame(4, Page::query()->count());
        $this->assertSame('catalog/shoes/red', Page::query()->whereTranslation('slug', 'red', 'en')->first()?->routeCanonical('en')?->path);

        $this->agent('restore', ['page' => $catalog->getKey()])
            ->assertHasErrors(['is not in the bin']);
    }

    #[Test]
    public function a_page_in_the_bin_is_counted_under_nothing_on_the_site(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);
        $red = $this->page('red', $shoes);

        $red->delete();

        // `descendants` is the size of what a delete would take, and a page already in the bin
        // is not part of it — the bounds it keeps for its own restore said otherwise.
        $content = $this->content($this->agent('get', ['page' => $shoes->getKey()]));

        $this->assertSame(0, $content['page']['descendants']);

        // The trail above it answers the same way: the home page with two pages under it and
        // the catalogue with one.
        $this->assertSame([2, 1], array_column($content['ancestors'], 'descendants'));
    }

    #[Test]
    public function the_home_page_keeps_its_place_and_its_address(): void
    {
        $home = $this->home();

        $this->agent('delete', ['page' => '/'])
            ->assertHasErrors([]);

        $this->assertFalse($home->refresh()->trashed());

        $about = $this->page('about');

        $this->agent('move', ['page' => $about->getKey(), 'target' => '/', 'zone' => 'before'])
            ->assertHasErrors([]);

        $this->assertSame(1, Page::query()->whereNull('parent_id')->count());

        // Its slug is empty in every language on purpose, and `PageForm` drops one sent for it
        // rather than refusing the whole save — the screen hides the field anyway.
        $this->agent('update', ['page' => '/', 'values' => ['title' => ['en' => 'Front'], 'slug' => ['en' => 'home']]])
            ->assertOk();

        $this->assertSame('', $home->refresh()->routeCanonical('en')?->path);
    }

    #[Test]
    public function the_sitemap_is_the_map_of_the_site(): void
    {
        $catalog = $this->page('catalog');
        $this->page('shoes', $catalog);
        $this->page('drafts', published: false);

        $resource = $this->resource('pages://sitemap');
        $map = ($resource->handler)();

        $this->assertSame(['en'], $map['locales']);
        $this->assertSame(4, $map['count']);

        // One root, and the site hanging off it the way it hangs off the home page.
        $this->assertCount(1, $map['pages']);
        $this->assertTrue($map['pages'][0]['is_home']);

        $top = array_column($map['pages'][0]['children'], null, 'id');
        $catalogNode = $top[$catalog->getKey()];

        $this->assertSame('/catalog', $catalogNode['paths']['en']);
        $this->assertSame('/catalog/shoes', $catalogNode['children'][0]['paths']['en']);

        // A page that is not on the site is on the map all the same, and says so.
        $statuses = array_column($map['pages'][0]['children'], 'status');
        $this->assertContains(Page::STATUS_DRAFT, $statuses);
    }

    #[Test]
    public function the_prompt_puts_the_loop_in_front_of_the_agent(): void
    {
        $prompt = $this->prompt('build_page');
        $text = ($prompt->handler)(['brief' => 'A page about our workshop', 'parent' => '/about']);

        $this->assertStringContainsString('A page about our workshop', $text);
        $this->assertStringContainsString('/about', $text);
        // The step a first attempt gets wrong: content is another module's tool.
        $this->assertStringContainsString('blocks_edit_content', $text);
        $this->assertStringContainsString('Do not publish', $text);

        $this->assertStringContainsString('ask what the page is for', ($prompt->handler)([]));
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No module offers the resource [{$uri}].");
    }

    private function prompt(string $name): Prompt
    {
        foreach ($this->app->make(ToolRegistry::class)->prompts() as $prompt) {
            if ($prompt->name === $name) {
                return $prompt;
            }
        }

        $this->fail("No module offers the prompt [{$name}].");
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $registry = $this->app->make(ToolRegistry::class);
        $bound = new RegistryTool($registry->tool('pages_'.$tool));

        return $as instanceof CmsUser
            ? WebxServer::actingAs($as, 'cms')->tool($bound, $arguments)
            : WebxServer::tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
