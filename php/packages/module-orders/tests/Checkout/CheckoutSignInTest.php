<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\CartService;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Users\Models\User;
use WebxUi\Users\UsersServiceProvider;

/**
 * A guest and a visitor at the checkout with `webx-ui/module-users` (spec §25, §26): «Sign in» in
 * the step of the contacts opens the dialog — without JavaScript the page of the sign-in with the
 * way back; «Sign out» comes back to the checkout; «Thank you» offers a guest an account with the
 * address of the order.
 */
final class CheckoutSignInTest extends CheckoutTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        $providers = parent::getPackageProviders($app);
        array_splice($providers, (int) array_search(OrdersServiceProvider::class, $providers, true), 0, [UsersServiceProvider::class]);

        return $providers;
    }

    #[Test]
    public function a_guest_signs_in_from_the_step_of_the_contacts(): void
    {
        $html = (string) $this->visit($this->cart(), '/checkout')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '~<a href="http://localhost/account/sign-in\?back=%2Fcheckout" rel="nofollow" class="webx-users-sign-in-button webx-orders-checkout__sign-in" data-webx-sign-in="sign-in"~',
            $html,
        );
        // The dialog comes with its opener, before </body>; the step's own form has no form in it.
        $this->assertSame(1, substr_count($html, 'id="webx-sign-in"'));
        $this->assertLessThan(strpos($html, 'id="checkout-step-contacts-form"'), strpos($html, 'webx-orders-checkout__sign-in'));
        $this->assertStringNotContainsString('webx-orders-checkout__sign-out', $html);
    }

    #[Test]
    public function a_visitor_signed_in_sees_who_and_signs_out_back_to_the_checkout(): void
    {
        $visitor = $this->visitor();
        $this->actingAs($visitor, 'site');
        // A visitor's cart is the account's.
        $cart = $this->app->make(CartService::class)->add(null, [['type' => 'test.product', 'ref' => 1]], 'en', $visitor->id);

        $html = (string) $this->visit($cart, '/checkout')->assertOk()->getContent();

        $this->assertStringContainsString('You are signed in as Buyer A', $html);
        $this->assertStringContainsString('<form method="post" action="http://localhost/account/sign-out" class="webx-orders-checkout__sign-out">', $html);
        $this->assertStringContainsString('<input type="hidden" name="back" value="http://localhost/checkout">', $html);
        $this->assertStringNotContainsString('data-webx-sign-in="sign-in"', $html);
        // Their contacts in the fields until they save their own.
        $this->assertStringContainsString('value="buyer-a@example.test"', $html);

        $this->post('/account/sign-out', ['back' => 'http://localhost/checkout'])->assertRedirect('http://localhost/checkout');
    }

    #[Test]
    public function thank_you_offers_a_guest_an_account_with_the_address_of_the_order(): void
    {
        $cart = $this->cart();
        $this->fill($cart, 'buyer-a@example.test');
        $this->submit($cart)->assertRedirect();
        $order = Order::query()->sole();

        $html = (string) $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()->getContent();

        $this->assertStringContainsString('Save your orders in an account', $html);
        $this->assertMatchesRegularExpression(
            '~<a href="http://localhost/account/register\?email=buyer-a%40example\.test&amp;back=%2Fcheckout%2Fthanks%2F'.$order->number.'" rel="nofollow" data-webx-sign-in-email="buyer-a@example\.test" class="webx-users-sign-in-button" data-webx-sign-in="register" data-webx-users-guest>Create an account</a>~',
            $html,
        );
        // The dialog's registration is on the page.
        $this->assertStringContainsString('data-webx-sign-in-view="register"', $html);

        // Registration closed — nothing offered.
        config()->set('webx-users.registration', 'closed');
        $this->assertStringNotContainsString('Save your orders', (string) $this->visit($cart, '/checkout/thanks/'.$order->number)->getContent());
    }

    #[Test]
    public function a_visitor_signed_in_is_offered_no_account(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $this->submit($cart)->assertRedirect();
        $order = Order::query()->sole();
        $this->actingAs($this->visitor(), 'site');

        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()->assertDontSee('Save your orders');
    }

    private function visitor(): User
    {
        return User::query()->create(['email' => 'buyer-a@example.test', 'password' => 'correct-horse-battery', 'first_name' => 'Buyer', 'last_name' => 'A', 'phone' => '+800 1234 5678']);
    }
}
