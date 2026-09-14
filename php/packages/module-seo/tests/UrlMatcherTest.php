<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Rendering\UrlNormaliser;

final class UrlMatcherTest extends TestCase
{
    #[Test]
    public function it_spells_an_address_one_way(): void
    {
        $this->assertSame('/catalog/shoes', UrlNormaliser::normalise('/catalog/shoes/'));
        $this->assertSame('/catalog/shoes', UrlNormaliser::normalise('catalog/shoes'));
        $this->assertSame('/', UrlNormaliser::normalise('/'));
        $this->assertSame('/', UrlNormaliser::normalise(''));
        $this->assertSame('/catalog/shoes?page=2', UrlNormaliser::normalise('/catalog/shoes/?page=2'));
        $this->assertSame('/catalog', UrlNormaliser::normalise('https://example.test/catalog/#reviews'));
    }

    #[Test]
    public function the_query_string_is_part_of_the_address(): void
    {
        // Pages of filters and pagination are half of what rules are written for; dropping the
        // query would quietly make one rule cover a hundred different pages.
        $this->assertTrue(UrlMatcher::covers('exact', '/catalog?page=2', '/catalog?page=2'));
        $this->assertFalse(UrlMatcher::covers('exact', '/catalog', '/catalog?page=2'));
    }

    #[Test]
    public function a_single_star_stops_at_a_slash_and_a_double_one_does_not(): void
    {
        $this->assertTrue(UrlMatcher::covers('mask', '/catalog/*', '/catalog/shoes'));
        $this->assertFalse(UrlMatcher::covers('mask', '/catalog/*', '/catalog/shoes/red'));
        $this->assertTrue(UrlMatcher::covers('mask', '/catalog/**', '/catalog/shoes/red'));
    }

    #[Test]
    public function a_mask_can_be_put_back_into_a_target(): void
    {
        $this->assertSame(
            '/shop/shoes',
            UrlMatcher::target('mask', '/catalog/*', '/shop/$1', '/catalog/shoes'),
        );
    }

    #[Test]
    public function a_broken_regular_expression_never_matches_and_never_throws(): void
    {
        // The whole point: a rule saved before this was checked, or edited straight in the
        // database, must not be able to take the public side down.
        $this->assertFalse(UrlMatcher::isValidRegex('#^/catalog/(#'));
        $this->assertFalse(UrlMatcher::covers('regex', '#^/catalog/(#', '/catalog/shoes'));
        $this->assertTrue(UrlMatcher::covers('regex', '#^/catalog/(.+)$#', '/catalog/shoes'));
    }

    #[Test]
    public function exact_comes_before_mask_comes_before_regex(): void
    {
        $rows = UrlMatcher::ordered([
            ['id' => 1, 'match_type' => 'regex', 'pattern' => '#.#', 'priority' => 100],
            ['id' => 2, 'match_type' => 'mask', 'pattern' => '/**', 'priority' => 0],
            ['id' => 3, 'match_type' => 'exact', 'pattern' => '/', 'priority' => -100],
        ]);

        $this->assertSame([3, 2, 1], array_column($rows, 'id'));
    }

    #[Test]
    public function priority_decides_inside_a_group_and_the_older_rule_decides_a_tie(): void
    {
        $rows = UrlMatcher::ordered([
            ['id' => 7, 'match_type' => 'mask', 'pattern' => '/a/*', 'priority' => 5],
            ['id' => 3, 'match_type' => 'mask', 'pattern' => '/a/*', 'priority' => 5],
            ['id' => 9, 'match_type' => 'mask', 'pattern' => '/a/*', 'priority' => 50],
        ]);

        $this->assertSame([9, 3, 7], array_column($rows, 'id'));
    }
}
