<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Checks\Page\SerpWidth;
use WebxUi\Audit\Checks\Page\VagueAnchors;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditLink;

/**
 * The checks of A6 (§14) on one small site that makes every mistake they look for: canonicals
 * that loop and chain, language versions that disagree, links nobody can follow, filler text,
 * a folder that lists its files — and the pages that make none of them, which stay quiet.
 */
final class MoreChecksTest extends TestCase
{
    private const BASE = 'https://shop.example.com';

    #[Test]
    public function every_new_check_finds_its_mistake_and_only_there(): void
    {
        $this->app['config']->set('webx-audit.vague_anchors', ['see the offer']);
        $this->fakeSite();

        $this->artisan('webx:audit:run')->assertSuccessful();

        $found = AuditIssue::query()->get()->groupBy('check');
        $where = static fn (string $check): array => $found->get($check)?->pluck('url')->map(static fn (?string $url): string => (string) substr((string) $url, strlen(self::BASE)))->sort()->values()->all() ?? [];

        foreach ([
            'redirects.meta_refresh' => ['/old'],
            'links.unfollowable' => ['/'],
            'links.vague_anchor' => ['/'],
            'links.utm' => ['/'],
            'links.to_non_canonical' => ['/'],
            'links.to_noindex' => ['/'],
            'indexing.nofollow' => ['/closed'],
            'html.doctype' => ['/old'],
            'html.obsolete' => ['/old'],
            'meta.multiple' => ['/'],
            'content.placeholder' => ['/lorem'],
            'content.soft_404' => ['/gone'],
            'h1.duplicate' => ['/about', '/twin'],
            'h1.length' => ['/wide'],
            'title.width' => ['/wide'],
            'canonical.chain' => ['/chain-a'],
            'canonical.loop' => ['/loop-a', '/loop-b'],
            'canonical.foreign' => ['/foreign'],
            'canonical.fragment' => ['/frag'],
            'canonical.pagination' => ['/catalog?page=2'],
            'hreflang.self_missing' => ['/es'],
            'hreflang.duplicate_lang' => ['/'],
            'hreflang.not_indexable' => ['/'],
            'hreflang.lang_mismatch' => ['/'],
            'structure.noindex_only' => ['/hidden-child'],
            'structure.nofollow_only' => ['/nf'],
            'sitemap.duplicate' => ['/sitemap.xml'],
            'assets.broken' => ['/'],
            'assets.heavy' => ['/'],
            'images.redirect' => ['/'],
            'images.alt_long' => ['/'],
            'host.directory_listing' => ['/storage/'],
        ] as $check => $paths) {
            $this->assertSame($paths, $where($check), $check);
        }

        $this->assertContains('/hidden-child', $where('structure.single_link'));
        $this->assertNotContains('/about', $where('structure.single_link'), 'Several pages link to it.');

        $charset = AuditIssue::query()->where('check', 'html.charset')->pluck('severity', 'url')->all();
        $this->assertSame('warning', $charset[self::BASE.'/old'] ?? null, 'The meta and the header disagree.');
        $this->assertSame('notice', $charset[self::BASE.'/lorem'] ?? null, 'Nobody says the encoding.');

        $format = AuditIssue::query()->where('check', 'url.format')->get()->map(static fn (AuditIssue $issue): string => substr((string) $issue->url, strlen(self::BASE)).' '.$issue->details['summary']['key'])->all();
        $this->assertContains('/catalog/catalog/item webx-audit::details.url-repeated', $format);
        $this->assertContains('/with%20space webx-audit::details.url-space', $format);
        $this->assertNotContains('/news/2026/10/10 webx-audit::details.url-repeated', $format, 'A date is not a mistake.');

        $vague = $found->get('links.vague_anchor')?->first();
        $this->assertSame(['Read more →', 'See the offer!'], array_column($vague->details['table']['rows'] ?? [], 'anchor'), 'Built-in words and the site’s own.');

        $unfollowable = $found->get('links.unfollowable')?->first();
        $this->assertSame('warning', $unfollowable?->severity, 'A mailto: without an address is broken, not a button.');
        // A letter with a subject and no address is the visitor's to address — a share row's e-mail.
        $this->assertSame(3, $unfollowable->details['summary']['params']['count'] ?? null);

        // A network's dialog for sharing is not a page: it is neither checked nor counted.
        $this->assertFalse(AuditLink::query()->where('to_url', 'like', '%facebook.com/sharer%')->exists());

        $hreflang = $found->get('hreflang.not_indexable')?->first();
        $this->assertSame('error', $hreflang?->severity);
    }

