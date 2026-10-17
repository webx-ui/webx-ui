<?php

declare(strict_types=1);

namespace WebxUi\Debug\Tests;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Blocks\Events\BlockRendering;
use WebxUi\Blocks\Events\RegionRendering;
use WebxUi\Cache\Events\GroupRead;
use WebxUi\Debug\Access;
use WebxUi\Debug\Collector;
use WebxUi\Debug\Reports;
use WebxUi\Routing\Events\Resolving;

/**
 * Everybody who must get the page as if the package were not there (§3 and §14 of the debug bar
 * spec): a visitor, one with a forged cookie, an administrator without the permission or
 * switched off, the bar switched off, and every response that is not a page.
 */
final class NobodyElseTest extends TestCase
{
    private int $statements = 0;

    #[Test]
    public function a_visitor_gets_the_page_as_it_is_with_the_bar_switched_off(): void
    {
        $this->seedArticles();
        DB::listen(function (): void {
            $this->statements++;
        });
        $events = [QueryExecuted::class, BlockRendering::class, RegionRendering::class, GroupRead::class, Resolving::class];
        $before = array_map($this->listeners(...), $events);

        [$on, $onQueries] = $this->counted(fn (): TestResponse => $this->get('/articles'));

        $this->app['config']->set('webx-debug.enabled', false);
        [$off, $offQueries] = $this->counted(fn (): TestResponse => $this->get('/articles'));

        $this->assertSame($off->getContent(), $on->getContent());
        $this->assertSame($this->headers($off), $this->headers($on));
        $this->assertSame($offQueries, $onQueries, 'A visitor costs no statement more with the bar installed.');

        // Nothing was subscribed: our points of measure are not even created for a visitor.
        $this->assertSame($before, array_map($this->listeners(...), $events));
        $this->assertFalse($this->app->make(Collector::class)->active);
    }

    #[Test]
    public function a_forged_cookie_starts_collecting_and_keeps_nothing(): void
    {
        $plain = $this->get('/plain');
        $forged = $this->withCookie(Access::COOKIE, '1')->get('/plain');

        $this->assertSame($plain->getContent(), $forged->getContent());
        $this->assertSame($this->headers($plain), $this->headers($forged));
        $this->assertStringNotContainsString('data-wx-debug', (string) $forged->getContent());
        $this->assertFalse($this->app->make(Collector::class)->active);
    }

    #[Test]
    public function an_administrator_without_the_permission_gets_nothing_and_loses_the_cookie(): void
    {
        $editor = $this->admin(super: false, permissions: ['pages.manage']);
        $response = $this->asAdmin($editor, '/plain');

        $this->assertStringNotContainsString('data-wx-debug', (string) $response->getContent());
        $this->assertNotSame('private, no-store', $response->headers->get('Cache-Control'));

        $cookie = collect($response->headers->getCookies())->first(static fn ($one): bool => $one->getName() === Access::COOKIE);
        $this->assertNotNull($cookie, 'The cookie of somebody who lost the permission is taken back.');
        $this->assertTrue($cookie->isCleared());
    }

    #[Test]
    public function a_role_with_the_permission_is_enough(): void
    {
        $developer = $this->admin(super: false, permissions: ['debug.view']);

        $this->assertStringContainsString('data-wx-debug', (string) $this->asAdmin($developer, '/plain')->getContent());
    }

    #[Test]
    public function a_switched_off_administrator_gets_nothing(): void
    {
        $off = $this->admin(active: false);

        $this->assertStringNotContainsString('data-wx-debug', (string) $this->asAdmin($off, '/plain')->getContent());
    }

    #[Test]
    public function the_switch_in_the_config_turns_it_off(): void
    {
        $this->app['config']->set('webx-debug.enabled', false);

        $this->assertStringNotContainsString('data-wx-debug', (string) $this->asAdmin($this->admin(), '/plain')->getContent());
        $this->assertFalse($this->app->make(Collector::class)->active);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notPages(): iterable
    {
        yield 'json' => ['/json'];
        yield 'redirect' => ['/redirect'];
        yield 'not found' => ['/missing'];
        yield 'no body' => ['/no-body'];
        yield 'preview' => ['/_preview/thing'];
        yield 'outside the web group' => ['/bare'];
    }

    #[Test]
    #[DataProvider('notPages')]
    public function a_response_that_is_not_a_page_is_left_alone(string $path): void
    {
        $admin = $this->admin();
        $response = $this->asAdmin($admin, $path);

        $this->assertStringNotContainsString('data-wx-debug', (string) $response->getContent());
        $this->assertSame([], $this->app->make(Reports::class)->recent($admin->getKey()));
    }

    #[Test]
    public function a_framed_page_is_left_alone(): void
    {
        $response = $this->actingAs($this->admin(), 'cms')
            ->withCookie(Access::COOKIE, '1')
            ->withHeader('Sec-Fetch-Dest', 'iframe')
            ->get('/plain');

        $this->assertStringNotContainsString('data-wx-debug', (string) $response->getContent());
    }

    /**
     * @param  callable(): TestResponse<Response>  $request
     * @return array{TestResponse<Response>, int}
     */
    private function counted(callable $request): array
    {
        $before = $this->statements;
        $response = $request();

        return [$response, $this->statements - $before];
    }

    /**
     * The headers a visitor gets, less the ones that differ on every response anyway.
     *
     * @param  TestResponse<Response>  $response
     * @return array<string, list<string|null>>
     */
    private function headers(TestResponse $response): array
    {
        $headers = $response->headers->all();
        unset($headers['date'], $headers['set-cookie']);
        ksort($headers);

        return $headers;
    }
}
