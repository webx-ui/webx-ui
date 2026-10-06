<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Audit\Runs\Runner;
use WebxUi\Localization\Locales;

/**
 * The crawl on a small site with the usual mistakes: a stand's picture on the home page, a
 * broken link, a redirect, a page closed with noindex, a page only the sitemap knows — and the
 * pages screen reading what the crawl kept.
 */
final class CrawlTest extends TestCase
{
    private const BASE = 'https://shop.example.com';

    #[Test]
    public function a_full_run_crawls_the_site_and_reads_every_page(): void
    {
        $this->fakeSite();

        $this->artisan('webx:audit:run')->assertSuccessful();

        $run = AuditRun::query()->sole();
        $this->assertSame(AuditRun::DONE, $run->status);

        $pages = AuditPage::query()->pluck('status', 'url')->all();
        $this->assertSame(200, $pages[self::BASE.'/'] ?? null);
        $this->assertSame(200, $pages[self::BASE.'/about'] ?? null);
        $this->assertSame(301, $pages[self::BASE.'/blog'] ?? null);
        $this->assertSame(200, $pages[self::BASE.'/blog/'] ?? null);
        $this->assertSame(404, $pages[self::BASE.'/missing'] ?? null);
        $this->assertSame(200, $pages[self::BASE.'/orphan'] ?? null, 'Found through the sitemap alone.');
        $this->assertArrayNotHasKey('https://www.shop.example.com/', $pages, 'The other mirror is not crawled.');

        $home = $this->page('/');
        $this->assertSame(0, $home->depth);
        $this->assertSame('Shop — everything for the garden, delivered', $home->title);
        $this->assertSame(['Garden shop'], $home->h1);
        $this->assertSame('en', $home->lang);
        $this->assertTrue($home->indexable);

        $about = $this->page('/about');
        $this->assertSame(1, $about->depth);
        $this->assertTrue($about->in_sitemap);
        $this->assertSame(1, $about->links_in);

        $this->assertSame(200, $this->page('/blog')->final_status, 'The chain ends on the page it leads to.');
        $this->assertSame(
            [[1, 'Blog'], [1, 'Latest'], [2, 'Spring'], [4, 'Tulips']],
            $this->page('/blog/')->fact('outline'),
            'Every heading, in order, with its level.',
        );
        $this->assertTrue($this->page('/private')->blocked_by_robots);
        $this->assertNull($this->page('/orphan')->depth, 'No link from the home page reaches it.');

        $stand = AuditLink::query()->where('host_class', 'dev')->sole();
        $this->assertSame('https://dev.shop.example.com/storage/hero.jpg', $stand->to_url);
        $this->assertSame(AuditLink::IMG, $stand->kind);

        $found = AuditIssue::query()->get()->groupBy('check');

        foreach ([
            'hosts.dev_page' => '/',
            'links.broken' => '/',
            'mixed_content' => '/',
            'forms.insecure' => '/',
            'links.nofollow_internal' => '/',
            'hosts.wrong_mirror' => '/',
            'hosts.blank_opener' => '/',
            'images.alt' => '/',
            'a11y.button_name' => '/',
            'a11y.form_label' => '/',
            'indexing.noindex' => '/blog/',
            'h1.multiple' => '/blog/',
            'headings.skipped' => '/blog/',
            'structure.orphan' => '/orphan',
            'title.missing' => '/orphan',
            'description.duplicate' => '/about',
            'canonical.other' => '/private',
        ] as $check => $path) {
            $this->assertContains(self::BASE.$path, $found->get($check)?->pluck('url')->all() ?? [], "{$check} on {$path}");
        }

        $broken = $found->get('links.broken')?->first();
        $this->assertSame($home->id, $broken?->page_id);
        $this->assertSame([404], array_column($broken->details['table']['rows'] ?? [], 'status'));

        $this->assertNotContains(self::BASE.'/about', $found->get('h1.missing')?->pluck('url')->all() ?? []);
        $this->assertGreaterThan(0, (int) ($run->counts['health'] ?? 0));
    }

    #[Test]
    public function the_crawl_moves_a_piece_at_a_time(): void
    {
        $this->fakeSite();
        $runner = $this->app->make(Runner::class);
        $run = $runner->start(AuditRun::FULL);

        $steps = 0;

        while ($runner->step($run->refresh(), 0.0) && $steps < 200) {
            $steps++;
        }

        $run->refresh();
        $this->assertSame(AuditRun::DONE, $run->status);
        $this->assertGreaterThan(10, $steps, 'A zero budget still moves one batch, one check at a time.');
        $this->assertSame(AuditPage::query()->whereNotNull('fetched_at')->count(), $run->pages_crawled);
        $this->assertSame(1, AuditIssue::query()->where('check', 'links.broken')->count(), 'No piece stores its findings twice.');
    }

