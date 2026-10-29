<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Cart\AddResult;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Tests\PlaceTestCase;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;

/**
 * The tab «Checkout» on the page (spec §20.3, §23.4, §31.2): which fields are asked and how, the
 * recipient and the comment switched off, the three ways of agreeing with the terms.
 */
final class CheckoutFieldsTest extends PlaceTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ThemeServiceProvider::class, ...parent::getPackageProviders($app), SettingsServiceProvider::class];
    }

    #[Test]
    public function a_hidden_field_is_not_asked_and_an_optional_one_says_so(): void
    {
        $this->settings(['orders.checkout.fields.last_name' => 'hidden', 'orders.checkout.fields.phone' => 'optional']);
        $cart = $this->cart();

        $this->visit($cart, '/checkout')->assertOk()
            ->assertDontSee('name="customer[last_name]"', false)
            ->assertSee('name="customer[phone]"', false)
            ->assertSee('(optional)');

        $this->visit($cart, '/checkout/steps/contacts', ['customer' => ['first_name' => 'Buyer', 'last_name' => 'Kept out', 'email' => 'buyer@example.test']])->assertRedirect();
        $this->confirm($cart)->assertRedirect();

        $order = Order::query()->sole();
        $this->assertNull($order->customer_last_name);
        $this->assertNull($order->customer_phone);
    }

    #[Test]
    public function with_both_contacts_optional_one_of_them_is_still_wanted(): void
    {
        $this->settings(['orders.checkout.fields.phone' => 'optional', 'orders.checkout.fields.email' => 'optional']);
        $cart = $this->cart();

        $this->visit($cart, '/checkout/steps/contacts', ['customer' => ['first_name' => 'Buyer']], json: true)
            ->assertStatus(422)
            ->assertJsonPath('errors', ['customer.phone' => ['Enter a phone or an e-mail.']]);

        $this->visit($cart, '/checkout/steps/contacts', ['customer' => ['first_name' => 'Buyer', 'phone' => '+800 1234 5678']], json: true)->assertOk();
    }

    #[Test]
    public function the_recipient_and_the_comment_go_with_their_settings(): void
    {
        $this->settings(['orders.checkout.recipient' => 'off', 'orders.checkout.comment' => 'off']);
        $cart = $this->cart();

        $this->visit($cart, '/checkout')->assertOk()
            ->assertDontSee('name="recipient[other]"', false)
            ->assertDontSee('checkout-step-comment', false);
        $this->visit($cart, '/checkout/steps/comment', ['comment' => 'Ring twice'])->assertNotFound();

        // A recipient sent anyway is not kept.
        $this->visit($cart, '/checkout/steps/contacts', [
            'customer' => ['first_name' => 'Buyer', 'phone' => '+800 1234 5678', 'email' => 'buyer@example.test'],
            'recipient' => ['other' => '1', 'first_name' => 'Someone', 'phone' => '+800 9876 5432'],
        ])->assertRedirect();
        $this->confirm($cart)->assertRedirect();

        $this->assertNull(Order::query()->sole()->recipient);
    }

    #[Test]
    public function a_ticked_recipient_needs_a_name_and_a_phone(): void
    {
        $cart = $this->cart();

        $this->visit($cart, '/checkout/steps/contacts', [
            'customer' => ['first_name' => 'Buyer', 'phone' => '+800 1234 5678', 'email' => 'buyer@example.test'],
            'recipient' => ['other' => '1'],
        ], json: true)->assertStatus(422)->assertJsonValidationErrors(['recipient.first_name', 'recipient.phone']);
    }

    #[Test]
    public function the_terms_are_a_notice_a_checkbox_or_nothing(): void
    {
        $this->settings([
            'orders.checkout.terms' => 'checkbox',
            'orders.checkout.terms_page' => ['target' => 'url', 'url' => 'https://shop.example.test/terms'],
        ]);
        $cart = $this->cart();
        $this->fill($cart);

        $this->visit($cart, '/checkout')->assertOk()
            ->assertSee('name="terms"', false)
            ->assertSee('href="https://shop.example.test/terms"', false)
            ->assertDontSee('By pressing the button');
        $this->visit($cart, '/checkout/place', ['expected_payable' => '4999'], json: true)
            ->assertStatus(422)
            ->assertJsonPath('errors', ['terms' => ['Agree with the terms to place the order.']]);
        $this->assertSame(0, Order::query()->count());

        $this->visit($cart, '/checkout/place', ['expected_payable' => '4999', 'terms' => '1'])->assertRedirect();
        $this->assertSame(1, Order::query()->count());

        $this->settings(['orders.checkout.terms' => 'none']);
        $this->visit($this->cart(), '/checkout')->assertOk()
            ->assertDontSee('name="terms"', false)
            ->assertDontSee('By pressing the button');
    }

    /** @param  array<string, mixed>  $values */
    private function settings(array $values): void
    {
        $this->app->make(Settings::class)->save($values);
    }

    private function fill(AddResult $cart): void
    {
        $this->visit($cart, '/checkout/steps/contacts', [
            'customer' => ['first_name' => 'Buyer', 'phone' => '+800 1234 5678', 'email' => 'buyer@example.test'],
        ])->assertRedirect();
    }

    /** @return TestResponse<Response> */
    private function confirm(AddResult $cart): TestResponse
    {
        return $this->visit($cart, '/checkout/place', ['expected_payable' => '4999']);
    }

    /**
     * @param  array<string, mixed>|null  $post  null — a GET
     * @return TestResponse<Response>
     */
    private function visit(AddResult $cart, string $uri, ?array $post = null, bool $json = false): TestResponse
    {
        $this->flushHeaders();
        $request = $this->withCookie('webx_cart', (string) ($cart->token ?? ''))->withoutMiddleware(ValidateCsrfToken::class);

        if ($json) {
            $request = $request->withHeader('Accept', 'application/json');
        }

        return $post === null ? $request->get($uri) : $request->post($uri, $post);
    }
}
