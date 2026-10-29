<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Fixtures;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use RuntimeException;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\AdjustmentSource;
use WebxUi\Cart\Totals\CartContext;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\ShippingChoice;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Checkout\Slots\ShippingSlot;
use WebxUi\Orders\Checkout\Steps\StepError;

/**
 * Delivery as a test wants it (spec §22.4): the choice is kept in memory, its price and its
 * neutral description are the test's, and the strict check may refuse — the point is gone. Being
 * a source of the chain too, a fixed price lands in the total as `module-delivery`'s does: an
 * adjustment `meta.kind = delivery`, priority 1000.
 */
final class FakeShippingSlot implements AdjustmentSource, ShippingSlot
{
    /** @var array{method: string, title: string, carrier: string, line: ShippingLine, cod: bool, pickup: bool}|null */
    public ?array $chosen = null;

    /** The point went away since it was chosen: the strict check refuses. */
    public bool $pointGone = false;

    /** The method that throws, as a slot whose service is down does. */
    public ?string $throwIn = null;

    /** @var list<Purpose> the purposes check() was asked with */
    public array $checked = [];

    public function choose(string $method, ShippingLine $line, string $title = 'Courier', string $carrier = 'carrier-a', bool $cod = false, bool $pickup = false): self
    {
        $this->chosen = ['method' => $method, 'title' => $title, 'carrier' => $carrier, 'line' => $line, 'cod' => $cod, 'pickup' => $pickup];

        return $this;
    }

    public function key(): string
    {
        return CheckoutSlots::DELIVERY;
    }

    public function needed(CheckoutContext $context): bool
    {
        $this->fail('needed');

        // Delivery::needed(): a line that can be bought and goes by mail (decision 46).
        foreach ($context->state->lines as $line) {
            if ($line->available && $line->line !== null && $line->line->shippable) {
                return true;
            }
        }

        return false;
    }

    public function render(CheckoutContext $context): Htmlable
    {
        $this->fail('render');

        return new HtmlString('<div class="fake-delivery">'.e($this->chosen['title'] ?? 'Choose a delivery method').'</div>');
    }

    public function summary(CheckoutContext $context): ?string
    {
        $this->fail('summary');

        return $this->chosen['title'] ?? null;
    }

    public function check(CheckoutContext $context, Purpose $purpose): array
    {
        $this->fail('check');
        $this->checked[] = $purpose;

        if ($this->chosen === null) {
            return [new StepError($this->key(), 'method', 'Choose a delivery method.', 'delivery.method_required')];
        }

        if ($purpose === Purpose::Order && $this->pointGone) {
            return [new StepError($this->key(), 'point', 'The pickup point no longer works, choose another one.', 'delivery.point_gone')];
        }

        return [];
    }

    public function snapshot(CheckoutContext $context): array
    {
        $this->fail('snapshot');
        $chosen = $this->chosen ?? throw new RuntimeException('Nothing is chosen.');

        return [
            'method' => $chosen['method'],
            'title' => [$context->locale => $chosen['title']],
            'price' => [
                'kind' => $chosen['line']->kind,
                'amount' => $chosen['line']->amount,
                'currency' => $context->currency,
                'in_total' => $chosen['line']->kind !== ShippingLine::CARRIER_RATES,
            ],
        ];
    }

    public function totalsLine(CheckoutContext $context): ?ShippingLine
    {
        $this->fail('totalsLine');

        return $this->chosen['line'] ?? null;
    }

    public function choice(CheckoutContext $context): ?ShippingChoice
    {
        $this->fail('choice');

        return $this->chosen === null ? null : new ShippingChoice(
            $this->chosen['method'],
            $this->chosen['carrier'],
            $this->chosen['cod'],
            $this->chosen['pickup'],
        );
    }

    public function adjustments(CartContext $cart): array
    {
        $line = $this->chosen['line'] ?? null;

        return $line !== null && $line->kind === ShippingLine::FIXED && $line->amount !== null
            ? [new Adjustment('delivery', 'Delivery', $line->amount, null, ['kind' => 'delivery'])]
            : [];
    }

    private function fail(string $method): void
    {
        if ($this->throwIn === $method) {
            throw new RuntimeException("The delivery service is down ({$method}).");
        }
    }
}
