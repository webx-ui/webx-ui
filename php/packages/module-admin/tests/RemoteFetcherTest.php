<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use WebxUi\Admin\Remote\FetchRefused;
use WebxUi\Admin\Remote\HostResolver;
use WebxUi\Admin\Remote\RemoteFetcher;
use WebxUi\Admin\Remote\Target;
use WebxUi\Admin\Testing\FakeHostResolver;

/**
 * The one way a module fetches an address somebody typed: http(s) only, public addresses only —
 * after the name is resolved and on every redirect — and never more than the caller takes.
 */
final class RemoteFetcherTest extends TestCase
{
    private FakeHostResolver $dns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostResolver::class, $this->dns = new FakeHostResolver);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function privateAddresses(): iterable
    {
        yield 'loopback' => ['127.0.0.1'];
        yield 'loopback elsewhere in the block' => ['127.8.8.8'];
        yield 'private 10' => ['10.0.0.5'];
        yield 'private 172' => ['172.16.4.4'];
        yield 'private 192' => ['192.168.1.1'];
        yield 'metadata' => ['169.254.169.254'];
        yield 'shared' => ['100.64.0.1'];
        yield 'documentation' => ['203.0.113.5'];
        yield 'nothing' => ['0.0.0.0'];
        yield 'v6 loopback' => ['::1'];
        yield 'v6 unspecified' => ['::'];
        yield 'v6 unique local' => ['fd00::1'];
        yield 'v6 link-local' => ['fe80::1'];
        yield 'v4 mapped' => ['::ffff:127.0.0.1'];
        yield 'v4 mapped metadata' => ['::ffff:169.254.169.254'];
        yield 'nat64 of private' => ['64:ff9b::a00:1'];
        yield '6to4 of loopback' => ['2002:7f00:1::1'];
    }

    #[Test]
    #[DataProvider('privateAddresses')]
    public function a_private_address_is_not_public(string $address): void
    {
        $this->assertFalse(RemoteFetcher::isPublic($address));
    }

    #[Test]
    public function a_public_address_is_public(): void
    {
        $this->assertTrue(RemoteFetcher::isPublic(FakeHostResolver::PUBLIC));
        $this->assertTrue(RemoteFetcher::isPublic('2606:2800:21f:cb07:6820:80da:af6b:8b2c'));
        $this->assertTrue(RemoteFetcher::isPublic('::ffff:'.FakeHostResolver::PUBLIC));
    }

    #[Test]
    #[DataProvider('privateAddresses')]
    public function an_internal_address_typed_as_it_is_is_refused(string $address): void
    {
        Http::fake();
        $host = str_contains($address, ':') ? "[{$address}]" : $address;

        $this->assertRefused("http://{$host}/latest/meta-data/", FetchRefused::PRIVATE);

        Http::assertNothingSent();
    }

    #[Test]
    public function a_name_that_resolves_inside_the_network_is_refused(): void
    {
        Http::fake();
        $this->dns->answer('intranet.example.com', ['10.0.0.7']);
        // One private address among public ones is enough: the connection could take that one.
        $this->dns->answer('rebind.example.com', [FakeHostResolver::PUBLIC, '127.0.0.1']);
        $this->dns->answer('metadata.example.com', ['::ffff:169.254.169.254']);
        $this->dns->answer('nowhere.example.com', []);

        $this->assertRefused('https://intranet.example.com/file.csv', FetchRefused::PRIVATE);
        $this->assertRefused('https://rebind.example.com/file.csv', FetchRefused::PRIVATE);
        $this->assertRefused('https://metadata.example.com/', FetchRefused::PRIVATE);
        $this->assertRefused('https://nowhere.example.com/file.csv', FetchRefused::UNRESOLVED);

        Http::assertNothingSent();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function otherSchemes(): iterable
    {
        yield 'file' => ['file:///etc/passwd'];
        yield 'ftp' => ['ftp://example.com/file.csv'];
        yield 'gopher' => ['gopher://example.com:6379/_INFO'];
        yield 'dict' => ['dict://example.com:11211/stats'];
        yield 'php' => ['php://filter/resource=/etc/passwd'];
        yield 'no scheme' => ['//example.com/file.csv'];
        yield 'no host' => ['http:///file.csv'];
        yield 'not an address' => ['not an address'];
    }

    #[Test]
    #[DataProvider('otherSchemes')]
    public function only_http_and_https_are_fetched(string $url): void
    {
        Http::fake();

        $this->assertRefused($url, FetchRefused::SCHEME);

        Http::assertNothingSent();
    }

    #[Test]
    public function a_redirect_into_the_network_is_not_followed(): void
    {
        Http::fake([
            'https://cdn.example.com/to-loopback' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
            'https://cdn.example.com/to-metadata' => Http::response('', 301, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
            'https://cdn.example.com/to-a-name' => Http::response('', 307, ['Location' => 'https://intranet.example.com/']),
            'https://cdn.example.com/to-a-file' => Http::response('', 302, ['Location' => 'file:///etc/passwd']),
        ]);
        $this->dns->answer('intranet.example.com', ['192.168.0.10']);

        $this->assertRefused('https://cdn.example.com/to-loopback', FetchRefused::PRIVATE);
        $this->assertRefused('https://cdn.example.com/to-metadata', FetchRefused::PRIVATE);
        $this->assertRefused('https://cdn.example.com/to-a-name', FetchRefused::PRIVATE);
        $this->assertRefused('https://cdn.example.com/to-a-file', FetchRefused::SCHEME);

        Http::assertSentCount(4);
    }

    #[Test]
    public function redirects_are_followed_a_few_times_and_no_more(): void
    {
        Http::fake([
            'https://cdn.example.com/a' => Http::response('', 302, ['Location' => '/b']),
            'https://cdn.example.com/b' => Http::response('', 302, ['Location' => 'c?x=1']),
            'https://cdn.example.com/c?x=1' => Http::response('', 302, ['Location' => '//files.example.com/d.csv']),
            'https://files.example.com/d.csv' => Http::response("sku,name\n1,Sofa\n", 200, ['Content-Type' => 'text/csv; charset=utf-8']),
            'https://loop.example.com/*' => Http::response('', 302, ['Location' => '/again']),
        ]);

        $fetched = $this->fetcher()->get('https://cdn.example.com/a', 1024);

        $this->assertSame("sku,name\n1,Sofa\n", $fetched->body);
        $this->assertSame('text/csv', $fetched->contentType);
        $this->assertSame('https://files.example.com/d.csv', $fetched->url);

        $this->assertRefused('https://loop.example.com/start', FetchRefused::REDIRECTS);

        config()->set('webx-admin.remote.max_redirects', 0);
        $this->assertRefused('https://cdn.example.com/a', FetchRefused::REDIRECTS);
    }

    #[Test]
    public function an_answer_larger_than_the_limit_is_refused(): void
    {
        Http::fake(['https://files.example.com/*' => static fn () => Http::response(str_repeat('x', 2048))]);

        $this->assertRefused('https://files.example.com/big.csv', FetchRefused::TOO_LARGE, maxBytes: 1024);

        $path = (string) tempnam(sys_get_temp_dir(), 'webx-remote-test-');

        try {
            $this->fetcher()->download('https://files.example.com/big.csv', $path, 1024);
            $this->fail('A download past the limit was kept.');
        } catch (FetchRefused $refused) {
            $this->assertSame(FetchRefused::TOO_LARGE, $refused->reason);
            $this->assertFileDoesNotExist($path);
        }

        $kept = $this->fetcher()->download('https://files.example.com/big.csv', $path, 4096);

        $this->assertSame(2048, $kept->size);
        $this->assertSame($path, $kept->path);
        $this->assertStringEqualsFile($path, str_repeat('x', 2048));
        @unlink($path);
    }

    #[Test]
    public function a_refusal_does_not_carry_the_answer(): void
    {
        Http::fake(['https://files.example.com/*' => Http::response('secret-token-from-the-answer', 403)]);

        try {
            $this->fetcher()->get('https://files.example.com/file.csv', 1024);
            $this->fail('A 403 was taken.');
        } catch (FetchRefused $refused) {
            $this->assertSame(FetchRefused::STATUS, $refused->reason);
            $this->assertSame(403, $refused->status);
            $this->assertStringNotContainsString('secret-token', $refused->getMessage());
        }
    }

    #[Test]
    public function a_trusted_host_may_be_on_the_network(): void
    {
        Http::fake(['http://images.internal/*' => Http::response('ok')]);
        $this->dns->answer('images.internal', ['10.0.0.9']);

        $this->assertRefused('http://images.internal/a.png', FetchRefused::PRIVATE);

        config()->set('webx-admin.remote.trusted_hosts', ['images.internal']);
        $this->assertSame('ok', $this->fetcher()->get('http://images.internal/a.png', 1024)->body);
        $this->assertNull($this->fetcher()->check('http://images.internal/a.png')->address);

        config()->set('webx-admin.remote.trusted_hosts', []);
        $this->assertSame('ok', $this->fetcher()->trusting(['*.internal'])->get('http://images.internal/a.png', 1024)->body);
    }

    #[Test]
    public function only_the_listed_hosts_are_fetched_when_the_site_lists_them(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        config()->set('webx-admin.remote.only_hosts', ['*.example.com', 'example.org']);

        $this->assertSame('ok', $this->fetcher()->get('https://files.example.com/a.csv', 1024)->body);
        $this->assertSame('ok', $this->fetcher()->get('https://example.org/a.csv', 1024)->body);
        $this->assertRefused('https://example.net/a.csv', FetchRefused::HOST);
        // A wildcard is a subdomain, not the end of a longer name.
        $this->assertRefused('https://badexample.com/a.csv', FetchRefused::HOST);

        // A caller narrows the site's list, it does not widen it.
        $narrow = $this->fetcher()->onlyHosts(['files.example.com', 'example.net']);
        $this->assertSame('ok', $narrow->get('https://files.example.com/a.csv', 1024)->body);
        $this->assertRefusedBy($narrow, 'https://example.org/a.csv', FetchRefused::HOST);
        $this->assertRefusedBy($narrow, 'https://example.net/a.csv', FetchRefused::HOST);

        // The list does not open the network: a listed name inside it is still refused.
        $this->dns->answer('lan.example.com', ['10.1.1.1']);
        $this->assertRefused('https://lan.example.com/a.csv', FetchRefused::PRIVATE);
    }

    #[Test]
    public function the_connection_is_pinned_to_the_checked_address(): void
    {
        $this->dns->answer('files.example.com', [FakeHostResolver::PUBLIC]);

        $target = $this->fetcher()->check('https://files.example.com:8443/a.csv');

        $this->assertEquals(new Target('files.example.com', 8443, FakeHostResolver::PUBLIC), $target);
        $this->assertSame([CURLOPT_RESOLVE => ['files.example.com:8443:'.FakeHostResolver::PUBLIC]], $target->options()['curl']);
        $this->assertSame(['files.example.com:443:[2606:2800::1]'], (new Target('files.example.com', 443, '2606:2800::1'))->options()['curl'][CURLOPT_RESOLVE]);
        // An IP typed as it is has no name to answer twice.
        $this->assertSame([], (new Target(FakeHostResolver::PUBLIC, 80, FakeHostResolver::PUBLIC))->options());
    }

    #[Test]
    public function head_answers_without_the_body(): void
    {
        Http::fake(['*' => Http::response('', 200, ['Content-Type' => 'video/mp4', 'Content-Length' => '5000000'])]);

        $fetched = $this->fetcher()->head('https://files.example.com/clip.mp4');

        $this->assertSame('video/mp4', $fetched->contentType);
        $this->assertSame(5000000, $fetched->size);
        Http::assertSent(static fn ($request): bool => $request->method() === 'HEAD');
    }

    /**
     * The limits above are checked against `Http::fake()`, which never calls Guzzle's callbacks;
     * this is the real transport stopping a body as it grows, from a server on this machine.
     */
    #[Test]
    public function a_real_transfer_stops_at_the_limit(): void
    {
        $directory = sys_get_temp_dir().'/webx-remote-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/index.php', '<?php if ($_SERVER["REQUEST_URI"] === "/sized") { header("Content-Length: 5000000"); } echo str_repeat("x", 5000000);');
        $port = random_int(20000, 40000);
        $server = new Process([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $directory]);
        $server->start();

        try {
            for ($tries = 0; $tries < 50 && @fsockopen('127.0.0.1', $port) === false; $tries++) {
                usleep(100_000);
            }

            if (! $server->isRunning()) {
                $this->markTestSkipped('No local server to fetch from.');
            }

            // Loopback is trusted here on purpose: the subject is the limit, not the address.
            $fetcher = $this->fetcher()->trusting(['127.0.0.1']);
            $path = (string) tempnam(sys_get_temp_dir(), 'webx-remote-test-');

            foreach (['/sized', '/streamed'] as $uri) {
                $this->assertRefusedBy($fetcher, "http://127.0.0.1:{$port}{$uri}", FetchRefused::TOO_LARGE, maxBytes: 100_000);

                try {
                    $fetcher->download("http://127.0.0.1:{$port}{$uri}", $path, 100_000);
                    $this->fail('A download past the limit was kept.');
                } catch (FetchRefused $refused) {
                    $this->assertSame(FetchRefused::TOO_LARGE, $refused->reason);
                    $this->assertFileDoesNotExist($path);
                }
            }

            $this->assertSame(5000000, $fetcher->get("http://127.0.0.1:{$port}/streamed", 6_000_000)->size);
        } finally {
            $server->stop();
            @unlink($directory.'/index.php');
            @rmdir($directory);
        }
    }

    private function fetcher(): RemoteFetcher
    {
        return $this->app->make(RemoteFetcher::class);
    }

    private function assertRefused(string $url, string $reason, int $maxBytes = 1024): void
    {
        $this->assertRefusedBy($this->fetcher(), $url, $reason, $maxBytes);
    }

    private function assertRefusedBy(RemoteFetcher $fetcher, string $url, string $reason, int $maxBytes = 1024): void
    {
        try {
            $fetcher->get($url, $maxBytes);
        } catch (FetchRefused $refused) {
            $this->assertSame($reason, $refused->reason, "[{$url}]: {$refused->getMessage()}");

            return;
        }

        $this->fail("[{$url}] was fetched.");
    }
}
