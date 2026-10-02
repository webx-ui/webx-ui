<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Hosts\UrlFinder;

final class HostsTest extends PhpUnitTestCase
{
    /** @return iterable<string, array{string, string|null}> */
    public static function addresses(): iterable
    {
        yield 'own' => ['https://shop.com/about', HostClassifier::OWN];
        yield 'own in capitals' => ['https://SHOP.com/', HostClassifier::OWN];
        yield 'the other mirror' => ['http://www.shop.com/a', HostClassifier::OWN_MIRROR];
        yield 'listed stand' => ['https://stage-box.agency.net/img.png', HostClassifier::DEV];
        yield 'listed as an address' => ['https://old-shop.net/x', HostClassifier::DEV];
        yield 'localhost' => ['http://localhost:8000/x', HostClassifier::DEV];
        yield 'loopback' => ['http://127.0.0.1/x', HostClassifier::DEV];
        yield 'private network' => ['http://192.168.1.20/x', HostClassifier::DEV];
        yield 'ipv6 loopback' => ['http://[::1]/x', HostClassifier::DEV];
        yield '.local zone' => ['https://shop.local/x', HostClassifier::DEV];
        yield '.test zone' => ['//shop.test/x.jpg', HostClassifier::DEV];
        yield 'dev first label' => ['https://dev.shop.com/x', HostClassifier::DEV];
        yield 'two labels are a domain' => ['https://demo.com/', HostClassifier::EXTERNAL];
        yield 'external' => ['https://cdn.jsdelivr.net/x.js', HostClassifier::EXTERNAL];
        yield 'public ip' => ['http://8.8.8.8/', HostClassifier::EXTERNAL];
        yield 'relative' => ['/about', null];
        yield 'mailto' => ['mailto:hi@shop.com', null];
    }

    #[Test]
    #[DataProvider('addresses')]
    public function every_address_has_a_class(string $url, ?string $class): void
    {
        $classifier = new HostClassifier(
            ['shop.com'],
            ['stage-box.agency.net', 'https://old-shop.net/'],
            ['local', 'localhost', 'test', 'example', 'invalid', 'internal'],
            ['dev', 'stage', 'staging', 'test', 'preview', 'demo'],
        );

        $this->assertSame($class, $classifier->classify($url));
    }

    #[Test]
    public function the_sites_own_host_is_never_a_stand(): void
    {
        $classifier = new HostClassifier(['shop.local'], [], ['local']);

        $this->assertSame(HostClassifier::OWN, $classifier->classify('http://shop.local/a'));
        $this->assertSame(HostClassifier::DEV, $classifier->classify('http://other.local/a'));
        $this->assertTrue($classifier->looksLikeStand('shop.local'));
    }

    #[Test]
    public function the_finder_reads_text_html_and_json(): void
    {
        $finder = new UrlFinder;

        $text = 'See https://dev.shop.com/sale. And <img src="//shop.local/a.jpg"> '
            .'{"href":"https:\/\/stage.shop.com\/x?y=1"} and a comment // not.a.link '
            .'mailto:hi@shop.com, (https://shop.com/a).';

        $this->assertSame([
            'https://dev.shop.com/sale',
            'https://stage.shop.com/x?y=1',
            'https://shop.com/a',
            '//shop.local/a.jpg',
        ], $finder->find($text));
    }

    #[Test]
    public function a_text_without_slashes_costs_nothing(): void
    {
        $this->assertSame([], (new UrlFinder)->find('plain words, no addresses'));
    }
}
