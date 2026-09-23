<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;
use WebxUi\Menu\Tests\Fixtures\Scoped;

/**
 * The section by its other doors (§11): what an agent can do with the menus of a site.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function the_module_offers_six_tools_and_one_resource_under_two_scopes(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        // Longer than the module prefix needs, because a real client shows a tool by the part
        // of its name after that prefix: `menu_list` and `menu_get` would stand in the
        // connector's settings as "List" and "Get" beside everybody else's.
        $this->assertSame(
            ['menu_list_menus', 'menu_get_tree', 'menu_add_link', 'menu_update_link', 'menu_move_link', 'menu_remove_link'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('menu')),
        );

        $this->assertContains('menu:read', $registry->scopes());
        $this->assertContains('menu:write', $registry->scopes());

        $this->assertSame(
            ['menu://menus'],
            array_map(static fn ($resource): string => $resource->uri, $registry->resources()),
        );
    }

    #[Test]
    public function the_tools_are_behind_the_permissions_the_panel_asks_for_the_same_work(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        // Reading is open to either of the pair, as the panel's own list is; writing is not.
        $this->assertSame(['menu.view', 'menu.manage'], $registry->tool('menu_get_tree')->permissions());
        $this->assertSame(['menu.manage'], $registry->tool('menu_add_link')->permissions());

        $reader = $this->editor(['menu.view']);

        $this->agent('get_tree', ['menu' => 'header'], $reader)->assertOk();
        $this->agent('add_link', ['menu' => 'header', 'target' => 'none'], $reader)
            ->assertHasErrors(['[menu.manage]']);
    }

    #[Test]
    public function a_token_that_may_only_read_is_refused_before_the_handler_runs(): void
    {
        $reader = $this->editor();
        $reader->withAccessToken(new Scoped(['menu:read']));

        $this->agent('get_tree', ['menu' => 'header'], $reader)->assertOk();
        $this->agent('add_link', ['menu' => 'header', 'target' => 'none'], $reader)
            ->assertHasErrors(['[menu:write]']);

        $this->assertSame(0, MenuItem::query()->count());
    }

    #[Test]
    public function the_list_says_which_menus_a_template_asks_for_and_what_is_in_them(): void
    {
        $this->item(['target' => 'none', 'title' => ['en' => 'Services']]);

        $content = $this->content($this->agent('list_menus')->assertOk());

        $byKey = array_column($content['menus'], null, 'key');

        $this->assertSame(['header', 'footer'], array_keys($byKey));
        $this->assertTrue($byKey['header']['declared']);
        $this->assertSame(1, $byKey['header']['items_count']);
        $this->assertSame(['link', 'button'], $byKey['header']['variants']);

        // A declared menu nobody has saved is on the list before it has a row — which is the
        // whole of what "the row appears on first save" means from the outside.
        $this->assertNull($byKey['footer']['id'] ?? null);
        $this->assertSame(0, $byKey['footer']['items_count']);
        $this->assertTrue($byKey['footer']['cache']['enabled']);
    }

    #[Test]
    public function the_tree_carries_the_address_the_site_would_print_and_whether_it_leads_anywhere(): void
    {
        $live = $this->thing('services', 'Services');
        $draft = $this->thing('pricing', 'Pricing', published: false);

        $heading = $this->item(['target' => 'none', 'is_heading' => true, 'title' => ['en' => 'More']]);
        $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $live->getKey()], null, $heading);
        $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $draft->getKey()]);

        $content = $this->content($this->agent('get_tree', ['menu' => 'header'])->assertOk());

        $this->assertSame('header', $content['menu']);
        $this->assertCount(2, $content['items']);

        [$first, $second] = $content['items'];

        $this->assertTrue($first['is_heading']);
        $this->assertNull($first['href']);
        $this->assertSame('Services', $first['children'][0]['label']);
        $this->assertStringEndsWith('/services', (string) $first['children'][0]['href']);
        $this->assertTrue($first['children'][0]['available']);

        // A draft is a legitimate target — a menu is built before the pages in it are
        // published — so it is reported and marked rather than left out.
        $this->assertSame('Pricing', $second['label']);
        $this->assertFalse($second['available']);
    }

    #[Test]
    public function an_agent_adds_an_item_and_a_dry_run_adds_nothing(): void
    {
        $thing = $this->thing('services', 'Services');

        $this->agent('add_link', [
            'menu' => 'header',
            'target' => 'entity',
            'entity_type' => 'thing',
            'entity_id' => $thing->getKey(),
            'dry_run' => true,
        ])->assertOk()->assertSee('"would_add":"thing #'.$thing->getKey().'"');

        $this->assertSame(0, MenuItem::query()->count());

        $content = $this->content($this->agent('add_link', [
            'menu' => 'header',
            'target' => 'entity',
            'entity_type' => 'thing',
            'entity_id' => $thing->getKey(),
        ])->assertOk());

        // No label of its own, so it is called what the entity is called — which is what the
        // panel shows and what the site prints.
        $this->assertSame([], $content['item']['title']);
        $this->assertSame('Services', $content['item']['label']);
        $this->assertSame('link', $content['item']['variant']);
        $this->assertTrue($content['item']['visible']);
    }

    #[Test]
    public function an_added_item_goes_where_it_was_asked_to_go(): void
    {
        $this->item(['target' => 'none', 'title' => ['en' => 'First']]);
        $this->item(['target' => 'none', 'title' => ['en' => 'Second']]);

        $this->agent('add_link', [
            'menu' => 'header',
            'target' => 'url',
            'url' => 'https://example.com/',
            'title' => 'Partner',
            'new_tab' => true,
            'rel' => ['nofollow'],
            'variant' => 'button',
            'index' => 1,
        ])->assertOk();

        $this->assertSame(['First', 'Partner', 'Second'], $this->labels());

        $added = MenuItem::query()->where('url', 'https://example.com/')->firstOrFail();

        $this->assertSame('button', $added->variant);
        $this->assertTrue($added->new_tab);
        $this->assertSame(['nofollow'], $added->rel);
        // The protection is added by whoever renders a new tab; it is not a choice and so it
        // is not in the column.
        $this->assertSame('nofollow noopener noreferrer', $added->link()->relAttribute());
    }

    #[Test]
    public function a_look_the_menu_never_declared_is_refused(): void
    {
        $this->agent('add_link', ['menu' => 'footer', 'target' => 'none', 'variant' => 'button'])
            ->assertHasErrors(['variant']);

        $this->assertSame(0, MenuItem::query()->count());
    }

    #[Test]
    public function an_update_changes_what_was_sent_and_leaves_the_rest(): void
    {
        $thing = $this->thing('services', 'Services');
        $item = $this->item([
            'target' => 'entity',
            'entity_type' => 'thing',
            'entity_id' => $thing->getKey(),
            'title' => ['en' => 'Our services'],
            'variant' => 'button',
        ]);

        $this->agent('update_link', ['menu' => 'header', 'item' => $item->getKey(), 'visible' => false])->assertOk();

        $item->refresh();

        $this->assertFalse($item->visible);
        $this->assertSame('entity', $item->target);
        $this->assertSame('button', $item->variant);
        $this->assertSame('Our services', $item->getTranslation('title', 'en'));

        // A target that changed takes the fields of the old one with it: an item that points
        // at an address keeps no morph pair for everything afterwards to step over.
        $this->agent('update_link', ['menu' => 'header', 'item' => $item->getKey(), 'target' => 'url', 'url' => '/contact'])
            ->assertOk();

        $item->refresh();

        $this->assertSame('url', $item->target);
        $this->assertSame('/contact', $item->url);
        $this->assertNull($item->entity_type);
        $this->assertNull($item->entity_id);
    }

    #[Test]
    public function an_item_is_moved_under_another_one_and_never_inside_its_own_branch(): void
    {
        $heading = $this->item(['target' => 'none', 'title' => ['en' => 'More']]);
        $child = $this->item(['target' => 'none', 'title' => ['en' => 'Prices']], null, $heading);
        $loose = $this->item(['target' => 'none', 'title' => ['en' => 'Contact']]);

        $this->agent('move_link', ['menu' => 'header', 'item' => $loose->getKey(), 'parent' => $heading->getKey(), 'index' => 0])
            ->assertOk();

        $this->assertSame($heading->getKey(), $loose->refresh()->parent_id);
        $this->assertSame(['Contact', 'Prices'], array_map(
            static fn (MenuItem $item): string => (string) $item->getTranslation('title', 'en'),
            MenuItem::query()->where('parent_id', $heading->getKey())->ordered()->get()->all(),
        ));

        $this->agent('move_link', ['menu' => 'header', 'item' => $heading->getKey(), 'parent' => $child->getKey(), 'index' => 0])
            ->assertHasErrors(['own branch']);
    }

    #[Test]
    public function removing_an_item_takes_its_branch_and_says_how_many(): void
    {
        $heading = $this->item(['target' => 'none', 'title' => ['en' => 'More']]);
        $this->item(['target' => 'none', 'title' => ['en' => 'Prices']], null, $heading);
        $this->item(['target' => 'none', 'title' => ['en' => 'Terms']], null, $heading);

        $this->agent('remove_link', ['menu' => 'header', 'item' => $heading->getKey(), 'dry_run' => true])
            ->assertOk()
            ->assertSee('"would_remove":3');

        $this->assertSame(3, MenuItem::query()->count());

        $this->agent('remove_link', ['menu' => 'header', 'item' => $heading->getKey()])
            ->assertOk()
            ->assertSee('"removed":3');

        $this->assertSame(0, MenuItem::query()->count());
    }

    #[Test]
    public function a_menu_this_site_does_not_have_is_refused_and_no_row_is_made_for_it(): void
    {
        $this->agent('add_link', ['menu' => 'sidebar', 'target' => 'none'])
            ->assertHasErrors(['no menu [sidebar]']);

        $this->assertSame(0, Menu::query()->count());
    }

    #[Test]
    public function the_resource_carries_the_catalogue_and_the_house_rules(): void
    {
        $registry = $this->app->make(ToolRegistry::class);
        $resource = $registry->resources()[0];

        /** @var array<string, mixed> $document */
        $document = ($resource->handler)();

        $this->assertSame(['header', 'footer'], array_column($document['menus'], 'key'));
        $this->assertSame(['thing'], array_column($document['link_to'], 'entity_type'));
        $this->assertNotEmpty($document['rules']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $registry = $this->app->make(ToolRegistry::class);
        $bound = new RegistryTool($registry->tool('menu_'.$tool));

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
