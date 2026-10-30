<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;

/**
 * Both sets of statuses as the panel picks from them (spec §11.2, §11.3): the public groups in
 * their order, each with its internal statuses — deleted ones too, marked, because the filter of
 * the list still finds the orders standing in them, while the choice of the card offers only the
 * active ones. Two queries whatever the number of statuses; the list reads its rows' statuses
 * from here rather than one a row.
 */
final class StatusChoices
{
    /** @var array<int, OrderStatus>|null */
    private ?array $statuses = null;

    /** @var array<int, PublicStatus>|null */
    private ?array $groups = null;

    /**
     * The groups with their statuses, for the filters and the card's choice.
     *
     * @return list<array{id: int, code: string, name: string, deleted: bool, statuses: list<array<string, mixed>>}>
     */
    public function grouped(string $locale): array
    {
        $this->load();
        $groups = [];

        foreach ($this->groups ?? [] as $group) {
            $groups[$group->id] = [
                'id' => $group->id,
                'code' => $group->code,
                'name' => (string) $group->getTranslation('name', $locale),
                'deleted' => $group->deleted_at !== null,
                'statuses' => [],
            ];
        }

        foreach ($this->statuses ?? [] as $status) {
            if (isset($groups[$status->public_status_id])) {
                $groups[$status->public_status_id]['statuses'][] = $this->status($status, $locale);
            }
        }

        return array_values($groups);
    }

    /**
     * One internal status with its group, as a row of the list or the card's head shows it.
     *
     * @return array{id: int, name: string, color: string, milestone: string|null, active: bool, deleted: bool, public: array{id: int, code: string, name: string}|null}|null
     */
    public function find(int $id, string $locale): ?array
    {
        $this->load();
        $status = $this->statuses[$id] ?? null;

        if ($status === null) {
            return null;
        }

        $group = $this->groups[$status->public_status_id] ?? null;

        return [
            ...$this->status($status, $locale),
            'public' => $group === null ? null : [
                'id' => $group->id,
                'code' => $group->code,
                'name' => (string) $group->getTranslation('name', $locale),
            ],
        ];
    }

    /** The model, deleted ones too, for a move the panel asked for. */
    public function model(int $id): ?OrderStatus
    {
        $this->load();

        return $this->statuses[$id] ?? null;
    }

    /**
     * @return array{id: int, name: string, color: string, milestone: string|null, active: bool, deleted: bool}
     */
    private function status(OrderStatus $status, string $locale): array
    {
        return [
            'id' => $status->id,
            'name' => (string) $status->getTranslation('name', $locale),
            'color' => $status->color->value,
            'milestone' => $status->sets_milestone?->value,
            'active' => $status->is_active,
            'deleted' => $status->deleted_at !== null,
        ];
    }

    private function load(): void
    {
        if ($this->statuses !== null) {
            return;
        }

        $this->groups = PublicStatus::withTrashed()->orderBy('position')->orderBy('id')->get()->keyBy('id')->all();
        $this->statuses = OrderStatus::withTrashed()->orderBy('position')->orderBy('id')->get()->keyBy('id')->all();
    }
}
