<?php

declare(strict_types=1);

namespace WebxUi\Orders\Mcp;

use WebxUi\Mcp\McpResource;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\Events\OrderCancelled;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Events\OrderPlaced;
use WebxUi\Orders\Events\OrderReceived;
use WebxUi\Orders\Events\OrderRefunded;
use WebxUi\Orders\Milestone;
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
                .'write them, and the settings of the section. Read it before changing an order or a status.',
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
            'settings' => $this->settings->describe(),
            'sources' => ['panel', 'mcp', 'api', 'checkout', 'status#<id> — a milestone a status set'],
        ];
    }
}
