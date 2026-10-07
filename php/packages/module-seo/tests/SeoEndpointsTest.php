<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\SeoModule;
use WebxUi\Seo\Panel\SeoRules;

final class SeoEndpointsTest extends TestCase
{
    #[Test]
    public function it_lists_the_rules_in_the_order_the_site_tries_them(): void
    {
        SeoUrl::query()->create(['match_type' => 'regex', 'pattern' => '#^/a#']);
        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/a']);
        SeoUrl::query()->create(['match_type' => 'mask', 'pattern' => '/a/*']);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api('urls'));

        $response->assertOk();
        $this->assertSame(['exact', 'mask', 'regex'], array_column($response->json('data'), 'match_type'));
    }

    #[Test]
    public function it_writes_a_rule_with_every_language_of_every_field(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api('urls'), [
            'match_type' => 'exact',
            'pattern' => '/about/',
            'title' => ['ru' => 'О нас', 'uk' => 'Про нас', 'xx' => 'Nowhere'],
            'robots' => 'noindex, nofollow',
        ]);

        $response->assertCreated();

        $rule = SeoUrl::query()->firstOrFail();

        // Normalised on the way in, so a rule written with a trailing slash matches an address
        // arriving without one.
        $this->assertSame('/about', $rule->pattern);
        // A language the site does not publish in is not kept: it would sit in the column for
        // ever, invisible to the panel that can only show the ones it knows.
        $this->assertSame(['ru' => 'О нас', 'uk' => 'Про нас'], $rule->getTranslations('title'));
    }

    #[Test]
    public function a_regular_expression_that_will_not_compile_is_refused_under_its_field(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('urls'), ['match_type' => 'regex', 'pattern' => '#^/catalog/(#'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pattern');
    }

    #[Test]
    public function reading_is_not_writing(): void
    {
        $reader = $this->editor(['seo.view']);

        $this->actingAs($reader, 'cms')->getJson($this->api('urls'))->assertOk();
        $this->actingAs($reader, 'cms')
            ->postJson($this->api('urls'), ['match_type' => 'exact', 'pattern' => '/about'])
            ->assertForbidden();
    }

    #[Test]
    public function a_stranger_is_not_let_in(): void
    {
        $this->getJson($this->api('urls'))->assertUnauthorized();
    }

    #[Test]
    public function test_url_says_which_rule_matched_and_what_the_page_ends_up_with(): void
    {
        SeoUrl::query()->create(['match_type' => 'mask', 'pattern' => '/catalog/*', 'title' => ['ru' => 'Каталог']]);
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/old', 'target' => '/catalog/shoes']);

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('test-url'), ['url' => '/catalog/shoes/', 'locale' => 'ru']);

        $response->assertOk();
        $this->assertSame('/catalog/shoes', $response->json('data.url'));
        $this->assertSame('/catalog/*', $response->json('data.matched.pattern'));
        $this->assertSame('Каталог', $response->json('data.seo.title'));
        // Only the sources that had something to say: with no settings written, the defaults
        // answer null and stay out of the chain rather than filling it with empty rows.
        $this->assertSame(['UrlRuleSource'], array_column($response->json('data.chain'), 'source'));
        $this->assertNull($response->json('data.redirect'));
    }

    #[Test]
    public function test_url_says_when_an_address_is_redirected_before_any_of_that(): void
    {
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/old', 'target' => '/new']);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('test-url'), ['url' => '/old'])
            ->assertOk()
            ->assertJsonPath('data.redirect.target', '/new')
            ->assertJsonPath('data.redirect.leads_to', '/new');
    }

    #[Test]
    public function the_agent_hears_about_an_exact_redirect_as_the_panel_does(): void
    {
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/about-us', 'target' => '/about', 'status' => 302]);

        $this->askAgent('/about-us')->assertStructuredContent(static function (AssertableJson $json): void {
            $json->where('redirect.pattern', '/about-us')
                ->where('redirect.status', 302)
                ->where('redirect.leads_to', '/about')
                ->has('route')
                ->etc();
        });
    }

    #[Test]
    public function the_agent_hears_where_a_mask_redirect_sends_this_very_address(): void
    {
        SeoRedirect::query()->create(['match_type' => 'mask', 'pattern' => '/catalog/*', 'target' => '/shop/$1']);

        $this->askAgent('/catalog/shoes')->assertStructuredContent(static function (AssertableJson $json): void {
            $json->where('redirect.target', '/shop/$1')->where('redirect.leads_to', '/shop/shoes')->etc();
        });
    }

    #[Test]
    public function the_agent_hears_no_redirect_where_there_is_none(): void
    {
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/old', 'target' => '/new']);

        $this->askAgent('/elsewhere')->assertStructuredContent(static function (AssertableJson $json): void {
            $json->where('redirect', null)->where('route', null)->where('url', '/elsewhere')->etc();
        });
    }

    private function askAgent(string $url): TestResponse
    {
        $registry = $this->app->make(ToolRegistry::class);
        $response = WebxServer::actingAs($this->editor(['seo.view']), 'cms')
            ->tool(new RegistryTool($registry->tool('seo_test_url')), ['url' => $url]);

        $this->assertInstanceOf(TestResponse::class, $response);

        return $response;
    }

    #[Test]
    public function it_writes_a_redirect_and_refuses_one_that_points_at_itself(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson($this->api('redirects'), [
            'match_type' => 'exact',
            'pattern' => '/old',
            'target' => '/new',
            'status' => 302,
        ])->assertCreated()->assertJsonPath('data.status', 302)->assertJsonPath('data.is_loop', false);

        // Saved, it only looked fine: the middleware steps over it and nothing ever moves.
        $this->postJson($this->api('redirects'), [
            'match_type' => 'exact',
            'pattern' => '/loop/',
            'target' => '/loop',
        ])->assertUnprocessable()->assertJsonValidationErrors('target');

        $this->postJson($this->api('redirects'), [
            'match_type' => 'regex',
            'pattern' => '([a-z',
            'target' => '/new',
        ])->assertUnprocessable()->assertJsonValidationErrors('pattern');
    }

    #[Test]
    public function an_agent_is_refused_a_redirect_to_itself_and_a_broken_pattern(): void
    {
        $set = $this->tool('redirects_set');

        $loop = $set(['pattern' => '/qa-loop', 'target' => '/qa-loop/']);
        $this->assertFalse($loop['ok']);
        $this->assertSame(__('webx-seo::errors.self-loop'), $loop['reason']);

        $broken = $set(['match_type' => 'regex', 'pattern' => '([a-z', 'target' => '/new']);
        $this->assertFalse($broken['ok']);
        $this->assertSame(__('webx-seo::errors.bad-regex'), $broken['reason']);

        $this->assertSame(0, SeoRedirect::query()->count());
        $this->assertTrue($set(['match_type' => 'regex', 'pattern' => '#^/old/(\d+)$#', 'target' => '/new/$1'])['ok']);
    }

    #[Test]
    public function the_settings_screen_grew_a_seo_tab(): void
    {
        $response = $this->actingAs($this->editor(['settings.view', 'seo.view']), 'cms')
            ->getJson('/api/cms/screens/settings.index');

        $response->assertOk();

        $ids = $this->ids($response->json('data.root') ?? []);

        $this->assertContains('seo', $ids);
        // The ids are a contract: a project writes its own patch against them.
        $this->assertContains('title-template', $ids);
        $this->assertContains('org-socials', $ids);
    }

    private function api(string $path): string
    {
        return '/api/cms/seo/'.$path;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<string>
     */
    private function ids(array $nodes): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            $ids[] = (string) ($node['id'] ?? '');
            $ids = [...$ids, ...$this->ids(is_array($node['children'] ?? null) ? $node['children'] : [])];
        }

        return $ids;
    }

    #[Test]
    public function an_agent_deletes_a_rule_and_the_site_stops_matching_it(): void
    {
        $rule = SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/about', 'title' => ['ru' => 'О нас']]);
        // Compiled before the delete, so the test shows the cache being dropped, not never filled.
        $this->assertCount(1, app(SeoRules::class)->urls());

        $preview = ($this->tool('urls_delete'))(['id' => $rule->id, 'dry_run' => true]);

        $this->assertFalse($preview['applied']);
        $this->assertSame('/about', $preview['would_delete']['pattern']);
        $this->assertSame('exact', $preview['would_delete']['match_type']);
        $this->assertTrue(SeoUrl::query()->whereKey($rule->id)->exists());

        $done = ($this->tool('urls_delete'))(['id' => $rule->id]);

        $this->assertTrue($done['applied']);
        $this->assertFalse(SeoUrl::query()->whereKey($rule->id)->exists());
        $this->assertSame([], app(SeoRules::class)->urls());
        $this->assertFalse(($this->tool('urls_delete'))(['id' => $rule->id])['ok']);
    }

    #[Test]
    public function an_agent_deletes_a_redirect_and_the_old_address_stops_moving(): void
    {
        $redirect = SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/old', 'target' => '/new']);
        $this->assertCount(1, app(SeoRules::class)->redirects());

        $preview = ($this->tool('redirects_delete'))(['id' => $redirect->id, 'dry_run' => true]);

        $this->assertFalse($preview['applied']);
        $this->assertSame(['/old', '/new'], [$preview['would_delete']['from'], $preview['would_delete']['to']]);
        $this->assertTrue(SeoRedirect::query()->whereKey($redirect->id)->exists());

        $done = ($this->tool('redirects_delete'))(['id' => $redirect->id]);

        $this->assertTrue($done['applied']);
        $this->assertSame([], app(SeoRules::class)->redirects());
        $this->assertFalse(($this->tool('redirects_delete'))(['id' => $redirect->id])['ok']);
    }

    private function tool(string $name): callable
    {
        foreach (app(SeoModule::class)->mcpTools() as $tool) {
            if ($tool->name === $name) {
                return $tool->handler;
            }
        }

        $this->fail("No tool {$name}.");
    }
}
