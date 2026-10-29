<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Fixtures;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use RuntimeException;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\PaymentSlot;
use WebxUi\Orders\Checkout\Slots\ShippingChoice;
use WebxUi\Orders\Checkout\Steps\StepError;
use WebxUi\Orders\Data\OrderData;

/**
 * Payment as a test wants it (spec §22.4): a method chosen in memory, online or not, a gateway
 * address for next(), and what it saw of the chosen delivery — the one thing a slot learns about
 * the other.
 */
final class FakePaymentSlot implements PaymentSlot
{
    /** @var array{method: string, title: string, online: bool}|null */
    public ?array $chosen = null;

    /** Where next() sends the buyer: a gateway, or null — «Thank you». */
    public ?string $gateway = null;

    /** «Pay» on «Thank you» — the number of the order follows; null — no way to pay again. */
    public ?string $again = 'https://pay.example.test/again/';

    /** The method that throws, as a slot whose gateway is down does. */
    public ?string $throwIn = null;

    /** @var list<ShippingChoice|null> what each render saw of the delivery */
    public array $seen = [];

    /** @var list<string> the numbers of the orders next() was asked about */
    public array $nexts = [];

    public function choose(string $method, bool $online, string $title = 'Card'): self
    {
        $this->chosen = ['method' => $method, 'title' => $title, 'online' => $online];

        return $this;
    }

    public function key(): string
    {
        return CheckoutSlots::PAYMENT;
    }

    public function needed(CheckoutContext $context): bool
    {
        $this->fail('needed');

        return $context->state->payable > 0;
    }

    public function render(CheckoutContext $context): Htmlable
    {
        $this->fail('render');
        $choice = $context->shippingChoice();
        $this->seen[] = $choice;
        // Cash on delivery only where the carrier collects money (§22.3).
        $cod = $choice?->cashOnDelivery === true ? '<label>Cash on delivery</label>' : '';

        return new HtmlString('<div class="fake-payment"><label>Card</label>'.$cod.'</div>');
    }

    public function summary(CheckoutContext $context): ?string
    {
        $this->fail('summary');

        return $this->chosen['title'] ?? null;
    }

    public function check(CheckoutContext $context, Purpose $purpose): array
    {
        $this->fail('check');

        return $this->chosen === null
            ? [new StepError($this->key(), 'method', 'Choose a payment method.', 'payment.method_required')]
            : [];
    }

    public function snapshot(CheckoutContext $context): array
    {
        $this->fail('snapshot');
        $chosen = $this->chosen ?? throw new RuntimeException('Nothing is chosen.');

        return ['method' => $chosen['method'], 'title' => [$context->locale => $chosen['title']]];
    }

    public function awaitsPayment(CheckoutContext $context): bool
    {
        $this->fail('awaitsPayment');

        return $this->chosen['online'] ?? false;
    }

    public function next(OrderData $order): ?string
    {
        $this->fail('next');
        $this->nexts[] = $order->number;

        return $this->gateway;
    }

    public function payAgainUrl(OrderData $order): ?string
    {
        $this->fail('payAgainUrl');

        return $this->again !== null ? $this->again.$order->number : null;
    }

    private function fail(string $method): void
    {
        if ($this->throwIn === $method) {
            throw new RuntimeException("The payment gateway is down ({$method}).");
        }
    }
}
