<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Rendering\Cards;

/**
 * The panel offers the site's shortcodes in a product's summary and description and in a
 * category's description; the storefront prints them as what they stand for. A page that printed
 * the field as stored would show the visitor a bare `[phone]`.
 */
final class ShortcodesTest extends TestCase
{
    private Category $laptops;

    protected function setUp(): void
    {
        parent::setUp();

        app(Shortcodes::class)->register('phone', '<a href="tel:+80012345678">+800 1234 5678</a>', plain: '+800 1234 5678');

        $this->laptops = $this->category('laptops');
    }

    #[Test]
    public function the_product_page_prints_the_shortcodes_in_the_summary_and_the_description(): void
    {
        $product = $this->product('Desk lamp', $this->laptops, [
            'summary' => 'Ask us: [phone]',
            'description' => '<p>Call <strong>[phone]</strong> or write [[phone]] in the form.</p>',
        ]);

        $body = (string) $this->get("/desk-lamp-{$product->id}")->assertOk()->getContent();

        $this->assertStringContainsString('<p class="webx-catalog-product__summary">Ask us: <a href="tel:+80012345678">+800 1234 5678</a></p>', $body);
        $this->assertStringContainsString('<p>Call <strong><a href="tel:+80012345678">+800 1234 5678</a></strong> or write [phone] in the form.</p>', $body);
        // The product's markup is text: the shortcode is its plain rendering there.
        $this->assertStringContainsString('"description":"Ask us: +800 1234 5678"', $body);

        // The record keeps the bracket — the panel and the agents read what was typed.
        $this->assertSame('Ask us: [phone]', $product->fresh()?->getTranslation('summary', 'en'));
    }

    #[Test]
    public function a_text_with_no_shortcode_is_printed_as_before(): void
    {
        $product = $this->product('Desk lamp', $this->laptops, ['summary' => 'Bright & small', 'description' => '<p>Plain [1] text.</p>']);

        $body = (string) $this->get("/desk-lamp-{$product->id}")->assertOk()->getContent();

        $this->assertStringContainsString('<p class="webx-catalog-product__summary">Bright &amp; small</p>', $body);
        $this->assertStringContainsString('<p>Plain [1] text.</p>', $body);
    }

    #[Test]
    public function the_category_page_prints_the_shortcodes_in_its_description(): void
    {
        $this->laptops->forceFill(['description' => ['en' => '<p>Orders by phone: [phone]</p>']])->save();

        $this->get('/laptops')
            ->assertOk()
            ->assertSee('<p>Orders by phone: <a href="tel:+80012345678">+800 1234 5678</a></p>', false)
            ->assertDontSee('[phone]', false);
    }

    #[Test]
    public function a_card_in_a_list_carries_the_summary_with_its_shortcodes_printed(): void
    {
        $product = $this->product('Desk lamp', $this->laptops, ['summary' => 'Ask us: [phone]']);

        $card = app(Cards::class)->products([$product], 'en')[0];

        $this->assertSame('Ask us: <a href="tel:+80012345678">+800 1234 5678</a>', e($card['summary']));
        // Sent to a script as JSON, it is the text.
        $this->assertSame('"Ask us: +800 1234 5678"', json_encode($card['summary']));
    }
}
