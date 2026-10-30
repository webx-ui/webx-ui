<?php

declare(strict_types=1);

namespace WebxUi\Orders\Notifications;

use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Events\Variable;

/**
 * What a channel of `webx-ui/module-notifications` is told about an order (spec §10, §40.2): nine
 * events, one set of values, and the default templates of each.
 *
 * Only ever touched when the notifications module is installed: the provider declares the events
 * under `class_exists`, and the rest of this module names none of the classes imported here.
 *
 * The events of the milestones take their channels from the settings of the section
 * (`orders.notify.*`); `orders.public_status` from the public status the order came into
 * (`options.notify_channels`) — a move inside a group is not an event at all (decision 12).
 */
final class OrderEvents
{
    public const string PLACED = 'orders.placed';

    public const string PUBLIC_STATUS = 'orders.public_status';

    public const string PAID = 'orders.paid';

    public const string PAYMENT_FAILED = 'orders.payment_failed';

    public const string RECEIVED = 'orders.received';

    public const string CANCELLED = 'orders.cancelled';

    public const string REFUNDED = 'orders.refunded';

    /** The lines were edited and the manager chose to tell the buyer (§40.2). */
    public const string ITEMS_CHANGED = 'orders.items_changed';

    /** Only by hand: «pay for the order» / «pay the rest» while the buyer owes (§40.2). */
    public const string PAYMENT_REQUESTED = 'orders.payment_requested';

    /** The events of the milestones and the setting each keeps its channels in. */
    public const array SETTINGS = [
        self::PLACED => 'orders.notify.placed',
        self::PAID => 'orders.notify.paid',
        self::PAYMENT_FAILED => 'orders.notify.payment_failed',
        self::RECEIVED => 'orders.notify.received',
        self::CANCELLED => 'orders.notify.cancelled',
        self::REFUNDED => 'orders.notify.refunded',
        self::ITEMS_CHANGED => 'orders.notify.items_changed',
        self::PAYMENT_REQUESTED => 'orders.notify.payment_requested',
    ];

    /** Beyond the values every event has (§10.2). */
    private const array EXTRA = [
        self::PLACED => [],
        self::PUBLIC_STATUS => [],
        self::PAID => ['reference'],
        self::PAYMENT_FAILED => ['reason'],
        self::RECEIVED => ['reference'],
        self::CANCELLED => ['reason'],
        self::REFUNDED => ['refund_amount'],
        // Never the edit's `reason`: the buyer is told the manager's `message` alone (§40.1).
        self::ITEMS_CHANGED => ['changes', 'previous_total', 'balance', 'balance_kind', 'amount_due', 'amount_over', 'message'],
        self::PAYMENT_REQUESTED => ['amount_due'],
    ];

