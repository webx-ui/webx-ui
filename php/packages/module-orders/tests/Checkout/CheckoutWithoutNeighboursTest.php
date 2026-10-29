<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Cart\AddResult;
use WebxUi\Cart\CartHandle;
use WebxUi\Cart\Rendering\Panel;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Http\CheckoutController;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\PlaceOrderBuilder;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Checkout\Steps\ContactsStep;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Tests\PlaceTestCase;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Forms\FormResponse;

/**
 * The checkout with nothing but what the module requires — the cart, the widgets, the
 * compositions (spec §1.1, §34): the steps «Contacts» and «Comment», the column of the totals, and
 * an order placed with plain forms, without JavaScript.
 */
final class CheckoutWithoutNeighboursTest extends PlaceTestCase
{
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

    #[Test]
    public function the_cart_button_leads_to_the_checkout(): void
    {
        $this->assertSame(url('/checkout'), $this->app->make(Panel::class)->checkout());
    }

    #[Test]
    public function the_steps_are_the_contacts_and_the_comment(): void
    {
        $steps = $this->app->make(CheckoutSteps::class);

        $this->assertSame(['contacts' => 100, 'comment' => 900], $steps->positions());
    }

    #[Test]
    public function an_empty_cart_says_so_and_has_no_steps(): void
    {
        $this->get('/checkout')->assertOk()
            ->assertSee('Your cart is empty.')
            ->assertDontSee('checkout-step-contacts', false);
    }

