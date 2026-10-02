<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Crawl\RobotsRules;
use WebxUi\Audit\Crawl\SitemapReader;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Audit\Runs\AuditResource;
use WebxUi\Audit\Runs\AuditRun;

/**
 * A3 on a site with the mistakes of §5.3–§5.6: a robots.txt with a typo that closes the
 * assets, a sitemap that lists redirects and errors, chains and loops of redirects, language
 * versions that do not agree, broken structured data, and pictures, icons and external links
 * that do not open.
 */
final class IndexingTest extends TestCase
{
    private const BASE = 'https://shop.example.com';

    #[Test]
    public function a_full_run_checks_robots_the_sitemap_redirects_languages_data_and_resources(): void
    {
        $asked = $this->fakeSite();

        $this->artisan('webx:audit:run')->assertSuccessful();

        $run = AuditRun::query()->sole();
        $this->assertSame(AuditRun::DONE, $run->status);
        $found = AuditIssue::query()->get()->groupBy('check');

        foreach ([
            'robots.syntax' => '/robots.txt',
            'robots.blocks_assets' => '/robots.txt',
            'sitemap.lastmod' => '/sitemap-pages.xml',
            'sitemap.bad_url' => '/old',
            'sitemap.missing_page' => '/new/',
            'redirects.chain' => '/old',
            'redirects.temporary' => '/new',
            'redirects.loop' => '/loop-a',
            'redirects.to_error' => '/dead',
            'links.to_redirect' => '/',
            'hreflang.not_reciprocal' => '/',
            'hreflang.no_x_default' => '/',
            'hreflang.broken' => '/',
            'html.lang' => '/de/',
            'jsonld.invalid' => '/',
            'jsonld.required' => '/',
            'jsonld.recommended' => '/',
            'images.broken' => '/',
            'images.heavy' => '/',
            'images.format' => '/',
            'og.image_broken' => '/',
            'html.favicon' => '/',
            'links.external_broken' => '/',
            'hosts.external_redirect' => '/',
        ] as $check => $path) {
            $this->assertContains(self::BASE.$path, $found->get($check)?->pluck('url')->all() ?? [], "{$check} at {$path}");
        }

        foreach (['robots.missing', 'robots.disallow_all', 'robots.no_sitemap', 'sitemap.missing', 'sitemap.limits'] as $quiet) {
            $this->assertFalse($found->has($quiet), "{$quiet} has nothing to say");
        }

        $sitemapBad = $found->get('sitemap.bad_url')?->pluck('url')->all() ?? [];
        $this->assertContains(self::BASE.'/gone', $sitemapBad);
        $this->assertContains(self::BASE.'/closed', $sitemapBad);
        $this->assertNotContains(self::BASE.'/', $sitemapBad);

        $chain = $found->get('redirects.chain')?->firstWhere('url', self::BASE.'/old');
        $this->assertSame([301, 302, 200], array_column($chain->details['table']['rows'] ?? [], 'status'));
        $this->assertNotContains(self::BASE.'/new', $found->get('redirects.chain')?->pluck('url')->all() ?? [], 'Said at the head of the chain only.');
        $this->assertCount(2, $found->get('redirects.loop') ?? [], 'Both steps of the loop walk into it.');

        $format = $found->get('images.format')?->first();
        $this->assertSame([self::BASE.'/img/big.jpg'], array_column($format->details['table']['rows'] ?? [], 'url'), 'The JPEG with a WebP source beside it is left alone.');

        $og = $found->get('og.image_broken')?->first();
        $this->assertSame('webx-audit::details.og-image-small', $og->details['summary']['key'] ?? null);

        $broken = $found->get('hreflang.broken')?->first();
        $this->assertSame(['jp'], array_column($broken->details['table']['rows'] ?? [], 'lang'));

        $syntax = $found->get('robots.syntax')?->first();
        $this->assertSame(['robots-unknown'], array_column($syntax->details['table']['rows'] ?? [], 'problem'));

        // Somebody else's page is asked, and once: two links, one request.
        $this->assertSame(1, $asked['GET https://partner.example.org/gone'] ?? 0);
        $this->assertSame(1, $asked['HEAD https://partner.example.org/moved'] ?? 0);
        $script = AuditResource::query()->where('url', self::BASE.'/assets/app.js')->sole();
        $this->assertSame(['GET', 200], [$script->method, $script->status], 'A server without HEAD is asked with GET.');
        $this->assertArrayNotHasKey('HEAD https://dev.shop.example.com/storage/hero.jpg', $asked, 'A stand is not asked.');

        $external = AuditLink::query()->where('to_url', 'https://partner.example.org/gone')->first();
        $this->assertSame(404, $external?->status, 'The answer reaches the link.');
        $this->assertSame(1, AuditResource::query()->where('url', 'https://partner.example.org/gone')->count());
    }

