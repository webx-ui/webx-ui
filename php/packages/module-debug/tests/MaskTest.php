<?php

declare(strict_types=1);

namespace WebxUi\Debug\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Debug\Support\Masker;

/**
 * What never goes into a report as it is (§10 of the debug bar spec), and the limits past which
 * a report keeps counting but stops writing down.
 */
final class MaskTest extends TestCase
{
    #[Test]
    public function a_password_and_a_session_never_reach_the_report(): void
    {
        $admin = $this->admin();
        $report = $this->reportOf($this->asAdmin($admin, '/secrets'), $admin);
        $packed = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('hunter2-plain', $packed);
        $this->assertStringNotContainsString('session-id-value', $packed);

        $users = $this->statement($report, 'from "cms_users"');
        $this->assertSame(['nobody@example.test', Masker::MASK], array_slice($users['bindings'], 0, 2));
        $this->assertStringContainsString("\"password\" = '".Masker::MASK."'", $users['inline']);

        $sessions = $this->statement($report, 'from "sessions"');
        $this->assertSame([Masker::MASK], $sessions['bindings']);
    }

    #[Test]
    public function headers_and_cookies_keep_their_names_and_lose_their_values(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin, 'cms')
            ->withCookie('wx_debug', '1')
            ->withHeader('Authorization', 'Bearer secret-bearer')
            ->withHeader('X-Custom', 'visible')
            ->get('/plain');

        $request = $this->reportOf($response, $admin)['request'];

        $this->assertSame(Masker::MASK, $request['request_headers']['authorization']);
        $this->assertSame('visible', $request['request_headers']['x-custom']);
        $this->assertContains('wx_debug', $request['cookies']);
        $this->assertStringNotContainsString('secret-bearer', json_encode($request, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function the_masker_reads_the_column_a_value_belongs_to(): void
    {
        $masker = $this->app->make(Masker::class);
        $m = Masker::MASK;

        $this->assertSame(['a@example.test', $m], $masker->bindings('select * from "users" where "email" = ? and "password" = ?', ['a@example.test', 'x']));
        $this->assertSame([$m, 5], $masker->bindings('update "users" set "remember_token" = ? where "id" = ?', ['t', 5]));
        $this->assertSame(['Name', $m, 'Two', $m], $masker->bindings('insert into "keys" ("name", "api_key") values (?, ?), (?, ?)', ['Name', 'k1', 'Two', 'k2']));
        $this->assertSame([$m, $m], $masker->bindings('select * from "t" where "t"."access_token" in (?, ?)', ['a', 'b']));
        $this->assertSame([$m, $m], $masker->bindings('select * from "oauth_clients" where "id" = ? and "name" = ?', [1, 'x']));
        $this->assertSame([$m], $masker->bindings('select * from "t" where "client_secret" = ?', ['s']));
        $this->assertSame([7], $masker->bindings('select * from "articles" where "id" = ?', [7]));

        $this->assertSame('https://api.example.test/v1?q=1&api_key='.$m, $masker->url('https://api.example.test/v1?q=1&api_key=abc'));
        $this->assertSame($m.'@example.test', $masker->email('person@example.test'));
        $this->assertSame(['user' => 'a', 'password' => $m, 'nested' => ['token' => $m]], $masker->keyed(['user' => 'a', 'password' => 'p', 'nested' => ['token' => 't']]));
    }

    #[Test]
    public function past_the_limit_statements_are_counted_not_written(): void
    {
        $this->seedArticles();
        $this->app['config']->set('webx-debug.limits.queries', 2);
        $admin = $this->admin();

        $report = $this->reportOf($this->asAdmin($admin, '/articles'), $admin);

        $this->assertTrue($report['truncated']);
        $this->assertSame(2, count(array_filter($report['queries'], static fn (array $query): bool => $query['kind'] === 'query')));
        $this->assertGreaterThanOrEqual(4, $report['summary']['queries']);
    }

    #[Test]
    public function a_long_value_is_cut(): void
    {
        $this->app['config']->set('webx-debug.limits.value', 20);
        $masker = $this->app->make(Masker::class);

        $this->assertSame(str_repeat('a', 20).'…', $masker->value(str_repeat('a', 50)));
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function statement(array $report, string $fragment): array
    {
        foreach ($report['queries'] as $query) {
            if (($query['kind'] ?? '') === 'query' && str_contains($query['sql'], $fragment)) {
                return $query;
            }
        }

        $this->fail("No statement with [{$fragment}].");
    }
}
