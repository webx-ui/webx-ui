<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Pages\Panel\PageForm;

class PublicTest extends TestCase
{
    #[Test]
    public function a_published_page_prints_its_blocks(): void
    {
        $this->blockType('text', '<p>{{ $text }}</p>');

        $page = $this->page('about', published: false);
        $page->blocks = [['key' => 'a', 'type' => 'text', 'values' => ['text' => 'Hello']]];
        $page->save();
        $page->publish();

        $this->get('/about')->assertOk()->assertSee('<p>Hello</p>', false);
    }

    #[Test]
    public function a_page_that_was_never_published_is_a_404(): void
    {
        $this->page('about', published: false);

        $this->get('/about')->assertNotFound();
    }

    #[Test]
    public function the_same_page_opens_under_a_preview_token(): void
    {
        $this->blockType('text', '<p>{{ $text }}</p>');

        $page = $this->page('about', published: false);
        $page->saveDraft(['blocks' => [['key' => 'a', 'type' => 'text', 'values' => ['text' => 'Not live yet']]]]);

        $response = $this->get(Preview::url($page));

        $response->assertOk();
        $response->assertSee('<p>Not live yet</p>', false);
    }

    #[Test]
    public function a_page_taken_off_the_site_stops_answering(): void
    {
        $page = $this->page('about');

        $this->get('/about')->assertOk();

        $page->unpublish();

        $this->get('/about')->assertNotFound();
    }

    #[Test]
    public function a_deleted_page_stops_answering_and_answers_again_when_restored(): void
    {
        $page = $this->page('about');
        $page->delete();

        $this->get('/about')->assertNotFound();

        $page->restoreBranch();

        $this->get('/about')->assertOk();
    }

    #[Test]
    public function the_home_page_answers_the_front_page(): void
    {
        $home = $this->home();
        $home->publish();

        $this->get('/')->assertOk();
    }

    #[Test]
    public function a_site_without_a_layout_gets_a_whole_document(): void
    {
        $body = $this->get($this->styledPage())->assertOk()->getContent();

        $this->assertWholeDocument((string) $body);
        $this->assertStringNotContainsString('The site header', (string) $body);
    }

    #[Test]
    public function a_site_with_a_layout_gets_the_page_inside_it(): void
    {
        $this->siteLayout();

        $body = $this->get($this->styledPage())->assertOk()->getContent();

        $this->assertWholeDocument((string) $body);
        $this->assertStringContainsString('The site header', (string) $body);
        $this->assertStringContainsString('The site footer', (string) $body);
    }

    /**
     * A published page with a block type that has styles of its own, at its address.
     *
     * The styles are the point: `@webxBlocks` prints the bundle of what was rendered, so it only
     * has anything to print if the content was rendered before the head — which is what the line
     * at the top of the view is for, and what a layout between the two could have undone.
     */
    private function styledPage(): string
    {
        $type = $this->blockType('text', '<p class="b-text">{{ $text }}</p>');
        $type->saveVersion(['styles' => '.b-text { color: rebeccapurple; }']);
        $type->publish();

        $page = $this->page('about', published: false);
        $page->blocks = [['key' => 'a', 'type' => 'text', 'values' => ['text' => 'Hello']]];
        $page->save();
        $page->publish();

        app(PageForm::class)->save($page, ['seo' => ['title' => ['en' => 'About us']]]);

        return '/about';
    }

    /** The site's layout, named the way its configuration would name it. */
    private function siteLayout(): void
    {
        config()->set('webx-pages.layout', 'site::layout');
    }

    /** A document either way: doctype, one head with everything in it, the content in the body. */
    private function assertWholeDocument(string $body): void
    {
        $this->assertStringStartsWith('<!doctype html', ltrim($body));

        $head = substr($body, 0, (int) strpos($body, '</head>'));

        $this->assertStringContainsString('<title>About us</title>', $head, 'The SEO head did not reach the layout.');
        $this->assertStringContainsString('rel="stylesheet"', $head, 'The block styles did not reach the layout.');
        $this->assertStringContainsString('<p class="b-text">Hello</p>', $body);
    }
}