    /** Who the default template of an event writes to: the buyer, or the shop. */
    public const array AUDIENCE = [
        self::PLACED => OrderTemplates::MANAGER,
        self::PUBLIC_STATUS => OrderTemplates::CUSTOMER,
        self::PAID => OrderTemplates::CUSTOMER,
        self::PAYMENT_FAILED => OrderTemplates::MANAGER,
        self::RECEIVED => OrderTemplates::CUSTOMER,
        self::CANCELLED => OrderTemplates::CUSTOMER,
        self::REFUNDED => OrderTemplates::CUSTOMER,
        self::ITEMS_CHANGED => OrderTemplates::CUSTOMER,
        self::PAYMENT_REQUESTED => OrderTemplates::CUSTOMER,
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::EXTRA);
    }

    /**
     * @return list<EventDefinition>
     */
    public static function definitions(): array
    {
        return array_map(self::definition(...), self::keys());
    }

    public static function definition(string $key): EventDefinition
    {
        $audience = self::AUDIENCE[$key];

        return new EventDefinition(
            key: $key,
            label: 'webx-orders::notifications.events.'.substr($key, strlen('orders.')),
            module: 'orders',
            variables: [...self::common(), ...array_map(self::extra(...), self::EXTRA[$key])],
            // The default templates (§10.2): a starting point in every language there are words in.
            sample: [
                'email' => static fn (string $locale): ?array => OrderTemplates::email($key, $audience, $locale),
                'telegram' => static fn (string $locale): ?array => OrderTemplates::telegram($key, $audience, $locale),
                '*' => static fn (string $locale): ?array => OrderTemplates::text($key, $audience, $locale),
            ],
            url: static fn (array $values): ?string => is_string($values['order_url'] ?? null) && $values['order_url'] !== ''
                ? $values['order_url']
                : null,
        );
    }

    /**
     * @return list<Variable>
     */
    private static function common(): array
    {
        $vars = 'webx-orders::notifications.vars.';
        $one = static fn (string $name, string $example, ?string $address = null): Variable => new Variable($name, $vars.$name, example: $example, address: $address);

        return [
            $one('number', '1024'),
            $one('order_url', 'https://site.test/cms/orders/1024'),
            $one('public_url', 'https://site.test/order/1024?signature=0000'),
            $one('placed_at', '10/27/2026 2:05 PM'),
            $one('customer_name', 'Buyer A'),
            $one('customer_first_name', 'Buyer'),
            $one('customer_last_name', 'A'),
            $one('customer_phone', '+800 1234 5678'),
            // An addressee (decision 12 of the notifications): a channel may write to the buyer.
            $one('customer_email', 'buyer-a@example.com', 'email'),
            $one('recipient_name', 'Recipient B'),
            $one('recipient_first_name', 'Recipient'),
            $one('recipient_last_name', 'B'),
            $one('recipient_phone', '+800 1234 5679'),
            new Variable('items', $vars.'items', example: [
                ['name' => 'Item A', 'options' => 'Red, M', 'qty' => '2', 'price' => '€49.99', 'total' => '€99.98', 'child' => ''],
                ['name' => 'Set B', 'options' => '', 'qty' => '1', 'price' => '€20.02', 'total' => '€20.02', 'child' => ''],
            ], fields: [
                'name' => $vars.'item_name',
                'options' => $vars.'item_options',
                'qty' => $vars.'item_qty',
                'price' => $vars.'item_price',
                'total' => $vars.'item_total',
                'child' => $vars.'item_child',
            ]),
            $one('subtotal', '€120.00'),
            $one('discount', '-€10.00'),
            $one('total', '€110.00'),
            $one('tenders', '-€5.00'),
            $one('payable', '€105.00'),
            $one('delivery', 'Branch 12, Town D'),
            $one('payment', 'Online by card'),
            $one('comment', 'Call before coming, please.'),
            $one('public_status', 'In progress'),
            $one('public_status_code', 'in_progress'),
            $one('public_status_description', 'We are putting your order together.'),
            // The buyer never sees the internal status (decision 11): a channel to them warns.
            new Variable('status', $vars.'status', example: 'Picker is looking', internal: true),
        ];
    }

    private static function extra(string $name): Variable
    {
        $vars = 'webx-orders::notifications.vars.';

        if ($name === 'changes') {
            return new Variable('changes', $vars.'changes', example: [
                ['name' => 'Item A', 'options' => 'Red, M', 'qty_from' => '2', 'qty_to' => '3', 'added' => '', 'removed' => ''],
                ['name' => 'Item C', 'options' => '', 'qty_from' => '0', 'qty_to' => '1', 'added' => 'yes', 'removed' => ''],
            ], fields: [
                'name' => $vars.'item_name',
                'options' => $vars.'item_options',
                'qty_from' => $vars.'change_qty_from',
                'qty_to' => $vars.'change_qty_to',
                'added' => $vars.'change_added',
                'removed' => $vars.'change_removed',
            ]);
        }

        $examples = [
            'reference' => 'TX-0001',
            'reason' => 'The buyer changed their mind',
            'refund_amount' => '€20.00',
            'previous_total' => '€95.00',
            'balance' => '€15.00',
            'balance_kind' => 'due',
            'message' => 'We added the charger you asked for on the phone.',
            'amount_due' => '€15.00',
            'amount_over' => '',
        ];

        return new Variable($name, $vars.$name, example: $examples[$name]);
    }
}
