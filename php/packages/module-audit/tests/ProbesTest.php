<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Probes\Certificate;
use WebxUi\Audit\Probes\CertificateReader;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditRun;

/**
 * The host as the probes see it: a site set up the way a new server comes out of the box, and
 * the same site after an admin has done the work — every host check speaks about the first and
 * keeps quiet about the second.
 */
final class ProbesTest extends TestCase
{
    private const HOME = '<html><body><a href="/about">About</a><link rel="stylesheet" href="/css/app.css">'
        .'<img src="https://shop.example.com/img/a.png" alt=""></body></html>';

    #[Test]
    public function a_server_out_of_the_box_is_told_everything(): void
    {
        $this->fakeSite([
            'https://shop.example.com/' => [200, self::HOME, ['Content-Type' => 'text/html', 'Server' => 'nginx/1.25.3', 'X-Powered-By' => 'PHP/8.4.1']],
            'http://shop.example.com/' => [302, '', ['Location' => 'http://www.shop.example.com/']],
            'http://www.shop.example.com/' => [301, '', ['Location' => 'https://shop.example.com/']],
            'https://www.shop.example.com/' => [200, self::HOME, ['Content-Type' => 'text/html']],
            'https://shop.example.com/index.php' => [200, self::HOME],
            'random' => [200, 'Home'],
            'https://shop.example.com/about' => [200, 'About'],
            'https://shop.example.com//about' => [200, 'About'],
            'https://shop.example.com/about/' => [200, 'About'],
            'https://shop.example.com/About' => [200, 'About'],
            'https://shop.example.com/css/app.css' => [200, 'body{}'],
            'https://shop.example.com/img/a.png' => [200, 'png', ['Cache-Control' => 'max-age=60']],
        ]);
        $this->certificate(5, matches: false);

        $this->artisan('webx:audit:run', ['--quick' => true])->assertSuccessful();

        $found = AuditIssue::query()->pluck('severity', 'check')->all();

        $this->assertSame('error', $found['host.mirror'] ?? null);
        $this->assertSame('warning', $found['host.https'] ?? null, 'Through a chain, it still gets there.');
        $this->assertSame('error', $found['host.tls'] ?? null);
        $this->assertSame('notice', $found['host.hsts'] ?? null);
        $this->assertSame('error', $found['host.index_files'] ?? null);
        $this->assertSame('warning', $found['host.slashes'] ?? null);
        $this->assertSame('warning', $found['host.trailing_slash'] ?? null);
        $this->assertSame('warning', $found['host.case'] ?? null);
        $this->assertSame('error', $found['host.soft_404'] ?? null);
        $this->assertSame('warning', $found['host.compression'] ?? null);
        $this->assertSame('notice', $found['host.security_headers'] ?? null);
        $this->assertSame('notice', $found['host.server_leak'] ?? null);
        $this->assertSame('warning', $found['host.static_cache'] ?? null);

        $chain = AuditIssue::query()->where('check', 'host.https')->firstOrFail();
        $this->assertSame([302, 301], array_column($chain->details['table']['rows'] ?? [], 'status'));

        $tls = AuditIssue::query()->where('check', 'host.tls')->pluck('fingerprint');
        $this->assertCount(2, $tls, 'Another host and a near expiry are two findings, told apart by their key.');
    }

    #[Test]
    public function a_server_that_was_set_up_is_left_alone(): void
    {
        $clean = ['Content-Type' => 'text/html; charset=utf-8', 'Content-Encoding' => 'gzip', 'Server' => 'nginx',
            'Strict-Transport-Security' => 'max-age=31536000', 'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin', 'X-Frame-Options' => 'SAMEORIGIN'];
        $cached = ['Cache-Control' => 'public, max-age=31536000, immutable'];

        $this->fakeSite([
            'https://shop.example.com/' => [200, self::HOME, $clean],
            'http://shop.example.com/' => [301, '', ['Location' => 'https://shop.example.com/']],
            'https://www.shop.example.com/' => [301, '', ['Location' => 'https://shop.example.com/']],
            'random' => [404, '<a href="/">Home</a>'],
            'https://shop.example.com/about' => [200, 'About'],
            'https://shop.example.com//about' => [301, '', ['Location' => 'https://shop.example.com/about']],
            'https://shop.example.com/about/' => [301, '', ['Location' => 'https://shop.example.com/about']],
            'https://shop.example.com/css/app.css' => [200, 'body{}', $cached],
            'https://shop.example.com/img/a.png' => [200, 'png', $cached],
        ]);
        $this->certificate(90);

        $this->artisan('webx:audit:run', ['--quick' => true, '--fail-on' => 'notice'])->assertSuccessful();

        $this->assertSame([], AuditIssue::query()->pluck('check')->all());

        $run = AuditRun::query()->sole();
        $this->assertSame(AuditRun::DONE, $run->status);
        $this->assertSame(100, $run->counts['health'] ?? null);
        $this->assertSame('/about', $run->probes['inner_path'] ?? null);
    }

    #[Test]
    public function a_second_name_that_does_not_resolve_is_only_a_notice(): void
    {
        $this->fakeSite([
            'https://shop.example.com/' => [200, '<html></html>', ['Content-Type' => 'text/html']],
            'https://www.shop.example.com/' => null,
            'random' => [404, ''],
        ]);

        $this->artisan('webx:audit:run', ['--quick' => true])->assertSuccessful();

        $this->assertSame('notice', AuditIssue::query()->where('check', 'host.mirror')->value('severity'));
    }

    /**
     * @param  array<string, array{0: int, 1: string, 2?: array<string, string>}|null>  $site  null: nothing answers
     */
    private function fakeSite(array $site): void
    {
        Http::fake(static function (Request $request) use ($site) {
            $url = $request->url();
            $key = str_contains($url, '/webx-audit-') ? 'random' : $url;

            if (array_key_exists($key, $site) && $site[$key] === null) {
                return Http::failedConnection('Could not resolve host');
            }

            [$status, $body, $headers] = ($site[$key] ?? [404, 'Not found', []]) + [2 => []];

            return Http::response($body, $status, $headers);
        });
    }

    private function certificate(int $days, bool $matches = true): void
    {
        $reader = $this->app->make(CertificateReader::class);
        $this->assertInstanceOf(FakeCertificateReader::class, $reader);
        $reader->certificate = new Certificate(Carbon::now()->addDays($days)->getTimestamp(), $matches, true, 'Test CA');
    }
}