    #[Test]
    public function a_title_is_measured_in_the_results_font(): void
    {
        $this->assertGreaterThan(SerpWidth::of('iiii', 20) * 3, SerpWidth::of('mmmm', 20), 'An m is wider than three i.');
        $this->assertGreaterThan(SerpWidth::of('shop', 20), SerpWidth::of('SHOP', 20));
        $this->assertSame(SerpWidth::of('cafe', 20), SerpWidth::of('café', 20), 'An accent does not widen a letter.');
        $this->assertGreaterThan(0, SerpWidth::of('Магазин', 20));
        $this->assertSame(40, SerpWidth::of('漢字', 20), 'A full em each.');
    }

    #[Test]
    public function an_anchor_is_compared_without_what_surrounds_it(): void
    {
        $this->assertSame('read more', VagueAnchors::normalise("  Read\n more → "));
        $this->assertSame('подробнее', VagueAnchors::normalise('Подробнее...'));
        $this->assertSame('read more about roses', VagueAnchors::normalise('Read more about roses'));
    }

    private function fakeSite(): void
    {
        $html = ['Content-Type' => 'text/html; charset=utf-8'];
        $page = static fn (string $title, string $body, string $head = '', bool $doctype = true): string => ($doctype ? '<!doctype html>' : '')
            .'<html lang="en"><head><meta charset="utf-8"><title>'.$title.'</title>'.$head.'</head><body>'.$body.'</body></html>';
        $canonical = static fn (string $path): string => '<link rel="canonical" href="'.self::BASE.$path.'">';
        $alternate = static fn (string $lang, string $path): string => '<link rel="alternate" hreflang="'.$lang.'" href="'.self::BASE.$path.'">';

        $site = [
            '/' => [200, $page('Shop', '<h1>Garden shop</h1>'
                .'<a href="/about">About us</a> <a href="/catalog">Read more →</a> <a href="/twin">See the offer!</a>'
                .'<a href="/catalog?page=2">Catalogue, page 2</a> <a href="/about?utm_source=mail">Spring sale</a>'
                .'<a href="#">Menu</a> <a href="mailto:info">Write to us</a> <a href="www.partner.example.org">Partner</a>'
                .'<a href="mailto:?subject=Tools&amp;body=https%3A%2F%2Fshop.com">Send by e-mail</a>'
                .'<a href="https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fshop.com">Share on Facebook</a>'
                .'<a href="/copy">A copy</a> <a href="/closed">Closed</a> <a href="/nf" rel="nofollow">Hidden</a>'
                .'<a href="/old">Old</a> <a href="/lorem">Draft</a> <a href="/gone">Gone</a> <a href="/wide">Sale</a>'
                .'<a href="/chain-a">Chain</a> <a href="/loop-a">Loop</a> <a href="/foreign">Foreign</a> <a href="/frag">Fragment</a>'
                .'<a href="/catalog/catalog/item">Item</a> <a href="/with%20space">Space</a> <a href="/news/2026/10/10">News</a>'
                .'<a href="/es">Español</a>'
                .'<img src="/img/a.jpg" alt="'.str_repeat('A garden spade with a wooden handle ', 4).'">',
                $canonical('/').$alternate('en', '/').$alternate('de', '/de').$alternate('de', '/de2')
                .'<meta name="description" content="Tools."><meta name="description" content="Tools again.">'
                .'<link rel="stylesheet" href="/css/missing.css"><script src="/js/app.js"></script>'), $html],
            '/about' => [200, $page('About the shop', '<h1>About</h1><a href="/">Home</a>'), $html],
            '/twin' => [200, $page('Our twin page', '<h1>  about </h1><a href="/">Home</a> <a href="/about">About</a>'), $html],
            '/catalog' => [200, $page('Catalogue', '<h1>Catalogue</h1>'), $html],
            '/catalog?page=2' => [200, $page('Catalogue, page 2', '<h1>Catalogue 2</h1>', $canonical('/catalog')), $html],
            '/copy' => [200, $page('A copy', '<h1>Copy</h1>', $canonical('/about')), $html],
            '/closed' => [200, $page('Closed', '<h1>Closed</h1><a href="/hidden-child">Child</a>', '<meta name="robots" content="noindex, nofollow">'), $html],
            '/hidden-child' => [200, $page('Hidden child', '<h1>Child</h1>'), $html],
            '/nf' => [200, $page('Nofollow only', '<h1>Nofollow</h1>'), $html],
            '/old' => [200, '<html><head><meta charset="windows-1251"><title>Old</title><meta http-equiv="refresh" content="0; url=/about"></head>'
                .'<body><h1>Old</h1><font color="red">Sale</font><center>!</center></body></html>', $html],
            '/lorem' => [200, '<!doctype html><html><head><title>Draft</title></head><body><h1>Draft</h1><p>Lorem ipsum dolor sit amet.</p></body></html>', ['Content-Type' => 'text/html']],
            '/gone' => [200, $page('Page not found', '<h1>Sorry</h1>'), $html],
            '/wide' => [200, $page('MEGA SALE ON EVERY GARDEN TOOL AND PLANT WE HAVE — SHOP NOW', '<h1>'.str_repeat('Everything for the garden ', 4).'</h1>'), $html],
            '/chain-a' => [200, $page('Chain A', '<h1>A</h1>', $canonical('/chain-b')), $html],
            '/chain-b' => [200, $page('Chain B', '<h1>B</h1>', $canonical('/about')), $html],
            '/loop-a' => [200, $page('Loop A', '<h1>Loop A</h1>', $canonical('/loop-b')), $html],
            '/loop-b' => [200, $page('Loop B', '<h1>Loop B</h1>', $canonical('/loop-a')), $html],
            '/foreign' => [200, $page('Foreign', '<h1>Foreign</h1>', '<link rel="canonical" href="http://shop.example.com/foreign">'), $html],
            '/frag' => [200, $page('Fragment', '<h1>Fragment</h1>', '<link rel="canonical" href="'.self::BASE.'/frag#top">'), $html],
            '/catalog/catalog/item' => [200, $page('Item', '<h1>Item</h1>'), $html],
            '/with%20space' => [200, $page('Space', '<h1>Space</h1>'), $html],
            '/news/2026/10/10' => [200, $page('News of the day', '<h1>News</h1>'), $html],
            '/de' => [200, $page('Laden', '<h1>Laden</h1>', $alternate('de-AT', '/de').$alternate('en', '/')), $html],
            '/de2' => [200, $page('Laden 2', '<h1>Laden 2</h1>', '<meta name="robots" content="noindex">'), $html],
            '/es' => [200, $page('Tienda', '<h1>Tienda</h1>', $alternate('en', '/')), $html],
            '/css/missing.css' => [404, 'Not found', []],
            '/js/app.js' => [200, '', ['Content-Type' => 'application/javascript', 'Content-Length' => '2000000']],
            '/img/a.jpg' => [301, '', ['Location' => self::BASE.'/img/b.jpg']],
            '/storage/' => [200, '<html><head><title>Index of /storage</title></head><body><h1>Index of /storage</h1></body></html>', $html],
            '/robots.txt' => [200, "User-agent: *\nSitemap: https://shop.example.com/sitemap.xml\n", ['Content-Type' => 'text/plain']],
            '/sitemap.xml' => [200, '<?xml version="1.0"?><urlset><url><loc>https://shop.example.com/about</loc></url><url><loc>https://shop.example.com/about</loc></url></urlset>', ['Content-Type' => 'application/xml']],
        ];

        Http::fake(static function (Request $request) use ($site) {
            $url = $request->url();

            if (! str_starts_with($url, self::BASE.'/')) {
                return Http::response('', 404);
            }

            [$status, $body, $headers] = $site[substr($url, strlen(self::BASE))] ?? [404, 'Not found', []];

            return Http::response($body, $status, $headers);
        });
    }
}
