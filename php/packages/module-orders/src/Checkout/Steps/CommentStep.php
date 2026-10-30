<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Steps;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\CheckoutLinks;
use WebxUi\Orders\Checkout\CheckoutSettings;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\PlaceOrderBuilder;

/**
 * The buyer's comment on the order (spec §20.2): never required, gone with the setting
 * `orders.checkout.comment = off`.
 */
final readonly class CommentStep implements Step
{
    public const string KEY = 'comment';

    /** What the draft keeps (§21.1). */
    public const int MAX = 2000;

    public function __construct(
        private CheckoutSettings $settings,
        private CheckoutLinks $links,
        private Validation $validation,
        private Views $views,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return (string) __('webx-orders::checkout.steps.comment');
    }

    public function applies(CheckoutContext $context): bool
    {
        return $this->settings->comment();
    }

    public function render(CheckoutContext $context): Htmlable
    {
        return new HtmlString($this->views->make('webx-orders::checkout.steps.comment', [
            'action' => $this->links->step(self::KEY, $context->locale),
            'comment' => $this->comment($context),
            'max' => self::MAX,
        ])->render());
    }

    public function summary(CheckoutContext $context): ?string
    {
        $comment = $this->comment($context);

        return $comment !== null ? Str::limit($comment, 80) : null;
    }

    public function save(CheckoutContext $context, array $input, CheckoutDraft $draft): void
    {
        $comment = is_scalar($input['comment'] ?? null) ? trim((string) $input['comment']) : '';

        $this->validation->make(['comment' => $comment], ['comment' => ['nullable', 'string', 'max:'.self::MAX]])->validate();

        $draft->comment = $comment === '' ? null : $comment;
    }

    public function check(CheckoutContext $context, Purpose $purpose): array
    {
        return [];
    }

    public function contribute(CheckoutContext $context, PlaceOrderBuilder $order): void
    {
        $order->comment($this->comment($context));
    }

    private function comment(CheckoutContext $context): ?string
    {
        $comment = $context->saved('comment');

        return is_string($comment) && $comment !== '' ? $comment : null;
    }
}
