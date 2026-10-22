<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcodes;

/**
 * A shortcode an editor typed into an event is what the visitor reads: its HTML on the page, its
 * plain rendering in the markup and the calendar file — never the bracket.
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
    public function the_page_prints_the_shortcodes_of_the_lead_the_description_and_the_highlights(): void
    {
        $this->event('spring-class', '2026-10-12 10:00:00', attributes: [
            'lead' => 'Book at [phone].',
            'description' => '<p>Questions? Call [phone].</p>',
            'highlights' => [['title' => ['en' => 'Hands on'], 'text' => ['en' => 'Ask the host: [phone]']]],
        ]);

        $page = (string) $this->get('/events/spring-class')->assertOk()->getContent();

        $this->assertStringContainsString('<p class="wx-event__lead">Book at '.self::LINK.'.</p>', $page);
        $this->assertStringContainsString('<p>Questions? Call '.self::LINK.'.</p>', $page);
        $this->assertStringContainsString('<p class="wx-event__highlight-text">Ask the host: '.self::LINK.'</p>', $page);
        $this->assertStringNotContainsString('[phone]', $page);

        $this->assertSame('Book at +800 1234 5678.', $this->jsonLd($page, 'Event')['description'] ?? null);
    }

    #[Test]
    public function the_calendar_file_has_the_plain_rendering(): void
    {
        $this->event('spring-class', '2026-10-12 10:00:00', attributes: ['lead' => 'Book at [phone]']);

        $body = (string) $this->get('/events/spring-class.ics')->assertOk()->getContent();

        $this->assertStringContainsString("\r\nDESCRIPTION:Book at +800 1234 5678\r\n", $body);
        $this->assertStringNotContainsString('[phone]', $body);
    }

    #[Test]
    public function a_category_page_prints_the_shortcodes_of_its_lead(): void
    {
        $category = $this->category('cooking-classes');
        $category->update(['lead' => '<p>Groups by [phone].</p>']);

        $page = (string) $this->get('/events/cooking-classes')->assertOk()->getContent();

        $this->assertStringContainsString('<p>Groups by '.self::LINK.'.</p>', $page);
        $this->assertStringNotContainsString('[phone]', $page);
    }

    #[Test]
    public function the_record_keeps_the_bracket(): void
    {
        $event = $this->event('spring-class', '2026-10-12 10:00:00', attributes: [
            'lead' => 'Book at [phone].',
            'description' => '<p>Call [phone].</p>',
        ]);

        $this->get('/events/spring-class')->assertOk();

        $event->refresh();
        $this->assertSame('Book at [phone].', $event->text('lead', 'en'));
        $this->assertSame('<p>Call [phone].</p>', $event->getTranslation('description', 'en'));
    }
}
