<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Checkout\Http\CheckoutController;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Events\OrderPlacing;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Tests\Fixtures\CallTimeStep;
use WebxUi\Widgets\Forms\FormResponse;

/**
 * A step of the project's own, registered the way the guide says (spec §20.2): it stands by its
 * position, keeps its value in the draft and writes it into the order's history with the order.
 */
final class ProjectStepTest extends CheckoutTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(CallTimeStep::class);
        $this->app->forgetInstance(CheckoutSteps::class);
        $this->app->afterResolving(CheckoutSteps::class, function (CheckoutSteps $steps): void {
            $steps->register($this->app->make(CallTimeStep::class), 250);
        });
        Event::listen(OrderPlacing::class, [CallTimeStep::class, 'placing']);
    }

    #[Test]
    public function the_step_stands_by_its_position_and_its_value_goes_into_the_history(): void
    {
        $cart = $this->cart();

        $this->visit($cart, '/checkout')->assertOk()
            ->assertSeeInOrder(['id="checkout-step-contacts"', 'id="checkout-step-call-time"', '2. When to call', 'id="checkout-step-comment"'], false);

        $this->fill($cart);
        $this->visit($cart, '/checkout/steps/call-time', ['call_time' => 'tomorrow'])->assertSessionHasErrors('call_time');
        $this->visit($cart, '/checkout/steps/call-time', ['call_time' => 'evening'])->assertRedirect();
        $this->assertSame(['call-time' => 'evening'], CheckoutDraft::query()->findOrFail($cart->cart->id)->steps);
        $this->visit($cart, '/checkout')->assertSee('id="checkout-step-call-time" class="webx-orders-checkout__step is-done"', false);

        $this->submit($cart, [FormResponse::FORM => CheckoutController::PLACE_FORM])->assertRedirect();

        $order = Order::query()->sole();
        $note = StatusChange::query()->where('order_id', $order->id)->where('source', 'checkout:call-time')->sole();
        $this->assertSame(StatusChange::NOTE, $note->kind);
        $this->assertSame('Call in the evening.', $note->note);
        // The draft forgets the steps after the order (§21.2): the next order asks again.
        $this->assertNull(CheckoutDraft::query()->findOrFail($cart->cart->id)->steps);
    }
}
