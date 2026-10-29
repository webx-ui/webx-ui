<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\OrderStatuses;
use WebxUi\Orders\StatusColor;
use WebxUi\Orders\StatusPlan;
use WebxUi\Orders\StatusRefused;

/**
 * The screen `orders.statuses` (spec §11.4) as the panel draws it: the public groups in their
 * order, each with its internal statuses and the orders standing in every one, then what is
 * deleted, to bring back. And the consequences of a change before it is made (§5.6) — the lines
 * a dialog shows, written here so the agent says the same.
 *
 * Three queries whatever the number of statuses: both sets with the deleted ones, and the orders
 * counted by status.
 */
final readonly class StatusBoard
{
    public function __construct(private OrderStatuses $statuses) {}

    /**
     * @return array<string, mixed>
     */
    public function describe(string $locale): array
    {
        $groups = PublicStatus::withTrashed()->orderBy('position')->orderBy('id')->get();
        $statuses = OrderStatus::withTrashed()->orderBy('position')->orderBy('id')->get();
        $counts = $this->statuses->counts();
        $live = [];
        $deletedGroups = [];
        $deleted = [];

        foreach ($groups as $group) {
            if ($group->trashed()) {
                $deletedGroups[] = [...$this->group($group, $locale), 'statuses' => []];
            } else {
                $live[$group->id] = [...$this->group($group, $locale), 'statuses' => [], 'orders' => 0];
            }
        }

        $names = $groups->mapWithKeys(static fn (PublicStatus $group): array => [$group->id => (string) $group->getTranslation('name', $locale)])->all();

        foreach ($statuses as $status) {
            $row = $this->status($status, $locale, $counts[$status->id] ?? 0);

            if ($status->trashed() || ! isset($live[$status->public_status_id])) {
                $deleted[] = [
                    ...$row,
                    'public' => ['id' => $status->public_status_id, 'name' => $names[$status->public_status_id] ?? '', 'deleted' => ! isset($live[$status->public_status_id])],
                ];

                continue;
            }

            $live[$status->public_status_id]['statuses'][] = $row;
            $live[$status->public_status_id]['orders'] += $row['orders'];
        }

        return [
            'groups' => array_values($live),
            'deleted' => ['groups' => $deletedGroups, 'statuses' => $deleted],
            'initial' => $this->statuses->initial(),
            'colors' => array_map(static fn (StatusColor $color): array => ['value' => $color->value, 'label' => $color->label()], StatusColor::cases()),
            'milestones' => array_map(static fn (Milestone $milestone): array => ['value' => $milestone->value, 'label' => $milestone->label()], Milestone::settable()),
        ];
    }

    /**
     * What a change does, in the panel's words: how many orders it touches, which buyers see
     * another name, and — when it cannot be done — why.
     *
     * @param  'delete'|'delete-public'|'save'|'arrange'|'restore'  $change
     * @return array{refusal: string|null, message: string|null, orders: int, rebound: list<array<string, mixed>>, active: list<array{id: int, name: string}>, lines: list<string>}
     */
    public function consequences(StatusPlan $plan, string $change, string $locale): array
    {
        $ids = array_values(array_unique([...array_column($plan->rebound, 'id'), ...$plan->active, ...($plan->id !== null && $change !== 'delete-public' ? [$plan->id] : [])]));
        $statuses = OrderStatus::withTrashed()->whereKey($ids)->get()->keyBy('id');
        $groupIds = array_values(array_unique([...array_column($plan->rebound, 'from'), ...array_column($plan->rebound, 'to'), ...$statuses->pluck('public_status_id')->all(), ...($change === 'delete-public' && $plan->id !== null ? [$plan->id] : [])]));
        $groups = PublicStatus::withTrashed()->whereKey($groupIds)->get()->keyBy('id');
        $statusName = static fn (int $id): string => (string) $statuses->get($id)?->getTranslation('name', $locale);
        $groupName = static fn (int $id): string => (string) $groups->get($id)?->getTranslation('name', $locale);

        $rebound = array_map(static fn (array $move): array => [
            ...$move,
            'name' => $statusName($move['id']),
            'from_name' => $groupName($move['from']),
            'to_name' => $groupName($move['to']),
        ], $plan->rebound);

        $lines = [];
        $own = $plan->id !== null ? $statuses->get($plan->id) : null;

        if ($change === 'delete' && $own !== null) {
            $lines[] = (string) __('webx-orders::panel.statuses.delete-orders', ['status' => $statusName($own->id), 'count' => $plan->orders]);
            $lines[] = (string) __('webx-orders::panel.statuses.delete-stay', ['public' => $groupName($own->public_status_id)]);
            $lines[] = (string) __('webx-orders::panel.statuses.delete-restore');
        }

        if ($change === 'delete-public' && $plan->id !== null && $plan->refusal === null) {
            $lines[] = (string) __('webx-orders::panel.statuses.delete-public-stay', ['public' => $groupName($plan->id)]);
        }

        foreach ($rebound as $move) {
            $lines[] = (string) __('webx-orders::panel.statuses.rebind', [
                'status' => $move['name'],
                'from' => $move['from_name'],
                'to' => $move['to_name'],
                'count' => $move['orders'],
            ]);
        }

        if ($rebound !== [] || $change === 'delete') {
            $lines[] = (string) __('webx-orders::panel.statuses.no-messages');
        }

        $public = $change === 'delete-public' && $plan->id !== null ? $groupName($plan->id) : ($own !== null ? $groupName($own->public_status_id) : '');

        return [
            'refusal' => $plan->refusal,
            'message' => $plan->refusal !== null ? self::refusal($plan->refusal, $public) : null,
            'orders' => $plan->orders,
            'rebound' => $rebound,
            'active' => array_values(array_map(static fn (int $id): array => ['id' => $id, 'name' => $statusName($id)], $plan->active)),
            'lines' => $lines,
        ];
    }

    /** Why the change is refused, and what to do instead. */
    public static function refusal(string $code, string $public = ''): string
    {
        return (string) __('webx-orders::panel.statuses.refusals.'.$code, ['public' => $public]);
    }

    /** The refusal of the service, as the panel says it. */
    public function refused(StatusRefused $refused, string $change, string $locale): string
    {
        return (string) $this->consequences($refused->plan, $change, $locale)['message'];
    }

    /**
     * @return array<string, mixed>
     */
    private function group(PublicStatus $group, string $locale): array
    {
        return [
            'id' => $group->id,
            'code' => $group->code,
            'name' => $group->getTranslations('name'),
            'description' => $group->getTranslations('description'),
            'label' => (string) $group->getTranslation('name', $locale),
            'deleted' => $group->trashed(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function status(OrderStatus $status, string $locale, int $orders): array
    {
        return [
            'id' => $status->id,
            'public_status_id' => $status->public_status_id,
            'name' => $status->getTranslations('name'),
            'label' => (string) $status->getTranslation('name', $locale),
            'color' => $status->color->value,
            'milestone' => $status->sets_milestone?->value,
            'active' => $status->is_active,
            'deleted' => $status->trashed(),
            'orders' => $orders,
        ];
    }
}
