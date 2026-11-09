<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Localization\Contracts\PageInLanguage;

/**
 * ROUTING §18.6: with the language of the first visit on, the audit still reads every language
 * at its own address — its agent is a robot to the site — so the `hreflang` checks find what
 * they found before: nothing, on a site whose languages point at each other.
 */
final class FirstVisitCrawlTest extends TestCase
{
    private const string BASE = 'https://shop.example.com';

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-localization.locales', [
            ['code' => 'ru', 'default' => true],
            ['code' => 'uk'],
        ]);
        $app['config']->set('webx-localization.first_visit.locale', 'uk');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(PageInLanguage::class, new class implements PageInLanguage
        {
            public function url(HttpRequest $request, string $locale): ?string
            {
                $path = trim($request->path(), '/');

                return $path === $locale || str_starts_with($path, $locale.'/') ? null : 'https://shop.example.com/'.$locale.($path === '' ? '' : '/'.$path);
            }
        });

        $page = static fn (string $lang, string $self): string => '<!doctype html><html lang="'.$lang.'"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width"><title>A page of the shop of cameras with a long enough title</title>'
            .'<link rel="canonical" href="'.$self.'">'
            .'<link rel="alternate" hreflang="ru" href="https://shop.example.com/">'
            .'<link rel="alternate" hreflang="uk" href="https://shop.example.com/uk">'
            .'<link rel="alternate" hreflang="x-default" href="https://shop.example.com/">'
            .'</head><body><h1>Cameras</h1><a href="/">RU</a> <a href="/uk">UK</a></body></html>';

        Route::middleware('webx.locale')->group(function () use ($page): void {
            Route::get('/', fn () => response($page('ru', self::BASE.'/'), 200, ['Content-Type' => 'text/html; charset=utf-8']));
            Route::get('uk', fn () => response($page('uk', self::BASE.'/uk'), 200, ['Content-Type' => 'text/html; charset=utf-8']));
        });

        // The crawl reaches this application itself, with the headers it sends — and asks for HTML,
        // the way a search engine does: only its agent keeps it from being sent away.
        Http::fake(function (Request $request) {
            if (! str_starts_with($request->url(), self::BASE)) {
                return Http::response('', 404);
            }

            $server = ['HTTP_ACCEPT' => 'text/html,application/xhtml+xml'];

            foreach ($request->headers() as $name => $values) {
                $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = implode(', ', $values);
            }

            $response = $this->call($request->method(), $request->url(), [], [], [], $server);

            return Http::response((string) $response->getContent(), $response->getStatusCode(), $response->headers->all());
        });
    }

    #[Test]
    public function the_audit_reads_both_languages_at_their_own_addresses(): void
    {
        // A person is sent: the switch is on.
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/130.0', 'Accept' => 'text/html'])
            ->get(self::BASE.'/')
            ->assertRedirect(self::BASE.'/uk');

        $this->artisan('webx:audit:run')->assertSuccessful();

        // Both read, each at its own address and with a 200.
        $pages = AuditPage::query()->pluck('status', 'url')->all();
        ksort($pages);
        $this->assertSame([self::BASE.'/' => 200, self::BASE.'/uk' => 200], $pages);

        $checks = AuditIssue::query()->pluck('check')->all();

        $this->assertSame([], array_values(array_filter($checks, static fn (string $check): bool => str_starts_with($check, 'hreflang.'))));
        $this->assertNotContains('redirects.temporary', $checks);
    }
}
