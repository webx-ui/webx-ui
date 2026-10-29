<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Foundation\Application;
use WebxUi\Cart\AddResult;
use WebxUi\Orders\Tests\PlaceTestCase;
use WebxUi\Themes\ThemeServiceProvider;

/** The checkout on a site of the module's requirements alone, a browser going through it. */
abstract class CheckoutTestCase extends PlaceTestCase
{
    use VisitsCheckout;

    /**
     * The theme engine first: the widgets the page stands on want it.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ThemeServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    /** The contacts saved: the cart is ready to be ordered. */
    protected function fill(AddResult $cart, string $email = 'buyer@example.test'): void
    {
        $this->visit($cart, '/checkout/steps/contacts', [
            'customer' => ['first_name' => 'Buyer', 'phone' => '+800 1234 5678', 'email' => $email],
        ])->assertRedirect();
    }
}
