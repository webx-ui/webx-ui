<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

/**
 * Highlighting (§6): here, inside here, or neither.
 *
 * Worked out after the cache and on every request, because it is the one thing about a menu
 * that differs from page to page.
 */
class ActiveTest extends TestCase
{
    public function test_the_item_of_the_page_being_looked_at_is_both_current_and_active(): void
    {
        $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about']);
        $this->standingOn('/about');

        $link = menu('header')->first();

        $this->assertTrue($link->isCurrent());
        $this->assertTrue($link->isActive());
    }

    public function test_a_page_below_an_item_makes_it_active_but_not_current(): void
    {
        $this->item(['title' => ['en' => 'Services'], 'target' => 'url', 'url' => '/services']);
        $this->standingOn('/services/design');

        $link = menu('header')->first();

        $this->assertTrue($link->isActive());
        $this->assertFalse($link->isCurrent(), 'We are inside it, not on it.');
    }

    public function test_a_prefix_is_a_whole_segment_and_not_a_string(): void
    {
        $this->item(['title' => ['en' => 'Services'], 'target' => 'url', 'url' => '/services']);
        $this->standingOn('/services-and-prices');

        $this->assertFalse(menu('header')->first()->isActive());
    }

    public function test_an_item_is_active_when_one_of_its_children_is(): void
    {
        $header = $this->menu('header');

        $services = $this->item(['title' => ['en' => 'Services'], 'target' => 'none', 'is_heading' => true], $header);
        $this->item(['title' => ['en' => 'Design'], 'target' => 'url', 'url' => '/design'], $header, $services);

        $this->standingOn('/design');

        $link = menu('header')->first();

        $this->assertTrue($link->isActive(), 'A heading goes nowhere and is still where we are.');
        $this->assertFalse($link->isCurrent());
        $this->assertSame('Design', menu('header')->active()?->label);
    }

    /**
     * The home page is the exception to the prefix rule, and it has to be: its path is the
     * empty string, which is a prefix of every address on the site. Getting this wrong shows up
     * as two highlighted items, and nobody reports that for a month.
     */
    public function test_the_home_page_is_not_active_everywhere(): void
    {
        $home = $this->thing('', 'Home');
        $header = $this->menu('header');

        $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $home->getKey()], $header);
        $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about'], $header);

        $this->standingOn('/about');

        $tree = menu('header');

        $this->assertFalse($tree[0]->isActive(), 'The front page is not every page.');
        $this->assertTrue($tree[1]->isActive());

        $this->standingOn('/');

        $tree = menu('header');

        $this->assertTrue($tree[0]->isActive());
        $this->assertTrue($tree[0]->isCurrent());
        $this->assertFalse($tree[1]->isActive());
    }

    public function test_an_address_on_our_own_host_is_compared_by_its_path(): void
    {
        $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => url('/about')]);
        $this->standingOn('/about');

        $this->assertTrue(menu('header')->first()->isCurrent());
    }

    public function test_somebody_elses_address_is_never_active(): void
    {
        $this->item(['title' => ['en' => 'Partner'], 'target' => 'url', 'url' => 'https://example.org/about']);
        $this->standingOn('/about');

        $link = menu('header')->first();

        $this->assertFalse($link->isActive());
        $this->assertFalse($link->isCurrent());
    }

    public function test_an_item_that_goes_nowhere_is_not_active_on_its_own(): void
    {
        $this->item(['title' => ['en' => 'Legal'], 'target' => 'none', 'is_heading' => true]);
        $this->standingOn('/legal');

        $this->assertFalse(menu('header')->first()->isActive());
    }
}
