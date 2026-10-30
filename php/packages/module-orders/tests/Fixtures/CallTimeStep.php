<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Fixtures;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\CheckoutLinks;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\PlaceOrderBuilder;
use WebxUi\Orders\Checkout\Steps\Step;
use WebxUi\Orders\Events\OrderPlacing;
use WebxUi\Orders\Orders;

/**
 * The project's own step of the guide (`apps/docs/guide/checkout.md`, «A step of your own»),
 * kept here so the guide's code is the code that works: the value in the draft's `steps`, the
 * order's history line written inside the order's transaction. Only the view is inline.
 */
final class CallTimeStep implements Step
{
    public const string KEY = 'call-time';

    private const array TIMES = ['morning', 'afternoon', 'evening'];

    /** What contribute() took from the draft, for the order being placed now. */
    private ?string $placing = null;

    public function __construct(private readonly CheckoutLinks $links) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return 'When to call';
    }

    public function applies(CheckoutContext $context): bool
    {
        return true;
    }

    public function render(CheckoutContext $context): Htmlable
    {
        return new HtmlString('<form class="call-time" action="'.e($this->links->step(self::KEY, $context->locale)).'">'
            .e($this->value($context) ?? '').'</form>');
    }

    public function summary(CheckoutContext $context): ?string
    {
        return $this->value($context);
    }

    public function save(CheckoutContext $context, array $input, CheckoutDraft $draft): void
    {
        $time = $input['call_time'] ?? null;

        if (! in_array($time, self::TIMES, true)) {
            throw ValidationException::withMessages(['call_time' => 'Choose a time.']);
        }

        $draft->steps = [...($draft->steps ?? []), self::KEY => $time];
    }

    public function check(CheckoutContext $context, Purpose $purpose): array
    {
        return [];
    }

    public function contribute(CheckoutContext $context, PlaceOrderBuilder $order): void
    {
        $this->placing = $this->value($context);
    }

    public function placing(OrderPlacing $event): void
    {
        if ($this->placing !== null) {
            app(Orders::class)->note($event->order, "Call in the {$this->placing}.", 'checkout:call-time');
            $this->placing = null;
        }
    }

    private function value(CheckoutContext $context): ?string
    {
        $value = $context->draft?->steps[self::KEY] ?? null;

        return is_string($value) ? $value : null;
    }
}
