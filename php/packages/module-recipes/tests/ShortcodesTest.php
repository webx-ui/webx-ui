<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use Illuminate\Contracts\Support\Htmlable;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Recipes\Rendering\Cards;

/**
 * A shortcode an editor typed into a recipe is what the visitor reads: its HTML on the page and
 * on the card, its plain rendering in the `Recipe` markup — never the bracket.
 */
final class ShortcodesTest extends TestCase
{
    private const LINK = '<a href="tel:+80012345678">+800 1234 5678</a>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Shortcodes::class)->register('phone', self::LINK, plain: '+800 1234 5678');
    }

    #[Test]
    public function the_page_prints_the_shortcodes_of_the_lead_the_ingredients_and_the_method(): void
    {
        $this->recipe('porridge', attributes: [
            'lead' => 'Questions: [phone].',
            'ingredients' => '<ul><li>Oats from the shop at [phone]</li><li>Milk</li></ul>',
            'method' => '<ol><li>Boil the milk.</li><li>Stuck? Call [phone].</li></ol>',
        ]);

        $page = (string) $this->get('/recipes/porridge')->assertOk()->getContent();

        $this->assertStringContainsString('<p class="wx-recipe__lead">Questions: '.self::LINK.'.</p>', $page);
        $this->assertStringContainsString('<li>Oats from the shop at '.self::LINK.'</li>', $page);
        $this->assertStringContainsString('<li>Stuck? Call '.self::LINK.'.</li>', $page);
        $this->assertStringNotContainsString('[phone]', $page);

        $markup = $this->jsonLd($page, 'Recipe');

        $this->assertSame('Questions: +800 1234 5678.', $markup['description']);
        $this->assertSame(['Oats from the shop at +800 1234 5678', 'Milk'], $markup['recipeIngredient']);
        $this->assertSame(
            [['@type' => 'HowToStep', 'text' => 'Boil the milk.'], ['@type' => 'HowToStep', 'text' => 'Stuck? Call +800 1234 5678.']],
            $markup['recipeInstructions'],
        );
    }

    #[Test]
    public function the_card_and_the_category_page_print_them_too(): void
    {
        $recipe = $this->recipe('porridge', attributes: ['lead' => 'Questions: [phone].']);
        $lead = $this->app->make(Cards::class)->recipes([$recipe], 'en')[0]['lead'];

        $this->assertInstanceOf(Htmlable::class, $lead);
        $this->assertSame('Questions: '.self::LINK.'.', $lead->toHtml());

        $this->category('breakfasts')->update(['lead' => '<p>Ask us: [phone].</p>']);

        $page = (string) $this->get('/recipes/breakfasts')->assertOk()->getContent();

        $this->assertStringContainsString('<p>Ask us: '.self::LINK.'.</p>', $page);
        $this->assertStringNotContainsString('[phone]', $page);
    }

    #[Test]
    public function the_record_keeps_the_bracket(): void
    {
        $recipe = $this->recipe('porridge', attributes: ['lead' => 'Questions: [phone].', 'method' => '<p>Call [phone].</p>']);

        $this->get('/recipes/porridge')->assertOk();

        $recipe->refresh();
        $this->assertSame('Questions: [phone].', $recipe->getTranslation('lead', 'en'));
        $this->assertSame('<p>Call [phone].</p>', $recipe->getTranslation('method', 'en'));
    }
}
