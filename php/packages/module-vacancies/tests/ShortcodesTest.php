<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcodes;

/**
 * A shortcode an editor typed into a vacancy is what the visitor reads: its HTML on the page, on
 * the card and in the HTML of the posting's description, its plain rendering in the posting's
 * text properties — never the bracket.
 */
final class ShortcodesTest extends TestCase
{
    private const LINK = '<a href="tel:+80012345678">+800 1234 5678</a>';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));
        $this->app->make(Shortcodes::class)->register('phone', self::LINK, plain: '+800 1234 5678');
    }

    #[Test]
    public function the_page_prints_the_shortcodes_of_the_lead_the_description_and_the_lists(): void
    {
        $this->organisation();
        $this->vacancy('php-developer', attributes: [
            'lead' => 'Questions: [phone].',
            'description' => '<p>Call the team at [phone].</p>',
            'duties' => [['text' => ['en' => 'Answer [phone]']]],
        ]);

        $page = (string) $this->get('/careers/php-developer')->assertOk()->getContent();

        $this->assertStringContainsString('Questions: '.self::LINK.'.', $page);
        $this->assertStringContainsString('<p>Call the team at '.self::LINK.'.</p>', $page);
        $this->assertStringContainsString('<li>Answer '.self::LINK.'</li>', $page);
        $this->assertStringNotContainsString('[phone]', $page);

        $posting = $this->jsonLd($page, 'JobPosting');
        $this->assertIsArray($posting);
        $this->assertStringContainsString('<p>Call the team at '.self::LINK.'.</p>', $posting['description']);
        $this->assertStringContainsString('<li>Answer '.self::LINK.'</li>', $posting['description']);
        $this->assertSame('Answer +800 1234 5678', $posting['responsibilities']);
    }

    #[Test]
    public function the_card_in_the_list_prints_the_lead(): void
    {
        $this->vacancy('php-developer', attributes: ['lead' => 'Questions: [phone].']);

        $page = (string) $this->get('/careers')->assertOk()->getContent();

        $this->assertStringContainsString('<p class="wx-vacancies__lead">Questions: '.self::LINK.'.</p>', $page);
        $this->assertStringNotContainsString('[phone]', $page);
    }

    #[Test]
    public function the_meta_description_and_the_record_keep_to_text(): void
    {
        $vacancy = $this->vacancy('php-developer', attributes: ['lead' => 'Questions: [phone].']);

        $page = (string) $this->get('/careers/php-developer')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="description" content="Questions: +800 1234 5678.">', $page);
        $this->assertSame('Questions: [phone].', $vacancy->refresh()->text('lead', 'en'));
    }
}
