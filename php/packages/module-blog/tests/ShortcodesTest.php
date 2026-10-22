<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Author;

/**
 * The texts of the blog that take shortcodes — an article's lead, a rubric's lead, an author's
 * bio — reach the visitor resolved wherever the site prints them: as HTML on a page and in a
 * card, as plain text in the JSON-LD and the RSS. The record keeps the bracket.
 */
final class ShortcodesTest extends TestCase
{
    private const LINK = '<a href="tel:+80012345678">+800 1234 5678</a>';

    protected function setUp(): void
    {
        parent::setUp();

        app(Shortcodes::class)->register('phone', self::LINK, plain: '+800 1234 5678');
    }

    #[Test]
    public function a_rubric_page_resolves_its_lead_and_the_leads_of_its_cards(): void
    {
        $rubric = $this->rubric('repairs');
        $rubric->update(['lead' => '<p>Ask us: [phone]</p>']);

        $article = $this->withLead('changing-a-belt');
        $article->rubrics()->attach($rubric);

        $body = (string) $this->get('/blog/repairs')->assertOk()->getContent();

        $this->assertStringContainsString('<p>Ask us: '.self::LINK.'</p>', $body);
        $this->assertStringContainsString('<p class="webx-blog-card__lead">Call '.self::LINK.' today.</p>', $body);
        $this->assertStringNotContainsString('[phone]', $body);

        $this->assertSame('Call [phone] today.', $article->refresh()->getTranslation('lead', 'en'), 'the record keeps the bracket');
    }

    #[Test]
    public function the_feed_card_resolves_the_lead(): void
    {
        $this->withLead('changing-a-belt');

        $body = (string) $this->get('/blog')->assertOk()->getContent();

        $this->assertStringContainsString('Call '.self::LINK.' today.', $body);
        $this->assertStringNotContainsString('[phone]', $body);
    }

    #[Test]
    public function the_json_ld_and_the_meta_of_an_article_carry_the_plain_text(): void
    {
        $this->withLead('changing-a-belt');

        $body = (string) $this->get('/blog/changing-a-belt')->assertOk()->getContent();

        $this->assertStringContainsString('"description":"Call +800 1234 5678 today."', $body);
        $this->assertStringContainsString('content="Call +800 1234 5678 today."', $body);
        $this->assertStringNotContainsString('[phone]', $body);
        $this->assertStringNotContainsString('tel:', $body, 'no link where only text can go');
    }

    #[Test]
    public function the_rss_carries_the_plain_text(): void
    {
        $this->withLead('changing-a-belt');

        $body = (string) $this->get('/blog/rss')->assertOk()->getContent();

        $this->assertStringContainsString('<description>Call +800 1234 5678 today.</description>', $body);
        $this->assertStringNotContainsString('[phone]', $body);
    }

    #[Test]
    public function an_author_page_resolves_the_bio(): void
    {
        config()->set('webx-blog.authors', true);

        $user = CmsUser::query()->create([
            'name' => 'Login Anna',
            'email' => 'anna@example.test',
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);
        Author::query()->create([
            'user_id' => $user->getKey(),
            'title' => 'Anna Writer',
            'slug' => 'anna',
            'bio' => '<p>Questions: [phone]</p>',
            'is_public' => true,
        ]);
        $article = new Article(['title' => 'Changing a belt', 'slug' => 'changing-a-belt', 'author_id' => $user->getKey()]);
        $article->save();
        $article->publish();

        $body = (string) $this->get('/blog/author/anna')->assertOk()->getContent();

        $this->assertStringContainsString('<p>Questions: '.self::LINK.'</p>', $body);
        $this->assertStringNotContainsString('[phone]', $body);
    }

    /** An article whose lead holds a shortcode, written before it goes live. */
    private function withLead(string $slug): Article
    {
        $article = new Article(['title' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug, 'lead' => 'Call [phone] today.']);
        $article->save();
        $article->publish();

        return $article->refresh();
    }
}
