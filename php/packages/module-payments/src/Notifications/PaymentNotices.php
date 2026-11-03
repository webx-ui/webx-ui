<?php

declare(strict_types=1);

namespace WebxUi\Payments\Notifications;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Cart\Money;
use WebxUi\Localization\Locales;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Events\Variable;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Notifications;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Notifications\OrderChannels;
use WebxUi\Orders\Notifications\OrderValues;
use WebxUi\Payments\Events\PaymentProblem;
use WebxUi\Payments\Instructions;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\PaymentSnapshot;
use WebxUi\Payments\Words;
use WebxUi\Settings\Settings;

/**
 * The payments' side of `webx-ui/module-notifications` (spec §11.5).
 *
 * - The order's events (`orders.*`) get three values the orders do not have: `payment_url` — the
 *   signed page to pay again, empty for a paid or a cancelled order —, `payment_instructions` — the
 *   bank details as text in the order's language, empty for other methods and once paid — and
 *   `payment_method`, the method's title. The «Payment received» letter is the orders' own channel
 *   `orders.paid`; payments only gives it these.
 * - `payments.problem` tells the manager of money where it should not be (§9.4): paid twice, not
 *   the sum asked, money for a cancelled order, a hold about to end, a capture refused. Its
 *   channels are the setting `payments.notify.problem`, as the orders keep theirs.
 *
 * Touched only where the notifications module is installed: the provider calls in here under
 * `class_exists(NotificationEvents::class)`.
 */
final readonly class PaymentNotices
{
    public const string PROBLEM = 'payments.problem';

    /** Where the channels of a problem are kept — a setting of the site, the config without one. */
    public const string SETTING = 'payments.notify.problem';

    /** The values payments adds to every event of an order. */
    public const array ORDER_VARIABLES = ['payment_url', 'payment_instructions', 'payment_method'];

    public function __construct(private Container $container) {}

    public static function declare(NotificationEvents $events, Container $container): void
    {
        $vars = 'webx-payments::notifications.vars.';

        $events->extend(
            ['orders.*'],
            [
                new Variable('payment_url', $vars.'payment_url', example: 'https://site.test/pay/1024?signature=0000'),
                new Variable('payment_instructions', $vars.'payment_instructions', example: "Recipient: Shop A Ltd\nAccount: 0000 0000 0000 0000\nPurpose: Payment for order 1024"),
                new Variable('payment_method', $vars.'payment_method', example: 'Bank transfer'),
            ],
            static fn (?Model $subject): array => $container->make(self::class)->orderValues($subject),
        );

        $one = static fn (string $name, string $example): Variable => new Variable($name, $vars.$name, example: $example);

        $events->register(new EventDefinition(
            key: self::PROBLEM,
            label: 'webx-payments::notifications.problem',
            module: 'payments',
            variables: [
                $one('number', '1024'),
                $one('order_url', 'https://site.test/cms/orders/1024'),
                $one('problem', 'Paid twice'),
                $one('amount', '49.99 €'),
                $one('method', 'Card (gateway A)'),
                $one('gateway_ref', 'ga_01J00000000000000000000000'),
                $one('message', 'A second payment came for an order that is paid'),
            ],
            sample: [
                '*' => static fn (string $locale): array => [
                    'subject' => trans('webx-payments::notifications.sample.subject', [], $locale),
                    'template' => trans('webx-payments::notifications.sample.template', [], $locale),
                ],
            ],
            url: static fn (array $values): ?string => is_string($values['order_url'] ?? null) && $values['order_url'] !== '' ? $values['order_url'] : null,
        ));
    }

    /**
     * The payments' values of an order's event, from the order as it is now.
     *
     * @return array<string, string>
     */
    public function orderValues(?Model $subject): array
    {
        if (! $subject instanceof Order) {
            return [];
        }

        $order = $subject;
        $block = is_array($order->payment) ? $order->payment : null;
        $locale = (string) ($order->locale ?: $this->container->make(Locales::class)->defaultCode());
        $closed = $order->paid_at !== null || $order->cancelled_at !== null;
        $owes = ! $closed && Balance::of($order)->due > 0;

        return [
            'payment_url' => $owes ? $this->container->make(PaymentLinks::class)->page($order) : '',
            'payment_instructions' => $owes && $block !== null ? Instructions::text($block, (string) $order->number, $locale) : '',
            'payment_method' => $block !== null ? PaymentSnapshot::summary($block, $locale) : '',
        ];
    }

    /** `PaymentProblem` → the channels of `payments.problem` (§11.5). */
    public function problem(PaymentProblem $event): void
    {
        $channels = OrderChannels::ids($this->channels());

        if ($channels === [] || ! $this->container->make(NotificationEvents::class)->has(self::PROBLEM)) {
            return;
        }

        $payment = Payment::query()->find($event->paymentId);
        $order = Order::query()->find($event->orderId);
        $locale = $this->container->make(Locales::class)->defaultCode();
        $method = PaymentMethod::query()->where('key', $event->methodKey)->first();

        $this->container->make(Notifications::class)->send(
            self::PROBLEM,
            $channels,
            [
                'number' => (string) ($order->number ?? ''),
                'order_url' => OrderValues::panelUrl($event->orderId),
                'problem' => trans('webx-payments::statuses.problems.'.$event->problem, [], $locale),
                'amount' => $this->container->make(Money::class)->format($event->amount, $event->currency),
                'method' => Words::pick($method?->title, $locale) ?? $event->methodKey,
                'gateway_ref' => (string) $event->gatewayRef,
                'message' => $event->message,
            ],
            subject: $payment,
            locale: $locale,
        );
    }

    /** The channels chosen for a problem: the site's setting, saved empty or not, else the config. */
    public function channels(): mixed
    {
        if ($this->container->bound(Settings::class)) {
            $stored = $this->container->make(Settings::class)->raw();

            if (array_key_exists(self::SETTING, $stored)) {
                return is_array($stored[self::SETTING]) ? $stored[self::SETTING] : [];
            }
        }

        return $this->container->make('config')->get('webx-payments.notices.problem', []);
    }
}
