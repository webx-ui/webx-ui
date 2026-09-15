<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use WebxUi\Routing\UrlNormaliser;

/**
 * Two spellings for two questions, and the distance between them is the point (§12.1).
 *
 * `key()` is what the registry stores and looks up by; `normalise()` is what a rule written by
 * hand is compared against. A rule is written for `?page=2`, so the query survives there; an
 * address is not its query string, so it does not survive here.
 */
class NormaliserTest extends PhpUnitTestCase
{
    #[Test]
    public function the_key_is_one_spelling_of_an_address(): void
    {
        $this->assertSame('catalog/shoes', UrlNormaliser::key('/Catalog/Shoes/'));
        $this->assertSame('catalog/shoes', UrlNormaliser::key('catalog//shoes'));
        $this->assertSame('catalog', UrlNormaliser::key('https://example.test/catalog?page=2#reviews'));
        $this->assertSame('ремни', UrlNormaliser::key('/Ремни'));

        // The site root, and nothing else, is the empty string.
        $this->assertSame('', UrlNormaliser::key('/'));
        $this->assertSame('', UrlNormaliser::key(''));
    }

    #[Test]
    public function a_rule_keeps_its_query_and_its_leading_slash(): void
    {
        $this->assertSame('/catalog/shoes', UrlNormaliser::normalise('catalog/shoes/'));
        $this->assertSame('/catalog/shoes?page=2', UrlNormaliser::normalise('/catalog/shoes/?page=2'));
        $this->assertSame('/', UrlNormaliser::normalise(''));

        // Unlike the key: a rule is compared against what an editor typed, case and all.
        $this->assertSame('/Catalog', UrlNormaliser::normalise('/Catalog'));
    }

    #[Test]
    public function joining_drops_the_empty_segments(): void
    {
        // A tree root with no slug of its own, or a prefix a project configured away.
        $this->assertSame('about/mission', UrlNormaliser::join('', 'About', 'Mission'));
        $this->assertSame('', UrlNormaliser::join('', ''));
    }
}
