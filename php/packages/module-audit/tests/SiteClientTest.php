<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Probes\SiteClient;

/**
 * The body a probe keeps is limited by what it decodes to, not by what came over the wire: a
 * page of a few kilobytes of gzip that inflates to gigabytes costs the limit and no more.
 */
final class SiteClientTest extends TestCase
{
    #[Test]
    public function a_compressed_page_is_decoded_no_further_than_the_limit(): void
    {
        $page = gzencode(str_repeat('a', 16 * 1024 * 1024), 9);
        Http::fake(['*' => Http::response((string) $page, 200, ['Content-Encoding' => 'gzip', 'Content-Length' => (string) strlen((string) $page)])]);

        memory_reset_peak_usage();
        $before = memory_get_usage();
        $answer = $this->app->make(SiteClient::class)->get('https://shop.example.com/', 1024 * 1024);

        $this->assertLessThan(8 * 1024 * 1024, memory_get_peak_usage() - $before);
        $this->assertSame(str_repeat('a', 1024 * 1024), $answer->body);
        // What the compression checks read, as Guzzle would have left it.
        $this->assertSame('gzip', $answer->header('x-encoded-content-encoding'));
        $this->assertSame((string) strlen((string) $page), $answer->header('x-encoded-content-length'));
        Http::assertSent(static fn ($request): bool => $request->header('Accept-Encoding') === ['gzip, deflate']);
    }

    #[Test]
    public function a_small_compressed_page_arrives_whole(): void
    {
        Http::fake(['*' => Http::response((string) gzcompress('<a href="/about">About</a>'), 200, ['Content-Encoding' => 'deflate'])]);

        $answer = $this->app->make(SiteClient::class)->get('https://shop.example.com/');

        $this->assertSame('<a href="/about">About</a>', $answer->body);
        $this->assertSame('deflate', $answer->header('x-encoded-content-encoding'));
    }
}
