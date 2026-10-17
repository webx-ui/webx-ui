<?php

declare(strict_types=1);

namespace WebxUi\Debug\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Debug\Access;
use WebxUi\Debug\Bar;

/**
 * The bar on an administrator's page (§3, §9, §11.1 of the debug bar spec): one tag before
 * `</body>`, the summary in it and the report kept for its maker.
 */
final class BarTest extends TestCase
{
    #[Test]
    public function an_administrator_with_the_permission_gets_one_tag_before_the_body_ends(): void
    {
        $admin = $this->admin();
        $response = $this->asAdmin($admin, '/plain');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertSame(1, substr_count($html, 'data-wx-debug='));
        $this->assertMatchesRegularExpression('#<script type="module" src="/cms/_debug/bar\.[a-f0-9]{12}\.js" data-wx-debug="[^"]+"></script>\n</body>#', $html);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

        $data = $this->tagData($response);
        $this->assertSame('/api/cms/debug/reports', $data['api']);
        $this->assertTrue($data['early']);
        $this->assertArrayHasKey('queries', $data['summary']);
        // The page carries the summary and the id, never the statements themselves.
        $this->assertArrayNotHasKey('queries', array_diff_key($data, ['summary' => true]));
    }

    #[Test]
    public function the_summary_on_the_page_is_the_summary_of_the_report(): void
    {
        $this->seedArticles();
        $admin = $this->admin();
        $response = $this->asAdmin($admin, '/articles');

        $report = $this->reportOf($response, $admin);
        $data = $this->tagData($response);

        $this->assertSame($report['summary'], $data['summary']);
        $this->assertSame($report['id'], $data['id']);
        $this->assertSame($admin->getKey(), $report['user_id']);
        $this->assertSame('/articles', $report['url']);
        $this->assertGreaterThanOrEqual(4, $report['summary']['queries']);
    }

    #[Test]
    public function the_links_to_the_panel_are_only_those_the_administrator_may_follow(): void
    {
        $super = $this->admin();
        $links = $this->reportOf($this->asAdmin($super, '/plain'), $super)['links'];

        // A page of the site's own code has no record to open; the regions are one prefix.
        $this->assertNull($links['page']);
        $this->assertSame('/cms/regions/', $links['region']);

        $reader = $this->admin(super: false, permissions: ['debug.view']);
        $links = $this->reportOf($this->asAdmin($reader, '/plain'), $reader)['links'];

        $this->assertNull($links['region'], 'Without blocks.regions the bar offers no region to open.');
    }

    #[Test]
    public function an_n_plus_one_is_grouped_by_shape_with_where_it_came_from(): void
    {
        $this->seedArticles();
        $admin = $this->admin();
        $report = $this->reportOf($this->asAdmin($admin, '/articles'), $admin);

        $covers = array_values(array_filter($report['repeated'], static fn (array $entry): bool => str_contains($entry['shape'], 'demo_covers')));

        $this->assertCount(1, $covers);
        $this->assertSame(3, $covers[0]['count']);
        $this->assertTrue($covers[0]['likely']);
        $this->assertSame(1, $report['summary']['repeated']);

        $site = (string) array_key_first($covers[0]['sites']);
        $this->assertStringContainsString('TestCase.php:', $site, 'The statement is named by the line of the site that issued it, not by the query builder.');

        $statements = array_values(array_filter($report['queries'], static fn (array $query): bool => ($query['kind'] ?? '') === 'query' && str_contains($query['sql'], 'demo_covers')));
        $this->assertSame([1], $statements[0]['bindings']);
        $this->assertStringContainsString('= 1', $statements[0]['inline']);
    }

    #[Test]
    public function the_timeline_starts_at_the_first_middleware_when_the_cookie_says_so(): void
    {
        $admin = $this->admin();
        $report = $this->reportOf($this->asAdmin($admin, '/plain'), $admin);

        $kinds = array_column($report['timeline']['spans'], 'kind');

        $this->assertContains('boot', $kinds);
        $this->assertContains('middleware', $kinds);
        $this->assertContains('handler', $kinds);
        $this->assertTrue($report['early']);
    }

