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
use WebxUi\Orders\Checkout\CheckoutFields;
use WebxUi\Orders\Checkout\CheckoutLinks;
use WebxUi\Orders\Checkout\CheckoutSettings;
use WebxUi\Orders\Checkout\FieldPlaces;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\PlaceOrderBuilder;

/**
 * The block «Additional» (spec §20.2, WEBX_UI_ORDER_FIELDS.md §4.2): the extra fields before the
 * comment, the buyer's comment, the fields after it — one form. The key stays `comment`: addresses,
 * a theme's view and a project's `remove('comment')` go by it.
 *
 * The comment is never required and goes with `orders.checkout.comment = off`; the block stays
 * while it has fields, and is gone with neither. Without fields it is «Comment», as it always was.
 */
final readonly class CommentStep implements Step
{
    public const string KEY = 'comment';

    /** What the draft keeps (§21.1). */
    public const int MAX = 2000;

    public function __construct(
        private CheckoutSettings $settings,
        private CheckoutLinks $links,
        private CheckoutFields $fields,
        private Validation $validation,
        private Views $views,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return (string) __($this->fields->any(self::KEY) ? 'webx-orders::checkout.steps.extra' : 'webx-orders::checkout.steps.comment');
    }

    public function applies(CheckoutContext $context): bool
    {
        return $this->settings->comment() || $this->fields->any(self::KEY);
    }

    public function render(CheckoutContext $context): Htmlable
    {
        return new HtmlString($this->views->make('webx-orders::checkout.steps.comment', [
            'action' => $this->links->step(self::KEY, $context->locale),
            'withComment' => $this->settings->comment(),
            'comment' => $this->comment($context),
            'max' => self::MAX,
        ])->render());
    }

    /** The fields filled before the comment, its beginning, the fields after it (§4.2). */
    public function summary(CheckoutContext $context): ?string
    {
        $parts = [
            ...$this->fields->filled(FieldPlaces::BEFORE_COMMENT, $context),
            ...(($comment = $this->comment($context)) !== null ? [$comment] : []),
            ...$this->fields->filled(FieldPlaces::AFTER_COMMENT, $context),
        ];

        return $parts !== [] ? Str::limit(implode(', ', $parts), 80) : null;
    }

    public function save(CheckoutContext $context, array $input, CheckoutDraft $draft): void
    {
        if (! $this->settings->comment()) {
            return;
        }

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

    /** The saved comment, while the setting asks one. */
    private function comment(CheckoutContext $context): ?string
    {
        if (! $this->settings->comment()) {
            return null;
        }

        $comment = $context->saved('comment');

        return is_string($comment) && $comment !== '' ? $comment : null;
    }
}
