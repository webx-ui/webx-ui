<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * The list of methods on the page of payment (groups spec §6.8): after a failed payment and on an
 * order «on receipt» it is the list of the checkout's step with «Continue», and the form is read by
 * §6.4. The order's own method is not on it, and what the order holds does not change.
 */
final class ChooseMethodTest extends OnlineCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::query()->whereIn('key', [OnReceipt::METHOD, BankTransfer::METHOD])->update(['enabled' => true]);
    }

    #[Test]
    public function a_failed_payment_offers_the_list_with_continue(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'decline']));

        $page = $this->get($this->payPage($order->refresh()))
            ->assertOk()
            ->assertSee('data-webx-payments-methods', false)
            ->assertSee('name="group" value="online"', false)
            ->assertSee('>Continue</button>', false)
            ->assertSee('webx/payments/methods.js', false)
            ->assertDontSee('webx-payments-others__choice', false);

        $this->assertSame(1, substr_count((string) $page->getContent(), 'data-webx-payments-methods'));
    }

    #[Test]
    public function an_order_on_receipt_has_the_same_list_open_instead_of_its_buttons(): void
    {
        $order = $this->onReceipt();

        $page = $this->get($this->payPage($order))
            ->assertOk()
            ->assertSee('Pay online now')
            ->assertSee('value="gateway-a-card"', false)
            ->assertSee('data-method="'.BankTransfer::METHOD.'"', false)
            ->assertDontSee('data-method="'.OnReceipt::METHOD.'"', false)
            ->assertDontSee('Pay online now —')
            ->assertDontSee('webx-payments-others__choice', false);

        $this->assertMatchesRegularExpression('/<details class="webx-payments-others"\s+open\s*>/', (string) $page->getContent());
    }

    #[Test]
    public function an_order_on_receipt_with_nothing_online_keeps_the_button_closed(): void
    {
        PaymentMethod::query()->where('key', 'like', 'gateway-a-%')->update(['enabled' => false]);

        $page = $this->get($this->payPage($this->onReceipt()))
            ->assertOk()
            ->assertSee('Choose another method')
            ->assertDontSee('Pay online now');

        $this->assertDoesNotMatchRegularExpression('/<details class="webx-payments-others"\s+open\s*>/', (string) $page->getContent());
    }

    #[Test]
    public function a_group_whose_one_method_was_the_orders_own_is_gone(): void
    {
        PaymentMethod::query()->where('key', 'like', 'gateway-a-%')->where('key', '!=', 'gateway-a-card')->update(['enabled' => false]);

        $this->get($this->payPage($this->onlineOrder()))
            ->assertDontSee('value="online"', false)
            ->assertDontSee('value="gateway-a-card"', false)
            ->assertSee('data-method="'.BankTransfer::METHOD.'"', false);
    }

    #[Test]
    public function a_method_of_the_group_sent_is_the_one_taken(): void
    {
        $order = $this->onlineOrder();

        $this->choose($order, ['group' => 'online', 'method' => 'gateway-a-form']);

        $this->assertSame('gateway-a-form', $order->refresh()->payment['method'] ?? null);
    }

    #[Test]
    public function a_method_left_from_another_group_gives_way_to_the_first_of_the_group(): void
    {
        $order = $this->onlineOrder();

        $this->choose($order, ['group' => 'transfer', 'method' => 'gateway-a-form']);

        $this->assertSame(BankTransfer::METHOD, $order->refresh()->payment['method'] ?? null);
    }

    #[Test]
    public function a_group_alone_never_falls_to_the_orders_own_method(): void
    {
        $order = $this->onlineOrder();

        $this->choose($order, ['group' => 'online']);

        $this->assertSame('gateway-a-form', $order->refresh()->payment['method'] ?? null);
    }

    #[Test]
    public function a_second_press_of_continue_stays_with_the_method_chosen(): void
    {
        $order = $this->onlineOrder();
        $form = ['group' => 'online', 'method' => 'gateway-a-unsigned'];

        $this->choose($order, $form);
        $this->choose($order->refresh(), $form);

        $this->assertSame('gateway-a-unsigned', $order->refresh()->payment['method'] ?? null);
    }

    #[Test]
    public function continue_with_nothing_chosen_says_so_and_changes_nothing(): void
    {
        $order = $this->onlineOrder();

        $this->choose($order, [])->assertRedirect($this->payPage($order));

        $this->get($this->payPage($order))->assertSee('Choose a payment method');
        $this->assertSame('gateway-a-card', $order->refresh()->payment['method'] ?? null);
        $this->assertSame(0, Payment::query()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function a_method_no_longer_on_offer_says_so_and_changes_nothing(): void
    {
        $order = $this->onlineOrder();

        $this->choose($order, ['group' => 'none', 'method' => 'gone'])->assertRedirect($this->payPage($order));

        $this->get($this->payPage($order))->assertSee('The payment method is no longer available');
        $this->assertSame('gateway-a-card', $order->refresh()->payment['method'] ?? null);
    }

    #[Test]
    public function the_items_of_the_order_stay_as_they_were(): void
    {
        $order = $this->onReceipt();
        $items = $order->items()->get()->toArray();
        $total = $order->total;

        $this->choose($order, ['group' => 'online', 'method' => 'gateway-a-card']);

        $order->refresh();
        $this->assertSame($items, $order->items()->get()->toArray());
        $this->assertSame($total, $order->total);
        $this->assertTrue($order->awaiting_payment);
    }

    private function onReceipt(): Order
    {
        return $this->place($this->cart(), [
            'awaitPayment' => false,
            'blocks' => ['payment' => ['method' => OnReceipt::METHOD, 'type' => OnReceipt::KEY, 'kind' => 'offline', 'title' => ['en' => 'On receipt'], 'price' => null]],
        ]);
    }

    /**
     * @param  array<string, string>  $form
     * @return TestResponse<Response>
     */
    private function choose(Order $order, array $form): TestResponse
    {
        return $this->post($this->app->make(PaymentLinks::class)->method($order), $form);
    }
}