    #[Test]
    public function the_card_lists_what_a_page_loads(): void
    {
        $this->fakeSite();
        $this->artisan('webx:audit:run')->assertSuccessful();

        $run = AuditRun::query()->sole();
        $home = AuditPage::query()->where('url', self::BASE.'/')->sole();
        $admin = $this->admin(['audit.view']);

        $card = $this->actingAs($admin, 'cms')->getJson("/api/cms/audit/runs/{$run->id}/pages/{$home->id}")->assertOk();
        $this->assertSame(2, $card->json('data.counts.microdata'));
        $this->assertSame(1, $card->json('data.counts.css'));
        $this->assertSame(1, $card->json('data.counts.js'));

        $images = $this->actingAs($admin, 'cms')->getJson("/api/cms/audit/runs/{$run->id}/pages/{$home->id}/resources?tab=images")->assertOk();
        $rows = $images->json('data');
        $this->assertIsArray($rows);
        $byUrl = array_column($rows, null, 'url');
        $this->assertSame(404, $byUrl[self::BASE.'/img/missing.jpg']['status'] ?? null);
        $this->assertSame(400_000, $byUrl[self::BASE.'/img/big.jpg']['bytes'] ?? null);
        $this->assertSame('Big spade', $byUrl[self::BASE.'/img/big.jpg']['alt'] ?? null);
        $this->assertSame(600, $byUrl[self::BASE.'/img/og.png']['width'] ?? null);
        $this->assertFalse($byUrl['https://dev.shop.example.com/storage/hero.jpg']['checked'] ?? true);

        $this->actingAs($admin, 'cms')
            ->getJson("/api/cms/audit/runs/{$run->id}/pages/{$home->id}/resources?tab=css")
            ->assertOk()
            ->assertJsonPath('data.0.url', self::BASE.'/assets/app.css')
            ->assertJsonPath('data.0.cache_control', 'max-age=60');

        $this->actingAs($admin, 'cms')
            ->getJson("/api/cms/audit/runs/{$run->id}/pages/{$home->id}/resources?tab=fonts")
            ->assertUnprocessable();

        $json = $card->json('data.page.json_ld');
        $this->assertNotNull($json[0]['error']);
        $this->assertSame([['type' => 'Product', 'missing' => ['offers | review | aggregateRating'], 'recommended' => ['image', 'description', 'brand', 'sku']]], $json[1]['items']);
        $this->assertStringContainsString('"Spade"', $json[1]['source']);

        $details = $this->actingAs($admin, 'cms')
            ->getJson("/api/cms/audit/runs/{$run->id}/issues?check=robots.syntax")
            ->assertOk()
            ->json('data.0.details.table');
        $this->assertSame('text', $details['columns'][2]['type'], 'A word cell reaches the panel as text…');
        $this->assertSame('Unknown directive', $details['rows'][0]['problem'], '…said in the reader’s language.');
    }

