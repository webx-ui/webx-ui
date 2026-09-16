<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\PageForm;
use WebxUi\Seo\Models\SeoUrl;

/**
 * The SEO card on the page editor, end to end: it arrives on the screen from the other package,
 * a save puts it in `seo_meta` rather than in the draft, and the live page prints it.
 *
 * The last one is the test that matters. Everything before it can be right while the `<head>`
 * of the actual page still says nothing — that was the state this module shipped in.
 */
final class SeoTest extends TestCase
{
    #[Test]
    public function the_card_arrives_on_the_editor_from_the_seo_module(): void
    {
        $ids = array_column(app(ScreenRegistry::class)->fields(PageForm::SCREEN), 'type', 'name');

        $this->assertSame('wx-seo', $ids['seo'] ?? null);
    }

    #[Test]
    public function a_saved_card_reaches_the_head_of_the_live_page(): void
    {
        $page = $this->page('about');

        $this->save($page, ['title' => ['en' => 'About us'], 'description' => ['en' => 'Who we are']]);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('<title>About us</title>', false);
        $response->assertSee('<meta name="description" content="Who we are">', false);
        $response->assertSee('<meta property="og:title" content="About us">', false);
    }

    #[Test]
    public function the_card_is_not_part_of_the_draft(): void
    {
        $page = $this->page('about');

        $this->save($page, ['title' => ['en' => 'About us']]);

        $page->refresh();

        // Saved the moment it was saved, on a page that is published and on one that is not: a
        // description that only reaches search engines at the next publication is the kind of
        // thing an editor finds out about from a search engine.
        $this->assertSame(['en' => 'About us'], $page->seoValue()['title'] ?? null);
        $this->assertArrayNotHasKey('seo', $page->draft ?? []);
        $this->assertHead('/about', '<title>About us</title>');
    }

    #[Test]
    public function a_rule_written_for_the_address_still_wins(): void
    {
        SeoUrl::query()->create([
            'match_type' => 'exact',
            'pattern' => '/about',
            'title' => ['en' => 'What the rule says'],
        ]);

        $page = $this->page('about');
        $this->save($page, ['title' => ['en' => 'About us'], 'description' => ['en' => 'Who we are']]);

        $response = $this->get('/about');

        $response->assertSee('<title>What the rule says</title>', false);
        // And only the field the rule filled in.
        $response->assertSee('<meta name="description" content="Who we are">', false);
    }

    #[Test]
    public function saving_another_tab_leaves_the_card_alone(): void
    {
        $page = $this->page('about');
        $this->save($page, ['title' => ['en' => 'About us']]);

        // The form sends the tab that was edited, not the whole screen — a card nobody opened
        // must not be emptied by a rename.
        app(PageForm::class)->save($page, ['title' => ['en' => 'About']]);

        $this->assertHead('/about', '<title>About us</title>');
    }

    /**
     * Fill the card in through the form, the way the panel does.
     *
     * @param  array<string, mixed>  $seo
     */
    private function save(Page $page, array $seo): void
    {
        app(PageForm::class)->save($page, ['seo' => $seo]);
    }

    /** Named so as not to collide with anything the base class already answers to. */
    private function assertHead(string $url, string $needle): void
    {
        $this->get($url)->assertOk()->assertSee($needle, false);
    }
}
