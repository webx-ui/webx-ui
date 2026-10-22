<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Services\Models\Service;

/**
 * The texts of the section that take shortcodes — a service's lead, a category's lead — reach the
 * visitor resolved wherever the site prints them: as HTML on a page, in a card and in the offered
 * block, as plain text in the JSON-LD. The record keeps the bracket.
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
    public function a_category_page_resolves_its_lead_and_the_leads_of_its_cards(): void
    {
        $category = $this->category('implants');
        $category->update(['lead' => '<p>Ask us: [phone]</p>']);
        $service = $this->withLead('crowns');
        $service->syncCategories([$category->getKey()]);

        $body = (string) $this->get('/services/implants')->assertOk()->getContent();

        $this->assertStringContainsString('<p>Ask us: '.self::LINK.'</p>', $body);
        $this->assertStringContainsString('<p class="webx-services-card__lead">Call '.self::LINK.' today.</p>', $body);
        $this->assertStringNotContainsString('[phone]', $body);

        $this->assertSame('Call [phone] today.', $service->refresh()->getTranslation('lead', 'en'), 'the record keeps the bracket');
    }

    #[Test]
    public function the_json_ld_and_the_meta_of_a_service_carry_the_plain_text(): void
    {
        $this->withLead('crowns');

        $body = (string) $this->get('/services/crowns')->assertOk()->getContent();

        $this->assertStringContainsString('"description":"Call +800 1234 5678 today."', $body);
        $this->assertStringContainsString('content="Call +800 1234 5678 today."', $body);
        $this->assertStringNotContainsString('[phone]', $body);
    }

    #[Test]
    public function the_cards_of_the_helper_and_the_offered_block_resolve_the_lead(): void
    {
        $this->withLead('crowns');

        $card = services()->first();
        $this->assertIsArray($card);
        $this->assertSame('Call '.self::LINK.' today.', (string) $card['lead']);

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['services']])->assertSuccessful();

        $html = (string) $this->app->make(Renderer::class)->render([[
            'key' => 'k1',
            'type' => 'services',
            'values' => ['title' => ['en' => 'What we do']],
        ]]);

        $this->assertStringContainsString('<p class="b-services__lead">Call '.self::LINK.' today.</p>', $html);
        $this->assertStringNotContainsString('[phone]', $html);
    }

    /** A service whose lead holds a shortcode, written before it goes live. */
    private function withLead(string $slug): Service
    {
        $service = new Service(['title' => ucfirst($slug), 'slug' => $slug, 'lead' => 'Call [phone] today.']);
        $service->save();
        $service->publish();

        return $service->refresh();
    }
}