    #[Test]
    public function without_the_cookie_collecting_starts_late_and_the_cookie_is_set(): void
    {
        $admin = $this->admin();
        $response = $this->asAdmin($admin, '/plain', marked: false);

        $data = $this->tagData($response);
        $this->assertFalse($data['early']);
        $response->assertCookie(Access::COOKIE);

        $kinds = array_column($this->reportOf($response, $admin)['timeline']['spans'], 'kind');
        $this->assertNotContains('boot', $kinds);
    }

    #[Test]
    public function a_view_and_the_statement_it_ran_are_named_by_the_blade_source(): void
    {
        $admin = $this->admin();
        $report = $this->reportOf($this->asAdmin($admin, '/view'), $admin);

        $this->assertStringEndsWith('page.blade.php', (string) $report['request']['view']);
        $this->assertStringEndsWith('page.blade.php', (string) $report['views'][0]['name']);

        $count = array_values(array_filter($report['queries'], static fn (array $query): bool => ($query['kind'] ?? '') === 'query' && str_contains($query['sql'], 'count(*)')));
        $this->assertNotSame([], $count);
        $this->assertStringContainsString('page.blade.php', (string) $count[0]['site']);
        $this->assertStringNotContainsString('storage/framework/views', (string) $count[0]['site']);
    }

    #[Test]
    public function blocks_and_regions_are_in_the_tree_and_on_the_timeline(): void
    {
        $this->publish('hero', '<h1>{{ $title }}</h1>');
        $admin = $this->admin();
        $response = $this->asAdmin($admin, '/blocks');

        $response->assertSee('<h1>Welcome</h1>', false);
        $report = $this->reportOf($response, $admin);

        $hero = array_values(array_filter($report['blocks'], static fn (array $node): bool => $node['kind'] === 'block'));
        $this->assertCount(1, $hero);
        $this->assertSame('hero', $hero[0]['type']);
        $this->assertSame('k1', $hero[0]['key']);
        $this->assertSame('site', $hero[0]['layer']);
        $this->assertNull($hero[0]['error']);

        $regions = array_values(array_filter($report['blocks'], static fn (array $node): bool => $node['kind'] === 'region'));
        $this->assertSame('undeclared', $regions[0]['name']);
        $this->assertSame('fallback', $regions[0]['source']);

        $labels = array_column($report['timeline']['spans'], 'label');
        $this->assertContains('block:k1', $labels);
        $this->assertContains('region:undeclared', $labels);
    }

    #[Test]
    public function the_versioned_cache_is_counted_by_group_and_source(): void
    {
        $admin = $this->admin();
        $report = $this->reportOf($this->asAdmin($admin, '/cached'), $admin);

        $demo = array_values(array_filter($report['cache']['groups'], static fn (array $group): bool => $group['group'] === 'demo'));

        $this->assertCount(1, $demo);
        $this->assertSame(2, $demo[0]['reads']);
        $this->assertSame(1, $demo[0]['miss']);
        $this->assertSame(1, $demo[0]['memo']);
        $this->assertSame(['demo.list'], $demo[0]['entries']);
        $this->assertIsInt($demo[0]['version']);
    }

    #[Test]
    public function the_script_is_served_under_its_hash_before_the_panels_shell(): void
    {
        $bar = $this->app->make(Bar::class);

        $this->get('/cms/_debug/bar.'.$bar->hash().'.js')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8')
            ->assertSee('data-wx-debug-root', false);

        // The tokens are written in, rewritten for the shadow host.
        $this->assertStringContainsString(':host', $bar->body());
        $this->assertStringNotContainsString("'' // tokens: written in when served", $bar->body());
    }
}