    #[Test]
    public function the_pages_screen_filters_sorts_and_exports(): void
    {
        $this->fakeSite();
        $this->artisan('webx:audit:run')->assertSuccessful();
        $run = AuditRun::query()->sole();
        $admin = $this->admin(['audit.view']);

        $this->actingAs($admin, 'cms')
            ->getJson("/api/cms/audit/runs/{$run->id}/pages?status=4xx")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.url', self::BASE.'/missing');

        $titled = $this->actingAs($admin, 'cms')
            ->getJson("/api/cms/audit/runs/{$run->id}/pages?f[title]=empty&sort=-word_count")
            ->assertOk()
            ->json('data');
        $this->assertContains(self::BASE.'/orphan', array_column($titled, 'url'));

        $this->actingAs($admin, 'cms')
            ->getJson("/api/cms/audit/runs/{$run->id}/pages?check=structure.orphan")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($admin, 'cms')
            ->getJson("/api/cms/audit/runs/{$run->id}/pages?f[word_count]=gt:5&indexable=1")
            ->assertOk()
            ->assertJsonPath('data.0.url', self::BASE.'/');

        $home = $this->page('/');

        $card = $this->actingAs($admin, 'cms')->getJson("/api/cms/audit/runs/{$run->id}/pages/{$home->id}")->assertOk();
        $this->assertGreaterThan(0, $card->json('data.counts.issues'));
        $this->assertSame('text/html; charset=utf-8', $card->json('data.page.headers.content-type'));

        $out = $this->actingAs($admin, 'cms')->getJson("/api/cms/audit/runs/{$run->id}/pages/{$home->id}/links?kind=a")->assertOk();
        $this->assertContains(self::BASE.'/missing', array_column($out->json('data'), 'url'));

        $about = $this->page('/about');
        $in = $this->actingAs($admin, 'cms')->getJson("/api/cms/audit/runs/{$run->id}/pages/{$about->id}/links?direction=in&kind=a")->assertOk();
        $this->assertSame([self::BASE.'/', self::BASE.'/'], array_column($in->json('data'), 'url'));

        $csv = $this->actingAs($admin, 'cms')->get("/api/cms/audit/runs/{$run->id}/pages/export?columns=url,status&status=4xx")->assertOk();
        $this->assertSame("\xEF\xBB\xBFAddress,Code\n".self::BASE."/missing,404\n", $csv->streamedContent());

        $this->actingAs($admin, 'cms')
            ->getJson('/api/cms/audit/runs/latest')
            ->assertOk()
            ->assertJsonPath('data.crawled.id', $run->id);
    }

