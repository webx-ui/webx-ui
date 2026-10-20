<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Snippets\Models\Snippet;
use WebxUi\Snippets\Output\Seen;

/**
 * The section by its other doors (§9 of the snippets spec): the same values, the same order, the
 * same refusals — and the places named whenever one is refused.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function the_tools_are_the_sections_by_name_scope_and_permission(): void
    {
        $registry = $this->app->make(ToolRegistry::class);
        $tools = [];

        foreach ($registry->toolsOf('snippets') as $tool) {
            $tools[$tool->fullName()] = [$tool->scope(), $tool->permissions(), $tool->tool->mutating];
        }

        $this->assertSame(
            ['snippets_list', 'snippets_get', 'snippets_create', 'snippets_update', 'snippets_delete', 'snippets_reorder'],
            array_keys($tools),
        );

        foreach (['snippets_list', 'snippets_get'] as $read) {
            $this->assertSame(['snippets:read', ['snippets.manage'], false], $tools[$read], $read);
        }

        foreach (['snippets_create', 'snippets_update', 'snippets_delete', 'snippets_reorder'] as $write) {
            $this->assertSame(['snippets:write', ['snippets.manage'], true], $tools[$write], $write);
            // The agent is told what a write is before it decides to make one.
            $this->assertStringStartsWith('Writing a snippet runs its JavaScript on every page', $registry->tool($write)->tool->description);
            $this->assertArrayHasKey('dry_run', $registry->tool($write)->tool->inputSchema['properties']);
        }

        $uris = array_map(static fn (McpResource $resource): string => $resource->uri, $registry->resources());
        $this->assertContains('snippets://catalog', $uris);
        $this->assertContains('snippets://places', $uris);
    }

    #[Test]
    public function the_tools_are_listed_by_the_command(): void
    {
        $this->artisan('webx:mcp-tools', ['--module' => 'snippets'])
            ->expectsOutputToContain('snippets_create')
            ->expectsOutputToContain('snippets_reorder')
            ->assertSuccessful();
    }

    #[Test]
    public function an_editor_without_the_permission_is_refused_even_a_read(): void
    {
        $editor = $this->admin(super: false, permissions: ['pages.manage']);

        $this->agent('snippets_list', [], $editor)->assertHasErrors(['[snippets.manage]']);
        $this->agent('snippets_create', ['name' => 'Chat', 'place' => 'head', 'code' => '<script></script>'], $editor)
            ->assertHasErrors(['[snippets.manage]']);

        $granted = $this->admin(super: false, permissions: ['snippets.manage']);
        $this->agent('snippets_list', [], $granted)->assertOk();
    }

    #[Test]
    public function create_get_update_delete_go_through_the_form(): void
    {
        $created = $this->content($this->agent('snippets_create', [
            'name' => 'Tag manager',
            'place' => 'head',
            'code' => '<script>window.example = 1;</script>',
            'consent' => 'statistics',
            'only_paths' => ['/blog/*'],
        ]));

        $id = $created['snippet']['id'];
        $this->assertIsInt($id);
        // Off unless asked for, and the paths in their stored shape.
        $this->assertFalse($created['values']['enabled']);
        $this->assertSame(['/blog/*'], $created['values']['only_paths']);
        $this->assertSame([], $created['warnings']);

        $got = $this->content($this->agent('snippets_get', ['snippet' => $id]));
        $this->assertSame('<script>window.example = 1;</script>', $got['values']['code']);

        $updated = $this->content($this->agent('snippets_update', ['snippet' => $id, 'enabled' => true, 'locales' => ['ru']]));
        $this->assertTrue($updated['values']['enabled']);
        $this->assertSame(['ru'], $updated['values']['locales']);
        // What was not sent keeps what it had.
        $this->assertSame('statistics', $updated['values']['consent']);

        $this->agent('snippets_delete', ['snippet' => $id])->assertOk();
        $this->assertTrue(Snippet::withTrashed()->findOrFail($id)->trashed());

        $this->agent('snippets_update', ['snippet' => $id, 'name' => 'Again'])->assertHasErrors(['is in the bin']);
    }

    #[Test]
    public function the_tags_of_a_whole_document_are_a_warning_and_the_save_goes_through(): void
    {
        $created = $this->content($this->agent('snippets_create', [
            'name' => 'Pasted page',
            'place' => 'body_end',
            'code' => '<html><body><script>1</script></body></html>',
        ]));

        $this->assertNotNull(Snippet::query()->find($created['snippet']['id']));
        $this->assertCount(1, $created['warnings']);
        $this->assertStringContainsString('<html>, <body>', $created['warnings'][0]);
    }

    #[Test]
    public function an_undeclared_place_is_refused_with_the_places_there_are(): void
    {
        config()->set('webx-snippets.places', ['before-footer' => 'Before the footer']);

        $this->agent('snippets_create', ['name' => 'Chat', 'place' => 'sidebar', 'code' => '<script></script>'])
            ->assertHasErrors(['Place [sidebar] is not declared', 'head, body_start, body_end, before-footer', 'snippets://places']);

        $this->assertSame(0, Snippet::withTrashed()->count());

        // A place of the config is as good as a built-in one.
        $this->agent('snippets_create', ['name' => 'Chat', 'place' => 'before-footer', 'code' => '<script></script>'])->assertOk();

        $snippet = $this->snippet('head', '<script></script>');
        $this->agent('snippets_update', ['snippet' => $snippet->id, 'place' => 'sidebar'])->assertHasErrors(['Place [sidebar] is not declared']);
        $this->assertSame('head', $snippet->refresh()->place);
    }

    #[Test]
    public function a_snippet_left_in_a_place_no_longer_declared_is_moved_before_it_is_enabled(): void
    {
        config()->set('webx-snippets.places', ['promo' => 'Promo']);
        $snippet = $this->snippet('promo', '<script></script>', ['enabled' => false]);
        config()->set('webx-snippets.places', []);

        $this->agent('snippets_update', ['snippet' => $snippet->id, 'enabled' => true])
            ->assertHasErrors(['in place [promo], which is no longer declared']);

        // Renamed where it stands — still off, so nothing would be printed nowhere.
        $this->agent('snippets_update', ['snippet' => $snippet->id, 'name' => 'Old promo'])->assertOk();

        $this->agent('snippets_update', ['snippet' => $snippet->id, 'place' => 'body_end', 'enabled' => true])->assertOk();
        $this->assertSame('body_end', $snippet->refresh()->place);
        $this->assertTrue($snippet->enabled);
    }

    #[Test]
    public function refusals_of_the_values_reach_the_agent_under_their_field(): void
    {
        $this->agent('snippets_create', ['name' => 'Chat', 'place' => 'head', 'code' => '<script></script>', 'consent' => 'analytics'])
            ->assertHasErrors(['consent:']);
        $this->agent('snippets_create', ['name' => 'Chat', 'place' => 'head', 'code' => '<script></script>', 'only_paths' => ['https://example.test/blog']])
            ->assertHasErrors(['only_paths:']);
        $this->agent('snippets_create', ['name' => 'Chat', 'place' => 'head', 'code' => str_repeat('a', Snippet::MAX_BYTES + 1)])
            ->assertHasErrors(['code:']);
        $this->agent('snippets_create', ['name' => 'Chat', 'place' => 'head', 'code' => '<script></script>', 'locales' => ['de']])
            ->assertHasErrors(['locales:']);
        $this->agent('snippets_create', ['name' => 'Chat', 'place' => 'head', 'code' => '<script></script>', 'author' => 'me'])
            ->assertHasErrors(['snippets_create has no argument [author]']);
        $this->agent('snippets_get', ['snippet' => 999])->assertHasErrors(['No snippet has the id [999]']);

        $this->assertSame(0, Snippet::withTrashed()->count());
    }

    #[Test]
    public function the_list_groups_by_place_and_says_what_is_printed_nowhere(): void
    {
        config()->set('webx-snippets.places', ['promo' => 'Promo']);
        $orphan = $this->snippet('promo', '<script>promo</script>');
        config()->set('webx-snippets.places', []);

        $first = $this->snippet('head', '<script>first</script>', ['name' => 'Counter']);
        $this->snippet('head', '<script>second</script>');

        $listed = $this->content($this->agent('snippets_list'));
        $byKey = array_column($listed['places'], null, 'key');

        $this->assertSame(['head', 'body_start', 'body_end', 'promo'], array_keys($byKey));
        $this->assertCount(2, $byKey['head']['snippets']);
        $this->assertSame([], $byKey['body_start']['snippets']);
        $this->assertFalse($byKey['promo']['declared']);
        $this->assertSame($orphan->id, $byKey['promo']['snippets'][0]['id']);
        // Switched on and on_stage (the helper's default): printed on this copy too.
        $this->assertFalse($byKey['head']['snippets'][0]['silent_here']);
        $this->assertFalse($listed['everything_printed']);
        // The list does not carry the code — snippets_get does.
        $this->assertArrayNotHasKey('code', $byKey['head']['snippets'][0]);

        $found = $this->content($this->agent('snippets_list', ['search' => 'counter']));
        $this->assertSame([$first->id], array_column(array_merge(...array_column($found['places'], 'snippets')), 'id'));
    }

    #[Test]
    public function reorder_takes_the_whole_place_and_only_that_place(): void
    {
        $a = $this->snippet('head', '<script>a</script>');
        $b = $this->snippet('head', '<script>b</script>');
        $c = $this->snippet('head', '<script>c</script>');
        $other = $this->snippet('body_end', '<script>other</script>');

        $this->agent('snippets_reorder', ['place' => 'head', 'snippets' => [$c->id, $a->id]])
            ->assertHasErrors(["must list every snippet of place [head], each once: missing {$b->id}"]);
        $this->agent('snippets_reorder', ['place' => 'head', 'snippets' => [$c->id, $a->id, $b->id, $other->id]])
            ->assertHasErrors(["Snippet {$other->id} is in place [body_end], not [head]"]);
        $this->agent('snippets_reorder', ['place' => 'nowhere', 'snippets' => [$a->id]])
            ->assertHasErrors(['Place [nowhere] is not declared']);

        $ordered = $this->content($this->agent('snippets_reorder', ['place' => 'head', 'snippets' => [$c->id, $a->id, $b->id]]));

        $this->assertSame([$c->id, $a->id, $b->id], array_column($ordered['places'][0]['snippets'], 'id'));
        $this->assertSame([$c->id, $a->id, $b->id], Snippet::query()->where('place', 'head')->orderBy('position')->pluck('id')->all());
    }

    #[Test]
    public function the_places_resource_lists_the_places_and_whether_the_views_print_them(): void
    {
        config()->set('webx-snippets.places', ['before-footer' => 'Before the footer', 'sidebar' => 'Sidebar']);
        $this->snippet('before-footer', '<script></script>');
        $this->app->make(Seen::class)->mark('head');

        config()->set('webx-snippets.places', ['before-footer' => 'Before the footer', 'sidebar' => 'Sidebar', 'gone' => 'Gone']);
        $this->snippet('gone', '<script></script>', ['enabled' => false]);
        config()->set('webx-snippets.places', ['before-footer' => 'Before the footer', 'sidebar' => 'Sidebar']);

        $places = $this->resource('snippets://places');
        $byKey = array_column($places['places'], null, 'key');

        $this->assertSame(['head', 'body_start', 'body_end', 'before-footer', 'sidebar'], array_keys($byKey));
        $this->assertTrue($byKey['head']['built_in']);
        $this->assertFalse($byKey['before-footer']['built_in']);
        $this->assertSame('Before the footer', $byKey['before-footer']['label']);
        // theme-default's layout prints the built-in ones; the site's fixture prints before-footer.
        $this->assertTrue($byKey['head']['in_views']);
        $this->assertTrue($byKey['before-footer']['in_views']);
        $this->assertFalse($byKey['sidebar']['in_views']);
        $this->assertNotNull($byKey['head']['last_seen_on_a_page']);
        $this->assertNull($byKey['body_start']['last_seen_on_a_page']);
        $this->assertSame(1, $byKey['before-footer']['snippets']);
        $this->assertSame(1, $byKey['before-footer']['enabled']);

        $this->assertSame([['key' => 'gone', 'snippets' => 1, 'printed' => false]], $places['undeclared']);
    }

    #[Test]
    public function the_catalogue_has_the_rules_and_every_snippet_in_short(): void
    {
        $snippet = $this->snippet('head', '<script>secret-ish</script>', ['name' => 'Counter']);

        $catalog = $this->resource('snippets://catalog');

        $this->assertArrayHasKey('consent', $catalog['rules']);
        $this->assertSame([['id' => $snippet->id, 'name' => 'Counter', 'place' => 'head', 'place_declared' => true, 'consent' => 'necessary', 'enabled' => true]], $catalog['snippets']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->admin(), 'cms')->tool($bound, $arguments);
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

    /**
     * @return array<string, mixed>
     */
    private function resource(string $uri): array
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                $read = ($resource->handler)();

                return is_array($read) ? $read : [];
            }
        }

        $this->fail("No resource {$uri}.");
    }
}
