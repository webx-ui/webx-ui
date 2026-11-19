<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;

/**
 * What `checkout.js` redraws from — the column of the totals (`data-summary`) and the page itself —
 * speaks the language of the page by its address, not by luck: the checkout is declared twice,
 * the copy behind the language prefix, and the address of the column is built in the page's
 * language. A site whose application language is English, its own Russian (main) and Ukrainian.
 */
final class CheckoutPageLanguageTest extends CheckoutTestCase
{
    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.locale', 'en');
        $app['config']->set('webx-localization.locales', [['code' => 'ru', 'default' => true], ['code' => 'uk']]);
    }

    #[Test]
    public function a_ukrainian_checkout_redraws_from_ukrainian_addresses(): void
    {
        $cart = $this->cart();

        $this->visit($cart, '/uk/checkout')->assertOk()
            ->assertSee('data-summary="http://localhost/uk/checkout/summary"', false)
            ->assertSee('Ваше замовлення');

        $this->visit($cart, '/uk/checkout/summary')->assertOk()
            ->assertSee('Ваше замовлення')
            ->assertDontSee('Your order');
    }

    #[Test]
    public function the_main_languages_checkout_is_in_it_and_not_in_the_applications(): void
    {
        $cart = $this->cart();

        $this->visit($cart, '/checkout')->assertOk()
            ->assertSee('data-summary="http://localhost/checkout/summary"', false);

        $this->visit($cart, '/checkout/summary')->assertOk()
            ->assertSee('Ваш заказ')
            ->assertDontSee('Your order');
    }
}