    #[Test]
    public function showing_the_page_makes_no_draft(): void
    {
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 2], ['type' => 'test.product', 'ref' => 3]]);

        $page = $this->visit($cart, '/checkout')->assertOk();

        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        $this->assertStringContainsString('Cookie', (string) $page->headers->get('Vary'));
        $page->assertSee('1. Contacts')
            ->assertSee('2. Comment')
            ->assertSee('3 items for')
            ->assertSee('Item A')
            ->assertSee('name="expected_payable" value="10698"', false)
            ->assertSee('By pressing the button you agree with the terms')
            // The first step not filled is where the buyer goes.
            ->assertSee('id="checkout-step-contacts" class="webx-orders-checkout__step is-open"', false);
        $this->visit($cart, '/checkout/summary')->assertOk()->assertSee('To pay');

        $this->assertSame(0, CheckoutDraft::query()->count());
    }

    #[Test]
    public function an_order_is_placed_with_plain_forms(): void
    {
        $cart = $this->cart();

        $this->visit($cart, '/checkout')->assertOk();
        $this->visit($cart, '/checkout/steps/contacts', [
            'customer' => ['first_name' => ' Buyer ', 'last_name' => 'A', 'phone' => '+800 1234 5678', 'email' => 'Buyer-A@example.test'],
            'recipient' => ['other' => '1', 'first_name' => 'Someone', 'phone' => '+800 9876 5432'],
        ])->assertRedirect(url('/checkout').'#checkout-step-contacts');

        $draft = CheckoutDraft::query()->findOrFail($cart->cart->id);
        $this->assertSame('contacts', $draft->last_step);
        $this->assertSame('Buyer', $draft->customer['first_name'] ?? null);
        $this->assertSame('Someone', $draft->recipient['first_name'] ?? null);

        $this->visit($cart, '/checkout/steps/comment', ['comment' => 'Ring twice'])->assertRedirect();

        // Saved and ready: the step is done, its summary is the buyer and the phone.
        $this->visit($cart, '/checkout')
            ->assertSee('id="checkout-step-contacts" class="webx-orders-checkout__step is-done"', false)
            ->assertSee('Buyer A, +800 1234 5678');

        $placed = $this->visit($cart, '/checkout/place', ['expected_payable' => '4999', FormResponse::FORM => CheckoutController::PLACE_FORM]);

        $order = Order::query()->sole();
        $placed->assertRedirect();
        $this->assertStringContainsString('/order/'.$order->number, (string) $placed->headers->get('Location'));
        $this->assertSame('Buyer', $order->customer_first_name);
        $this->assertSame('A', $order->customer_last_name);
        $this->assertSame('buyer-a@example.test', $order->customer_email);
        $this->assertSame(['first_name' => 'Someone', 'last_name' => null, 'phone' => '+800 9876 5432'], $order->recipient);
        $this->assertSame('Ring twice', $order->comment);
        $this->assertSame(4999, $order->payable);
    }

    #[Test]
    public function the_steps_answer_json_to_the_form_script(): void
    {
        $cart = $this->cart();

        $this->visit($cart, '/checkout/steps/contacts', ['customer' => ['first_name' => '']], json: true)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer.first_name', 'customer.phone', 'customer.email']);

        $this->visit($cart, '/checkout/steps/contacts', ['customer' => ['first_name' => 'Buyer', 'phone' => '+800 1234 5678', 'email' => 'buyer@example.test']], json: true)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->visit($cart, '/checkout/steps/delivery', [], json: true)->assertNotFound();
    }

    #[Test]
    public function placing_with_a_step_not_ready_creates_nothing(): void
    {
        $cart = $this->cart();

        $this->visit($cart, '/checkout/place', ['expected_payable' => '4999'], json: true)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer.first_name']);

        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function an_amount_that_changed_is_a_conflict_with_a_fresh_column(): void
    {
        $cart = $this->cart();
        $this->fill($cart);

        $this->visit($cart, '/checkout/place', ['expected_payable' => '4000'], json: true)
            ->assertStatus(409)
            ->assertJsonPath('code', 'checkout.payable_changed')
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, '40,00') && str_contains($message, '49,99'))
            ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'value="4999"'));

        // Without JavaScript the page comes back with the message under the confirmation.
        $this->visit($cart, '/checkout/place', ['expected_payable' => '4000'])->assertRedirect(url('/checkout'));
        $this->visit($cart, '/checkout')->assertSee('The amount has changed');

        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function a_line_that_cannot_be_bought_is_warned_about_above_the_steps(): void
    {
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 2]]);
        $this->products->lines[2] = self::product('Set B', 15000, available: false);
        $this->fill($cart);

        $this->visit($cart, '/checkout')->assertOk()
            ->assertSee('id="checkout-warnings"', false)
            ->assertSee('Open the cart');

        $this->visit($cart, '/checkout/place', ['expected_payable' => '4999'], json: true)
            ->assertStatus(409)
            ->assertJsonPath('code', 'checkout.cart_not_orderable');
    }

    #[Test]
    public function the_other_language_has_its_own_address(): void
    {
        $cart = $this->cart();

        $this->visit($cart, '/ru/checkout')->assertOk()
            ->assertSee('Оформление заказа')
            ->assertSee('action="'.url('/ru/checkout/steps/contacts').'"', false)
            ->assertSee('1 товар на сумму');
    }

    #[Test]
    public function a_signed_in_visitor_fills_the_contacts_until_the_draft_has_the_buyers_own(): void
    {
        $cart = $this->cart();
        $visitor = new Visitor(7, 'visitor@example.test', true, 'Visitor', 'B', '+800 5555 0000');
        $step = $this->app->make(ContactsStep::class);
        $context = fn (?CheckoutDraft $draft): CheckoutContext => new CheckoutContext($cart->cart, $this->app->make(CartHandle::class)->state(), $draft, $visitor, 'en', 'EUR');

        $this->assertSame('Visitor B, +800 5555 0000', $step->summary($context(null)));
        $this->assertSame([], $step->check($context(null), Purpose::Order));

        $draft = new CheckoutDraft(['cart_id' => $cart->cart->id, 'customer' => ['first_name' => 'Buyer', 'email' => 'buyer@example.test']]);
        $this->assertSame('Buyer, buyer@example.test', $step->summary($context($draft)));

        $builder = new PlaceOrderBuilder;
        $step->contribute($context($draft), $builder);
        $input = $builder->build($context($draft), null);
        $this->assertSame('buyer@example.test', $input->customer->email);
        $this->assertSame(7, $input->userId);
    }

    private function fill(AddResult $cart): void
    {
        $this->visit($cart, '/checkout/steps/contacts', [
            'customer' => ['first_name' => 'Buyer', 'phone' => '+800 1234 5678', 'email' => 'buyer@example.test'],
        ])->assertRedirect();
    }

    /**
     * @param  array<string, mixed>|null  $post  null — a GET
     * @return TestResponse<Response>
     */
    private function visit(AddResult $cart, string $uri, ?array $post = null, bool $json = false): TestResponse
    {
        // The headers of one request are not the next one's.
        $this->flushHeaders();
        $request = $this->withCookie('webx_cart', (string) ($cart->token ?? ''));

        if ($json) {
            $request = $request->withHeader('Accept', 'application/json');
        }

        // The middleware of the web group checks the token; the tests of the forms are not about it.
        $request = $request->withoutMiddleware(ValidateCsrfToken::class);

        return $post === null ? $request->get($uri) : $request->post($uri, $post);
    }
}