    #[Test]
    public function a_quick_run_reads_robots_and_the_sitemap_without_a_crawl(): void
    {
        Http::fake(static fn (Request $request) => match ($request->url()) {
            self::BASE.'/' => Http::response('<html lang="en"><body>Home</body></html>', 200, ['Content-Type' => 'text/html']),
            self::BASE.'/robots.txt' => Http::response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']),
            self::BASE.'/sitemap.xml' => Http::response('<urlset><url><loc>'.self::BASE.'/</loc><lastmod>2099-01-01</lastmod></url>', 200, ['Content-Type' => 'application/xml']),
            default => Http::response('', 404),
        });

        $this->artisan('webx:audit:run', ['--quick' => true])->assertSuccessful();

        $found = AuditIssue::query()->pluck('check')->all();

        $this->assertContains('robots.disallow_all', $found);
        $this->assertContains('robots.no_sitemap', $found);
        $this->assertContains('sitemap.missing', $found, 'The sitemap does not parse — the closing tag is missing.');
        $this->assertSame(0, AuditPage::query()->count(), 'No crawl in a quick run.');

        $run = AuditRun::query()->sole();
        $this->assertSame('/sitemap.xml', parse_url((string) ($run->probes['sitemaps']['files'][0]['url'] ?? ''), PHP_URL_PATH));
    }

    #[Test]
    public function robots_lines_search_engines_skip_are_found(): void
    {
        $problems = RobotsRules::problems("Disallow: /early\nUser-agent: *\nDisalow: /typo\nAllow: /ok\nnonsense\n# a comment\nHost: shop.example.com\n");

        $this->assertSame([
            ['line' => 1, 'value' => 'Disallow: /early', 'problem' => 'robots-outside-group'],
            ['line' => 3, 'value' => 'Disalow: /typo', 'problem' => 'robots-unknown'],
            ['line' => 5, 'value' => 'nonsense', 'problem' => 'robots-not-a-rule'],
        ], $problems);
    }

    #[Test]
    public function a_sitemap_is_parsed_with_its_dates(): void
    {
        $parsed = SitemapReader::parse('<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc> '.self::BASE.'/a.xml </loc><lastmod>2099-05-05</lastmod></sitemap></sitemapindex>');

        $this->assertSame('index', $parsed['kind']);
        $this->assertNull($parsed['error']);
        $this->assertSame([self::BASE.'/a.xml'], $parsed['locations']);
        $this->assertSame(1, $parsed['lastmod']['future']);

        $this->assertSame('The root element is <html>, not <urlset> or <sitemapindex>.', SitemapReader::parse('<html><body/></html>')['error']);
        $this->assertNotNull(SitemapReader::parse('<urlset><url>')['error']);
    }