    #[Test]
    public function every_language_is_crawled_from_its_own_home_as_pages_of_their_own(): void
    {
        $this->app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'de']]);
        $this->app->make(Locales::class)->forget();

        $html = ['Content-Type' => 'text/html; charset=utf-8'];
        $page = static fn (string $lang, string $title, string $body): string => '<!doctype html><html lang="'.$lang.'"><head><title>'.$title.'</title></head><body>'.$body.'</body></html>';
        // Nothing on the English side leads to German, and there is no sitemap: only the seed can find /de.
        $site = [
            '/' => $page('en', 'Garden shop', '<h1>Garden shop</h1><a href="/about">About</a>'),
            '/about' => $page('en', 'About', '<h1>About</h1><a href="/">Home</a>'),
            '/de' => $page('de', 'Gartenladen', '<h1>Gartenladen</h1><a href="/de/ueber-uns">Über uns</a>'),
            '/de/ueber-uns' => $page('de', 'Über uns', '<h1>Über uns</h1><a href="/de">Start</a>'),
        ];

        Http::fake(static function (Request $request) use ($site, $html) {
            $path = substr($request->url(), strlen(self::BASE));

            return isset($site[$path]) ? Http::response($site[$path], 200, $html) : Http::response('Not found', 404);
        });

        $this->artisan('webx:audit:run')->assertSuccessful();

        $german = $this->page('/de');
        $this->assertSame(AuditPage::HOME, $german->source);
        $this->assertSame(0, $german->depth);
        $this->assertSame('de', $german->lang);
        $this->assertSame(1, $this->page('/de/ueber-uns')->depth, 'Depth counts from the home of its language.');

        $run = AuditRun::query()->sole();
        $this->assertSame(4, $run->pages_crawled, 'Each language version is a page of its own, under the one limit.');
        $this->assertSame(4, $run->counts['health_parts']['pages'] ?? null);
        $this->assertSame(0, AuditIssue::query()->where('check', 'structure.orphan')->count(), 'A language home is a home, not an orphan.');
    }

    #[Test]
    public function a_finding_that_counts_elements_quotes_them(): void
    {
        $html = ['Content-Type' => 'text/html; charset=utf-8'];
        $body = '<!doctype html><html lang="en"><head><title>Garden shop</title></head><body><h1>Shop</h1>'
            .'<img src="/a.jpg"><img src="/b.jpg" alt="">'
            .'<button class="cart"><svg></svg></button>'
            .'<input type="email" name="email" placeholder="Your email">'
            .str_repeat('<img src="/c.jpg">', 6)
            .'</body></html>';

        Http::fake(static fn (Request $request) => $request->url() === self::BASE.'/'
            ? Http::response($body, 200, $html)
            : Http::response('Not found', 404));

        $this->artisan('webx:audit:run')->assertSuccessful();

        $markup = static fn (string $check): array => array_column(
            AuditIssue::query()->where('check', $check)->sole()->details['table']['rows'] ?? [],
            'markup',
        );

        $this->assertSame(['<img src="/a.jpg">', ...array_fill(0, 4, '<img src="/c.jpg">')], $markup('images.alt'), 'Five at most; an empty alt is a decision.');
        $this->assertSame(['<button class="cart"><svg></svg></button>'], $markup('a11y.button_name'));
        $this->assertSame(['<input type="email" name="email" placeholder="Your email">'], $markup('a11y.form_label'));
        $this->assertSame('code', AuditIssue::query()->where('check', 'images.alt')->sole()->details['table']['columns'][0]['type'] ?? null);
    }

    private function page(string $path): AuditPage
    {
        return AuditPage::query()->where('url', self::BASE.$path)->sole();
    }

    private function fakeSite(): void
    {
        $html = ['Content-Type' => 'text/html; charset=utf-8'];
        $head = static fn (string $title, string $extra = ''): string => '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width"><title>'.$title.'</title>'
            .'<meta name="description" content="Garden tools and plants, delivered to the door within a day anywhere in the country.">'
            .$extra.'</head>';

        $site = [
            '/' => [200, $head('Shop — everything for the garden, delivered', '<link rel="canonical" href="https://shop.example.com/"><script src="http://cdn.example.org/old.js"></script>')
                .'<body><h1>Garden shop</h1><p>Spades, rakes, seeds and seedlings for every garden and every season.</p>'
                .'<a href="/about">About us</a> <a href="/blog">Blog</a> <a href="/missing">Old offer</a> <a href="/private">Private</a>'
                .'<a href="/about" rel="nofollow">About again</a>'
                .'<a href="https://www.shop.example.com/about">Mirror</a>'
                .'<a href="https://partner.example.org/" target="_blank">Partner</a>'
                .'<img src="https://dev.shop.example.com/storage/hero.jpg">'
                .'<form action="http://shop.example.com/subscribe"><input type="email" name="email"><button></button></form>'
                .'</body></html>', $html],
            '/about' => [200, $head('About the garden shop and the people who run it')
                .'<body><h1>About</h1><p>We grow what we sell.</p><a href="/">Home</a></body></html>', $html],
            '/blog' => [301, '', ['Location' => self::BASE.'/blog/']],
            '/blog/' => [200, $head('The garden blog: what to plant and when to plant it', '<meta name="robots" content="noindex, follow">')
                .'<body><h1>Blog</h1><h1>Latest</h1><h2>Spring</h2><h4>Tulips</h4><a href="/">Home</a></body></html>', $html],
            '/private' => [200, '<html><head><link rel="canonical" href="https://shop.example.com/about"></head><body><a href="/">Home</a></body></html>', $html],
            '/orphan' => [200, '<html><body><p>Forgotten.</p><a href="/">Home</a></body></html>', $html],
            '/robots.txt' => [200, "User-agent: *\nDisallow: /private\nSitemap: https://shop.example.com/sitemap.xml\n", ['Content-Type' => 'text/plain']],
            '/sitemap.xml' => [200, '<?xml version="1.0"?><urlset><url><loc>https://shop.example.com/about</loc></url><url><loc>https://shop.example.com/orphan</loc></url></urlset>', ['Content-Type' => 'application/xml']],
        ];

        Http::fake(static function (Request $request) use ($site) {
            $url = $request->url();

            if (! str_starts_with($url, self::BASE.'/')) {
                return Http::response('', 404);
            }

            $path = substr($url, strlen(self::BASE));
            [$status, $body, $headers] = $site[$path] ?? [404, 'Not found', []];

            return Http::response($body, $status, $headers);
        });
    }
}
