<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Press\Models\Outlet;

/**
 * The summary and the excerpt are offered the shortcodes in the panel, so every place that prints
 * them resolves them: the page as HTML, the meta description and the markup as text. The record
 * keeps the bracket.
 */
final class ShortcodesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Shortcodes::class)->register('phone', '<a href="tel:+80012345678">+800 1234 5678</a>', plain: '+800 1234 5678');
    }

    #[Test]
    public function the_page_prints_them_resolved_and_the_meta_and_markup_as_text(): void
    {
        $outlet = $this->outlet('Tatler', [
            $this->row('To the day', ['published_on' => '2023-08-12', 'excerpt' => ['en' => 'Quoted: [phone]']]),
        ], values: ['summary' => ['en' => 'Desk: [phone]']]);

        $page = (string) $this->get('/press/tatler')->assertOk()->getContent();

        $this->assertStringContainsString('Desk: <a href="tel:+80012345678">+800 1234 5678</a>', $page);
        $this->assertStringContainsString('Quoted: <a href="tel:+80012345678">+800 1234 5678</a>', $page);
        $this->assertStringContainsString('<meta name="description" content="Desk: +800 1234 5678">', $page);
        $this->assertStringNotContainsString('[phone]', $page);

        $list = $this->jsonLd($page, 'ItemList');
        $this->assertSame('Quoted: +800 1234 5678', $list['itemListElement'][0]['item']['description']);

        $outlet = Outlet::query()->with('articles')->findOrFail($outlet->getKey());
        $this->assertSame('Desk: [phone]', $outlet->getTranslation('summary', 'en'));
        $this->assertSame('Quoted: [phone]', $outlet->articles->first()?->getTranslation('excerpt', 'en'));
    }

    #[Test]
    public function the_feed_block_prints_the_excerpt_resolved(): void
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['press']])->assertSuccessful();
        $this->outlet('Tatler', [
            $this->row('To the day', ['published_on' => '2023-08-12', 'excerpt' => ['en' => 'Quoted: [phone]']]),
        ]);

        $html = (string) $this->app->make(Renderer::class)->render([['key' => 'k1', 'type' => 'press-articles', 'values' => []]]);

        $this->assertStringContainsString('<p class="b-press-articles__excerpt">Quoted: <a href="tel:+80012345678">+800 1234 5678</a></p>', $html);
        $this->assertStringNotContainsString('[phone]', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonLd(string $page, string $type): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $page, $matches);

        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true);

            if (is_array($decoded) && ($decoded['@type'] ?? null) === $type) {
                return $decoded;
            }
        }

        $this->fail("No {$type} markup on the page.");
    }
}