    /**
     * The site, answering by method and address; returns how often each was asked.
     *
     * @return \ArrayObject<string, int>
     */
    private function fakeSite(): \ArrayObject
    {
        $html = ['Content-Type' => 'text/html; charset=utf-8'];
        $page = static fn (string $lang, string $head, string $body): string => '<!doctype html><html lang="'.$lang.'"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width"><title>A page of the garden shop with a title long enough</title>'
            .$head.'</head><body>'.$body.'</body></html>';

        // A PNG header is all getimagesize() reads: 600×315, half of what a large card needs.
        $png = "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR".pack('NN', 600, 315)."\x08\x06\x00\x00\x00\x00\x00\x00\x00";

        $home = $page('en',
            '<link rel="canonical" href="https://shop.example.com/">'
            .'<link rel="alternate" hreflang="en" href="https://shop.example.com/">'
            .'<link rel="alternate" hreflang="de" href="https://shop.example.com/de/">'
            .'<link rel="alternate" hreflang="jp" href="https://shop.example.com/jp/">'
            .'<link rel="stylesheet" href="/assets/app.css"><script src="/assets/app.js"></script>'
            .'<link rel="icon" href="/favicon-missing.ico">'
            .'<meta property="og:title" content="Shop"><meta property="og:url" content="https://shop.example.com/"><meta property="og:image" content="/img/og.png">'
            .'<script type="application/ld+json">{"@type": "Product",}</script>'
            .'<script type="application/ld+json">{"@context": "https://schema.org", "@type": "Product", "name": "Spade"}</script>',
            '<h1>Garden shop</h1>'
            .'<a href="/old">Old offer</a> <a href="/loop-a">Loop</a> <a href="/dead">Dead</a> <a href="/new/">New</a>'
            .'<a href="https://partner.example.org/gone">Partner</a> <a href="https://partner.example.org/gone" rel="nofollow">Partner again</a>'
            .'<a href="https://partner.example.org/moved">Moved partner</a>'
            .'<img src="/img/missing.jpg" alt="Missing" width="1" height="1">'
            .'<img src="/img/big.jpg" alt="Big spade" width="1" height="1">'
            .'<picture><source type="image/webp" srcset="/img/big2.webp"><img src="/img/big2.jpg" alt="Big rake" width="1" height="1"></picture>'
            .'<img src="https://dev.shop.example.com/storage/hero.jpg" alt="Hero" width="1" height="1">',
        );

        $site = [
            '/' => [200, $home, $html],
            '/robots.txt' => [200, "User-agent: *\nDisalow: /tmp\nDisallow: /assets/\n\nSitemap: https://shop.example.com/sitemap.xml\n", ['Content-Type' => 'text/plain']],
            '/sitemap.xml' => [200, '<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://shop.example.com/sitemap-pages.xml</loc></sitemap></sitemapindex>', ['Content-Type' => 'application/xml']],
            '/sitemap-pages.xml' => [200, '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .implode('', array_map(static fn (string $path): string => '<url><loc>https://shop.example.com'.$path.'</loc><lastmod>2026-01-01</lastmod></url>', ['/', '/old', '/gone', '/closed', '/de/', '/jp/']))
                .'</urlset>', ['Content-Type' => 'application/xml']],
            '/old' => [301, '', ['Location' => self::BASE.'/new']],
            '/new' => [302, '', ['Location' => self::BASE.'/new/']],
            '/new/' => [200, $page('en', '', '<h1>New</h1><a href="/">Home</a><a href="/closed">Closed</a>'), $html],
            '/loop-a' => [301, '', ['Location' => self::BASE.'/loop-b']],
            '/loop-b' => [301, '', ['Location' => self::BASE.'/loop-a']],
            '/dead' => [301, '', ['Location' => self::BASE.'/gone']],
            '/gone' => [404, 'Not found', $html],
            '/closed' => [200, $page('en', '<meta name="robots" content="noindex">', '<a href="/">Home</a>'), $html],
            '/de/' => [200, $page('en', '<link rel="alternate" hreflang="de" href="https://shop.example.com/de/">', '<a href="/">Start</a>'), $html],
            '/jp/' => [200, $page('ja', '', '<a href="/">Home</a>'), $html],
            '/assets/app.css' => [200, 'body{}', ['Content-Type' => 'text/css', 'Cache-Control' => 'max-age=60']],
            '/img/big.jpg' => [200, '', ['Content-Type' => 'image/jpeg', 'Content-Length' => '400000']],
            '/img/big2.jpg' => [200, '', ['Content-Type' => 'image/jpeg', 'Content-Length' => '400000']],
            '/img/big2.webp' => [200, '', ['Content-Type' => 'image/webp', 'Content-Length' => '90000']],
            '/img/og.png' => [200, $png, ['Content-Type' => 'image/png']],
        ];

        /** @var \ArrayObject<string, int> $asked */
        $asked = new \ArrayObject;

        Http::fake(static function (Request $request) use ($site, $asked) {
            $url = $request->url();
            $key = $request->method().' '.$url;
            $asked[$key] = ($asked[$key] ?? 0) + 1;

            if ($url === 'https://partner.example.org/gone') {
                return $request->method() === 'HEAD' ? Http::response('', 405) : Http::response('Gone', 404);
            }

            if ($url === 'https://partner.example.org/moved') {
                return Http::response('', 301, ['Location' => 'https://partner.example.org/new']);
            }

            if (! str_starts_with($url, self::BASE.'/')) {
                return Http::response('', 404);
            }

            $path = substr($url, strlen(self::BASE));

            if ($path === '/assets/app.js') {
                return $request->method() === 'HEAD' ? Http::response('', 405) : Http::response('1', 200, ['Content-Type' => 'text/javascript']);
            }

            [$status, $body, $headers] = $site[$path] ?? [404, 'Not found', []];

            return Http::response($request->method() === 'HEAD' ? '' : $body, $status, $headers);
        });

        return $asked;
    }
}
