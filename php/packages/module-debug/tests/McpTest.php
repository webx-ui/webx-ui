<?php

declare(strict_types=1);

namespace WebxUi\Debug\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Debug\Access;
use WebxUi\Debug\Mcp\PageFetcher;
use WebxUi\Debug\Reports;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * `debug_page` (§13 of the debug bar spec): the same report as the bar's, for a path of this
 * site, asked for as a visitor would ask with a one-time token instead of a session.
 */
final class McpTest extends TestCase
{
    /** @var list<array{url: string, headers: array<string, string>}> */
    private array $fetched = [];

    /** What the page last answered the agent's request with. */
    private ?string $lastBody = null;

    #[Test]
    public function the_tool_reads_behind_its_permission(): void
    {
        $tools = $this->app->make(ToolRegistry::class)->toolsOf('debug');

        $this->assertCount(1, $tools);
        $this->assertSame('debug_page', $tools[0]->fullName());
        $this->assertSame('debug:read', $tools[0]->scope());
        $this->assertSame(['debug.view'], $tools[0]->permissions());
        $this->assertFalse($tools[0]->tool->mutating);
        $this->assertArrayNotHasKey('dry_run', $tools[0]->tool->inputSchema['properties']);
    }

    #[Test]
    public function a_page_comes_back_with_its_statements_and_repeats(): void
    {
        $this->seedArticles();
        $admin = $this->admin();

        $report = $this->content($this->agent(['url' => '/articles'], $admin));

        $this->assertSame(200, $report['status']);
        $this->assertSame('/articles', $report['url']);
        $this->assertGreaterThanOrEqual(4, $report['summary']['queries']);
        $this->assertSame(1, $report['summary']['repeated']);
        $this->assertSame(3, $report['repeated'][0]['count']);
        $this->assertArrayHasKey('timeline', $report);
        $this->assertArrayNotHasKey('views', $report);
        $this->assertArrayNotHasKey('log', $report['other']);

        // The site was asked by its own address, with a token rather than a session.
        $this->assertSame('https://example.test/articles', $this->fetched[0]['url']);
        $this->assertArrayHasKey(Access::HEADER, $this->fetched[0]['headers']);

        // The report is kept like the bar's, for the same person to open in the bar.
        $this->assertNotNull($this->app->make(Reports::class)->find($report['id'], $admin->getKey()));
    }

    #[Test]
    public function the_page_itself_is_a_visitors(): void
    {
        $this->agent(['url' => '/plain']);

        $this->assertSame('<html><body><h1>Hello</h1></body></html>', $this->lastBody);
    }

    #[Test]
    public function only_the_sections_asked_for_come_back(): void
    {
        $report = $this->content($this->agent(['url' => '/view', 'include' => ['views']]));

        $this->assertStringEndsWith('page.blade.php', (string) $report['views'][0]['name']);
        $this->assertArrayNotHasKey('queries', $report);
        $this->assertArrayHasKey('summary', $report);
    }

    #[Test]
    public function another_host_and_a_relative_path_are_refused(): void
    {
        foreach (['https://elsewhere.test/', '//elsewhere.test/', 'blog', ''] as $url) {
            $this->agent(['url' => $url])->assertHasErrors(['url is a path of this site']);
        }

        $this->assertSame([], $this->fetched);
    }

    #[Test]
    public function a_response_that_is_not_a_page_of_the_site_makes_no_report(): void
    {
        $this->agent(['url' => '/api/cms/debug/reports'])->assertHasErrors(['no report was made']);
    }

    #[Test]
    public function a_token_is_good_once(): void
    {
        $admin = $this->admin();
        $issued = $this->app->make(Access::class)->issue($admin);
        $reports = $this->app->make(Reports::class);

        $this->withHeaders([Access::HEADER => $issued['token']])->get('/plain')->assertOk();
        $this->assertNotNull($reports->find($issued['id'], $admin->getKey()));

        cache()->forget('webx:debug:'.$issued['id']);

        $this->withHeaders([Access::HEADER => $issued['token']])->get('/plain')->assertOk();
        $this->assertNull($reports->find($issued['id'], $admin->getKey()), 'A token shown a second time collects nothing that is kept.');
    }

    #[Test]
    public function a_forged_token_keeps_nothing(): void
    {
        $response = $this->withHeaders([Access::HEADER => 'forged'])->get('/plain');

        $this->assertSame('<html><body><h1>Hello</h1></body></html>', $response->getContent());
    }

    #[Test]
    public function an_editor_without_the_permission_is_refused(): void
    {
        $this->agent(['url' => '/plain'], $this->admin(super: false, permissions: ['pages.manage']))
            ->assertHasErrors(['[debug.view]']);

        $this->assertSame([], $this->fetched);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(array $arguments, ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('debug_page'));
        // The page asked for from inside the application, through the whole kernel, as the site
        // would answer the real request — with the token header and nothing else of ours.
        $this->app->instance(PageFetcher::class, new class($this) implements PageFetcher
        {
            public function __construct(private readonly McpTest $test) {}

            public function fetch(string $url, array $headers): array
            {
                return $this->test->answerKeeping($url, $headers);
            }
        });

        return WebxServer::actingAs($as ?? $this->admin(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{status: int|null, ms: int, error: string|null}
     */
    public function answerKeeping(string $url, array $headers): array
    {
        $this->fetched[] = ['url' => $url, 'headers' => $headers];
        $response = $this->withHeaders($headers)->get($url);
        $this->lastBody = (string) $response->getContent();

        return ['status' => $response->getStatusCode(), 'ms' => 1, 'error' => null];
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
