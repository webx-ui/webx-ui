<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

/**
 * What a template gets: the three kinds of target, the language, and the four flags.
 */
class RenderTest extends TestCase
{
    public function test_the_three_kinds_of_target(): void
    {
        $thing = $this->thing('services', 'Services');
        $header = $this->menu('header');

        $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $thing->getKey()], $header);
        $this->item(['title' => ['en' => 'Blog'], 'target' => 'url', 'url' => '/blog'], $header);
        $this->item(['title' => ['en' => 'Legal'], 'target' => 'none', 'is_heading' => true], $header);

        $tree = menu('header');

        $this->assertSame(['Services', 'Blog', 'Legal'], $this->labels('header'));
        $this->assertSame(url('/services'), $tree[0]->url);
        $this->assertSame(url('/blog'), $tree[1]->url);
        $this->assertNull($tree[2]->url, 'An item that goes nowhere has no address at all.');
        $this->assertTrue($tree[2]->isHeading);
    }

    public function test_an_item_is_called_what_the_entity_is_called_until_it_is_given_a_label(): void
    {
        $thing = $this->thing('work-with-katia', 'Work With Katia');
        $header = $this->menu('header');

        $item = $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $thing->getKey()], $header);

        $this->assertSame(['Work With Katia'], $this->labels('header'));

        $item->update(['title' => ['en' => 'Work']]);

        $this->assertSame(['Work'], $this->labels('header'));
    }

    public function test_an_entity_the_site_would_not_show_is_left_out(): void
    {
        $thing = $this->thing('draft', 'Draft', published: false);
        $header = $this->menu('header');

        $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $thing->getKey()], $header);

        // The address exists — a slug is content, and it is written before anything is
        // published. Availability is the module's answer, not the registry's.
        $this->assertSame([], $this->labels('header'));

        $thing->update(['published' => true]);

        $this->assertSame(['Draft'], $this->labels('header'));
    }

    public function test_an_entity_that_is_gone_is_left_out(): void
    {
        $thing = $this->thing('gone', 'Gone');
        $header = $this->menu('header');

        $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $thing->getKey()], $header);
        $this->item(['title' => ['en' => 'Blog'], 'target' => 'url', 'url' => '/blog'], $header);

        $thing->delete();

        $this->assertSame(['Blog'], $this->labels('header'));
    }

    public function test_a_path_gets_the_language_prefix_and_never_a_second_one(): void
    {
        $this->useLocales('en', 'uk');
        $header = $this->menu('header');

        $this->item(['title' => ['en' => 'Account', 'uk' => 'Акаунт'], 'target' => 'url', 'url' => '/account'], $header);
        $this->item(['title' => ['en' => 'Help', 'uk' => 'Допомога'], 'target' => 'url', 'url' => '/uk/help'], $header);
        $this->item(['title' => ['en' => 'Partner', 'uk' => 'Партнер'], 'target' => 'url', 'url' => 'https://example.org/x'], $header);

        $english = menu('header', 'en');
        $ukrainian = menu('header', 'uk');

        $this->assertSame(url('/account'), $english[0]->url);
        $this->assertSame(url('/uk/account'), $ukrainian[0]->url);

        // Written with the prefix already: the editor copied it out of the browser.
        $this->assertSame(url('/uk/help'), $ukrainian[1]->url);

        $this->assertSame('https://example.org/x', $ukrainian[2]->url, 'Somebody else’s address is left alone.');
    }

    public function test_an_entity_answers_with_the_address_of_the_language_being_rendered(): void
    {
        $this->useLocales('en', 'uk');
        $thing = $this->thing('services', ['en' => 'Services', 'uk' => 'Послуги']);
        $header = $this->menu('header');

        $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $thing->getKey()], $header);

        $this->assertSame(url('/services'), menu('header', 'en')[0]->url);
        $this->assertSame(url('/uk/services'), menu('header', 'uk')[0]->url);
        $this->assertSame('Послуги', menu('header', 'uk')[0]->label);
    }

    public function test_an_item_nobody_has_translated_is_left_out_of_that_language(): void
    {
        $this->useLocales('en', 'uk');
        $header = $this->menu('header');

        $this->item(['title' => ['en' => 'Blog'], 'target' => 'url', 'url' => '/blog'], $header);
        $this->item(['title' => ['en' => 'About', 'uk' => 'Про нас'], 'target' => 'url', 'url' => '/about'], $header);

        $this->assertSame(['Blog', 'About'], $this->labels('header', 'en'));
        $this->assertSame(['Про нас'], $this->labels('header', 'uk'), 'An empty link is worse than a missing one.');
    }

    public function test_locales_hide_an_item_from_the_languages_it_does_not_name(): void
    {
        $this->useLocales('en', 'uk');
        $header = $this->menu('header');

        $this->item([
            'title' => ['en' => 'Careers', 'uk' => 'Вакансії'],
            'target' => 'url',
            'url' => '/careers',
            'locales' => ['en'],
        ], $header);

        $this->assertSame(['Careers'], $this->labels('header', 'en'));
        $this->assertSame([], $this->labels('header', 'uk'));
    }

    public function test_a_new_tab_always_carries_noopener(): void
    {
        $header = $this->menu('header');

        $this->item([
            'title' => ['en' => 'Partner'],
            'target' => 'url',
            'url' => 'https://example.org/x',
            'new_tab' => true,
            'rel' => ['nofollow', 'sponsored'],
        ], $header);

        $this->item([
            'title' => ['en' => 'Blog'],
            'target' => 'url',
            'url' => '/blog',
            'rel' => ['nofollow'],
        ], $header);

        $tree = menu('header');

        $this->assertSame('nofollow sponsored noopener noreferrer', $tree[0]->rel);
        $this->assertSame(
            ['href' => 'https://example.org/x', 'target' => '_blank', 'rel' => 'nofollow sponsored noopener noreferrer'],
            $tree[0]->attrs(),
        );

        // No new tab, no protection to add — and none invented.
        $this->assertSame('nofollow', $tree[1]->rel);
        $this->assertArrayNotHasKey('target', $tree[1]->attrs());
    }

    public function test_an_anchor_is_appended_to_either_kind_of_target(): void
    {
        $thing = $this->thing('about', 'About');
        $header = $this->menu('header');

        $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $thing->getKey(), 'hash' => 'team'], $header);
        $this->item(['title' => ['en' => 'Top'], 'target' => 'url', 'hash' => 'top'], $header);

        $tree = menu('header');

        $this->assertSame(url('/about').'#team', $tree[0]->url);
        $this->assertSame('#top', $tree[1]->url, 'An anchor with no address points at this very page.');
    }

    public function test_a_hidden_item_is_left_out(): void
    {
        $header = $this->menu('header');

        $this->item(['title' => ['en' => 'Blog'], 'target' => 'url', 'url' => '/blog', 'visible' => false], $header);
        $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about'], $header);

        $this->assertSame(['About'], $this->labels('header'));
    }

    public function test_the_variant_travels_as_it_was_written(): void
    {
        $header = $this->menu('header');

        $this->item(['title' => ['en' => 'Book'], 'target' => 'url', 'url' => '/book', 'variant' => 'button'], $header);
        $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about'], $header);

        $this->assertSame(['button', 'link'], menu('header')->map(static fn ($link): string => $link->variant)->all());
    }

    public function test_the_blade_component_prints_the_menu(): void
    {
        $header = $this->menu('header');

        $services = $this->item(['title' => ['en' => 'Services'], 'target' => 'none', 'is_heading' => true], $header);
        $this->item(['title' => ['en' => 'Design'], 'target' => 'url', 'url' => '/design'], $header, $services);
        $this->item([
            'title' => ['en' => 'Partner'],
            'target' => 'url',
            'url' => 'https://example.org/x',
            'new_tab' => true,
        ], $header);

        $html = (string) $this->blade('<x-webx-menu::menu name="header" />');

        $this->assertStringContainsString('<ul class="wx-menu">', $html);
        $this->assertStringContainsString('wx-menu__item--heading', $html);
        $this->assertStringContainsString('href="'.url('/design').'"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }
}
