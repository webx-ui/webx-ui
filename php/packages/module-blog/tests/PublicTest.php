<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Facades\Preview;

/**
 * What the site answers with, for each of the three kinds of address and for the feed.
 */
final class PublicTest extends TestCase
{
    #[Test]
    public function a_published_article_prints_its_blocks(): void
    {
        $this->blockType('text', '<p>{{ $text }}</p>');

        $article = $this->article('how-to-choose', published: false);
        $article->blocks = [['key' => 'a', 'type' => 'text', 'values' => ['text' => 'Hello']]];
        $article->save();
        $article->publish();

        $this->get('/blog/how-to-choose')->assertOk()->assertSee('<p>Hello</p>', false);
    }

    #[Test]
    public function an_article_that_was_never_published_is_a_404(): void
    {
        $this->article('how-to-choose', published: false);

        $this->get('/blog/how-to-choose')->assertNotFound();
    }

    #[Test]
    public function the_same_article_opens_under_a_preview_token(): void
    {
        $this->blockType('text', '<p>{{ $text }}</p>');

        $article = $this->article('how-to-choose', published: false);
        $article->saveDraft(['blocks' => [['key' => 'a', 'type' => 'text', 'values' => ['text' => 'Not live yet']]]]);

        $response = $this->get(Preview::url($article));

        $response->assertOk();
        $response->assertSee('<p>Not live yet</p>', false);
    }

    #[Test]
    public function an_article_taken_off_the_site_stops_answering(): void
    {
        $article = $this->article('how-to-choose');

        $this->get('/blog/how-to-choose')->assertOk();

        $article->unpublish();

        $this->get('/blog/how-to-choose')->assertNotFound();
    }

    #[Test]
    public function a_rubric_lists_the_articles_filed_under_it(): void
    {
        $rubric = $this->rubric('repairs');

        $mine = $this->article('changing-a-belt');
        $mine->rubrics()->attach($rubric);

        $this->article('something-else');

        $response = $this->get('/blog/repairs');

        $response->assertOk();
        $response->assertSee('Changing a belt');
        $response->assertDontSee('Something else');
    }

    #[Test]
    public function a_hidden_rubric_is_a_404_and_its_articles_go_on_answering(): void
    {
        $rubric = $this->rubric('repairs', visible: false);

        $article = $this->article('changing-a-belt');
        $article->rubrics()->attach($rubric);

        $this->get('/blog/repairs')->assertNotFound();

        // They are not its property: an article is in three rubrics at once, and putting one of
        // them away must not take the article off the site with it (§3).
        $this->get('/blog/changing-a-belt')->assertOk();
    }

    #[Test]
    public function a_tag_lists_its_articles_under_the_tag_prefix(): void
    {
        $tag = $this->tag('belts');

        $article = $this->article('changing-a-belt');
        $article->tags()->attach($tag);

        $response = $this->get('/blog/tag/belts');

        $response->assertOk();
        $response->assertSee('Changing a belt');
    }

    #[Test]
    public function the_feed_lists_everything_and_pins_go_on_top(): void
    {
        $this->article('older');
        $pinned = $this->article('the-pinned-one');
        $pinned->pinned = true;
        $pinned->save();
        $this->article('newest');

        $response = $this->get('/blog');

        $response->assertOk();
        $response->assertSeeInOrder(['The pinned one', 'Newest', 'Older']);
    }

    #[Test]
    public function the_feed_paginates_with_a_query_string(): void
    {
        // Two pages of three, so that page two holds exactly the one that did not fit.
        config()->set('webx-blog.per_page', 3);

        foreach (['one', 'two', 'three', 'four'] as $slug) {
            $this->article($slug);
        }

        $this->get('/blog')->assertOk()->assertDontSee('>One<', false);
        $this->get('/blog?page=2')->assertOk()->assertSee('One');
    }

    #[Test]
    public function the_rss_carries_the_main_rubric_as_the_category(): void
    {
        $first = $this->rubric('repairs');
        $second = $this->rubric('news');

        $article = $this->article('changing-a-belt');
        $article->rubrics()->attach([$first->getKey() => ['position' => 0], $second->getKey() => ['position' => 1]]);

        $response = $this->get('/blog/rss');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $response->assertSee('<category>Repairs</category>', false);
        $response->assertDontSee('<category>News</category>', false);
        $response->assertSee('/blog/changing-a-belt</link>', false);
    }

    #[Test]
    public function the_rss_is_a_well_formed_document(): void
    {
        $this->article('changing-a-belt');

        $body = $this->get('/blog/rss')->assertOk()->getContent();

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string((string) $body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($document, 'The RSS document did not parse as XML.');
    }

    #[Test]
    public function a_site_without_a_layout_gets_a_whole_document(): void
    {
        $body = $this->get($this->styledArticle())->assertOk()->getContent();

        $this->assertWholeDocument((string) $body);
        $this->assertStringNotContainsString('The site header', (string) $body);
    }

    #[Test]
    public function a_site_with_a_layout_gets_the_article_inside_it(): void
    {
        $this->siteLayout();

        $body = $this->get($this->styledArticle())->assertOk()->getContent();

        $this->assertWholeDocument((string) $body);
        $this->assertStringContainsString('The site header', (string) $body);
        $this->assertStringContainsString('The site footer', (string) $body);
    }

    #[Test]
    public function the_three_listings_stand_in_the_layout_too(): void
    {
        $this->siteLayout();

        $rubric = $this->rubric('repairs');
        $this->tag('belts');

        foreach (['/blog', $rubric->url(), '/blog/tag/belts'] as $url) {
            $body = (string) $this->get($url)->assertOk()->getContent();

            $this->assertStringStartsWith('<!doctype html', ltrim($body));
            $this->assertStringContainsString('The site header', $body, "{$url} did not stand in the layout.");
        }
    }

    /**
     * A published article with a block type that has styles of its own, at its address.
     *
     * The styles are the point: `@webxBlocks` prints the bundle of what was rendered, so it only
     * has anything to print if the content was rendered before the head — which is what the line
     * at the top of the view is for, and what a layout between the two could have undone.
     */
    private function styledArticle(): string
    {
        $type = $this->blockType('text', '<p class="b-text">{{ $text }}</p>');
        $type->saveVersion(['styles' => '.b-text { color: rebeccapurple; }']);
        $type->publish();

        $article = $this->article('changing-a-belt', published: false);
        $article->blocks = [['key' => 'a', 'type' => 'text', 'values' => ['text' => 'Hello']]];
        $article->save();
        $article->publish();

        return '/blog/changing-a-belt';
    }

    /** The site's layout, named the way its configuration would name it. */
    private function siteLayout(): void
    {
        config()->set('webx-blog.layout', 'site::layout');
    }

    /** A document either way: doctype, one head with everything in it, the content in the body. */
    private function assertWholeDocument(string $body): void
    {
        $this->assertStringStartsWith('<!doctype html', ltrim($body));

        $head = substr($body, 0, (int) strpos($body, '</head>'));

        $this->assertStringContainsString('<title>Changing a belt</title>', $head, 'The SEO head did not reach the layout.');
        $this->assertStringContainsString('rel="stylesheet"', $head, 'The block styles did not reach the layout.');
        $this->assertStringContainsString('<p class="b-text">Hello</p>', $body);
    }
}
