<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasVisibility;

/**
 * One entry of «Delivery» in the panel's menu (§14.2).
 *
 * The section is six screens, and the navigation is one entry per module: so it is six modules
 * in one group — the methods (which declare the permissions), the shop's points, the carriers,
 * the geography, the popular towns and the binding queue. Only the first declares permissions;
 * the rest are opened with the same two (`delivery.view`, `delivery.manage`).
 *
 * Every entry says so through {@see HasVisibility}: an entry with no permissions of its own would
 * otherwise be shown to everybody signed in — a role with only `payments.view` saw the points, the
 * carriers and the geography. The two are the ones the section's reading routes ask for, so the
 * menu shows exactly what the server lets in; `delivery.ship` alone opens only the order's tab.
 */
class DeliverySection extends AbstractModule implements HasVisibility
{
    /** The id of the navigation group. */
    public const string GROUP = 'delivery';

    /** Reads every screen of the section: methods, points, carriers and their runs, geography. */
    public const string VIEW = 'delivery.view';

    /** Writes them: methods, points, carriers and their keys, the sync, geography, the queue. */
    public const string MANAGE = 'delivery.manage';

    /** The waybill and the tracking of an order — a manager's, without the settings (§14.1). */
    public const string SHIP = 'delivery.ship';

    /** The sections in menu order: id → [dictionary key, icon]. The first is the module itself. */
    public const array SECTIONS = [
        'delivery' => ['panel.sections.methods', 'send'],
        'delivery-points' => ['panel.sections.points', 'map-pin'],
        'delivery-carriers' => ['panel.sections.carriers', 'briefcase'],
        'delivery-geography' => ['panel.sections.geography', 'grid'],
        'delivery-popular' => ['panel.sections.popular', 'star'],
        'delivery-bindings' => ['panel.sections.bindings', 'link'],
    ];

    /** Where the group stands: after the orders (350) and the customers (400). */
    public const int ORDER = 360;

    public function __construct(private readonly string $id) {}

    /** @return list<self> */
    public static function all(): array
    {
        return array_map(static fn (string $id): self => new self($id), array_keys(self::SECTIONS));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return (string) __('webx-delivery::'.self::SECTIONS[$this->id][0]);
    }

    public function icon(): string
    {
        return self::SECTIONS[$this->id][1];
    }

    public function order(): int
    {
        return self::ORDER + (int) array_search($this->id, array_keys(self::SECTIONS), true);
    }

    public function group(): string
    {
        return self::GROUP;
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->id === 'delivery' ? [self::VIEW, self::MANAGE, self::SHIP] : [];
    }

    /** @return list<string> */
    public function visibleWith(): array
    {
        return [self::VIEW, self::MANAGE];
    }
}
