<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Facades\Preview;

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
}
