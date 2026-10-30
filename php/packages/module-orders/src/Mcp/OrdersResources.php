<?php

declare(strict_types=1);

namespace WebxUi\Orders\Mcp;

use WebxUi\Mcp\McpResource;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\Events\OrderCancelled;
use WebxUi\Orders\Events\OrderItemsChanged;
use WebxUi\Orders\Events\OrderItemsChanging;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Events\OrderPlaced;
use WebxUi\Orders\Events\OrderReceived;
use WebxUi\Orders\Events\OrderRefunded;
use WebxUi\Orders\ItemsEditResult;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Notifications\OrderChannels;
use WebxUi\Orders\Notifications\OrderEvents;
use WebxUi\Orders\OrderSettings;
use WebxUi\Orders\OrderStatuses;
use WebxUi\Orders\Panel\StatusChoices;
use WebxUi\Orders\StatusColor;

/**
 * What an agent reads before it works the orders (spec §12): the milestones and who sets them,
 * the palette, both levels of the statuses, the blocks and the modules that write them, the
 * settings. Read off the enums, the registries and the tables, never written by hand: a block a
 * neighbour registers, or a status the shop adds, is here the moment it exists.
 */
final readonly class OrdersResources
{
    /** The event each milestone comes with; «awaiting payment» comes with the order's own. */
    private const array EVENTS = [
        'placed' => OrderPlaced::class,
        'awaiting_payment' => OrderPlaced::class,
        'paid' => OrderPaid::class,
        'received' => OrderReceived::class,
        'cancelled' => OrderCancelled::class,
        'refunded' => OrderRefunded::class,
    ];

    /** Who sets it, in the words of the tools. */
    private const array SET_BY = [
        'placed' => 'the order itself, when it is placed',
        'awaiting_payment' => 'the order itself, when its payment method awaits payment',
        'paid' => 'orders_mark, a status that sets it, or the payment module',
        'received' => 'orders_mark, a status that sets it, or the delivery module',
        'cancelled' => 'orders_cancel, or a status that sets it (with the reason in its note)',
        'refunded' => 'orders_refund, when money and every tender are back',
    ];

    public function __construct(
        private OrderStatuses $statuses,
        private OrderBlocks $blocks,
        private OrderSettings $settings,
        private OrderChannels $channels,
    ) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'orders://catalog',
                'Orders catalog',
                'The milestones of an order (who sets each, the event it comes with, whether a status may set it), the palette '
                .'of a status, both levels of the statuses with the ones new orders come into, the blocks and the modules that '
                .'write them, the events the notifications module is told of with their values, and the settings of the '
                .'section. Read it before changing an order or a status.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $locale = app()->getLocale();

        return [
            'milestones' => array_map(static fn (Milestone $milestone): array => [
                'key' => $milestone->value,
                'label' => $milestone->label($locale),
                'set_by' => self::SET_BY[$milestone->value],
                'event' => self::EVENTS[$milestone->value],
                'settable_by_status' => $milestone->settableByStatus(),
            ], Milestone::cases()),
            'colors' => array_map(static fn (StatusColor $color): array => ['value' => $color->value, 'label' => $color->label($locale)], StatusColor::cases()),
            'statuses' => (new StatusChoices)->grouped($locale),
            'initial_statuses' => $this->statuses->initial(),
            'blocks' => array_map(fn (string $key): array => [
                'key' => $key,
                'module' => $this->blocks->get($key)?->module,
            ], OrderBlocks::KEYS),
            'notifications' => $this->notifications($locale),
            'settings' => $this->settings->describe(),
            'sources' => ['panel', 'mcp', 'api', 'checkout', 'status#<id> — a milestone a status set'],
            // orders_items_edit (§37): why an edit is refused, and what its dry run warns of.
            'items_edit' => [
                'refusals' => array_map(static fn (string $code): array => [
                    'code' => $code,
                    'message' => (string) __('webx-orders::panel.items-refusals.'.$code, [], $locale),
                ], ItemsEditResult::REFUSALS),
                'warnings' => [
                    ItemsEditResult::WARN_PAID, ItemsEditResult::WARN_ADJUSTMENT_KEPT, ItemsEditResult::WARN_BENEFIT_KEPT,
                    ItemsEditResult::WARN_NOTHING_SHIPPABLE, ItemsEditResult::WARN_TENDERS_OVER, 'line.* — the cart\'s, for a line added',
                ],
                'events' => [OrderItemsChanging::class, OrderItemsChanged::class],
            ],
        ];
    }

    /**
     * The events of an order and where each takes its channels from (§10.1) — null without the
     * notifications module, where nobody is told of anything.
     *
     * @return list<array<string, mixed>>|null
     */
    private function notifications(string $locale): ?array
    {
        if (! $this->channels->available()) {
            return null;
        }

        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => (string) __(OrderEvents::definition($key)->label, [], $locale),
            'channels_from' => OrderEvents::SETTINGS[$key] ?? 'the public status the order comes into: notify_channels',
            'channels' => isset(OrderEvents::SETTINGS[$key]) ? $this->channels->forEvent($key) : null,
            'variables' => array_map(static fn ($variable): array => [
                'name' => $variable->name,
                'internal' => $variable->internal,
                'address' => $variable->address,
            ], OrderEvents::definition($key)->variables),
        ], OrderEvents::keys());
    }
}
