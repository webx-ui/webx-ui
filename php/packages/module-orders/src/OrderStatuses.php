<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebxUi\Cache\CacheGroups;
use WebxUi\Localization\Locales;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\Notifications\OrderChannels;
use WebxUi\Orders\Site\OrderPage;

/**
 * The two sets of statuses as the screen `orders.statuses` and the agent write them (spec §5.6,
 * §11.4): the groups the buyer sees and the stages of the shop inside them — their names, order,
 * colours and milestones; deleting softly and bringing back.
 *
 * What it guards is that an order always has somewhere to go: the status new orders come into
 * is not deleted or switched off, a group with orders keeps an active status, a public group
 * goes only when no status lives in it. Each refusal is a {@see StatusRefused} with the dry run's
 * answer; a dry run answers the same and writes nothing. The orders themselves are never written
 * here — they stay in their status, and what the buyer sees follows the status.
 */
final readonly class OrderStatuses
{
    /** A public code: what templates and agents say (`in_progress`). */
    public const string CODE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private InitialStatuses $initial,
        private ConnectionInterface $db,
        private Validation $validation,
        private Locales $locales,
        private CacheGroups $groups,
    ) {}

    /**
     * A new public group at the end, or the one given with its code, name and description.
     *
     * @param  array<string, mixed>  $input  `code`, `name`, `description` — the last two by language
     *
     * @throws ValidationException
     */
    public function writePublic(array $input, ?PublicStatus $status = null): PublicStatus
    {
        $fallback = $this->locales->fallback();
        $code = $input['code'] ?? null;
        $input['code'] = is_string($code) ? strtolower(trim($code)) : $code;

        /** @var array{code: string, name: array<string, string|null>, description?: array<string, string|null>|null, notify_channels?: list<int>|null} $valid */
        $valid = $this->validation->make($input, [
            'code' => ['required', 'string', 'max:48', 'regex:'.self::CODE, Rule::unique('order_public_statuses', 'code')->ignore($status?->id)],
            'name' => ['required', 'array'],
            "name.{$fallback}" => ['required', 'string', 'max:120'],
            'name.*' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:255'],
            'notify_channels' => ['nullable', 'array'],
            'notify_channels.*' => ['integer', 'min:1'],
        ], [
            'code.regex' => (string) __('webx-orders::panel.statuses.errors.code'),
            'code.unique' => (string) __('webx-orders::panel.statuses.errors.code-taken'),
        ], [
            'code' => (string) __('webx-orders::panel.statuses.code'),
            'name' => (string) __('webx-orders::panel.statuses.name'),
            "name.{$fallback}" => (string) __('webx-orders::panel.statuses.name'),
            'name.*' => (string) __('webx-orders::panel.statuses.name'),
            'description.*' => (string) __('webx-orders::panel.statuses.description'),
            'notify_channels' => (string) __('webx-orders::panel.statuses.channels'),
            'notify_channels.*' => (string) __('webx-orders::panel.statuses.channels'),
        ])->validate();

        $status ??= new PublicStatus(['position' => (int) PublicStatus::withTrashed()->max('position') + 1]);
        $status->code = $valid['code'];
        $status->setAttribute('name', self::words($valid['name']));
        $description = self::words($valid['description'] ?? []);
        $status->setAttribute('description', $description === [] ? null : $description);

        // The channels told when an order comes into the group (§10.1); left out — as they were.
        if (array_key_exists('notify_channels', $valid)) {
            $options = $status->options ?? [];
            $options[OrderChannels::OPTION] = app(OrderChannels::class)->existing(OrderChannels::ids($valid['notify_channels'] ?? []));
            $status->options = $options;
        }

        $status->save();

        return $status;
    }

    /**
     * Delete a public group softly (§5.6). Only a group no status lives in may go; given `moveTo`,
     * its statuses go there first, in the same transaction. Orders in the deleted statuses of the
     * group keep showing its name — the row stays — and its channels fire no more.
     *
     * @throws StatusRefused
     * @throws ValidationException `move_to` deleted, or the group itself
     */
    public function removePublic(PublicStatus $status, ?PublicStatus $moveTo = null, bool $dryRun = false): StatusPlan
    {
        if ($moveTo !== null && ($moveTo->trashed() || $moveTo->id === $status->id)) {
            throw ValidationException::withMessages(['move_to' => (string) __('webx-orders::panel.statuses.errors.move-to')]);
        }

        if ($status->trashed()) {
            return new StatusPlan(dryRun: $dryRun, id: $status->id);
        }

        $counts = $this->counts();
        $alive = OrderStatus::query()->where('public_status_id', $status->id)->orderBy('position')->orderBy('id')->get();
        $orders = 0;
        $rebound = [];

        foreach ($alive as $one) {
            $orders += $counts[$one->id] ?? 0;

            if ($moveTo !== null) {
                $rebound[] = ['id' => $one->id, 'from' => $status->id, 'to' => $moveTo->id, 'orders' => $counts[$one->id] ?? 0];
            }
        }

        $refusal = $alive->isNotEmpty() && $moveTo === null ? StatusRefused::HAS_ACTIVE : null;
        $plan = new StatusPlan($refusal, $orders, $rebound, array_values($alive->modelKeys()), $dryRun, $status->id);

        if ($dryRun) {
            return $plan;
        }

        if ($refusal !== null) {
            throw new StatusRefused($plan);
        }

        $this->db->transaction(function () use ($status, $alive, $moveTo): void {
            $position = (int) OrderStatus::withTrashed()->max('position');

            foreach ($alive as $one) {
                $one->public_status_id = (int) $moveTo?->id;
                $one->position = ++$position;
                $one->save();
            }

            $status->delete();
        });

        return $plan->done($status->id);
    }

    /** A deleted public group back as it was — its name, its place, its channels. */
    public function restorePublic(PublicStatus $status): PublicStatus
    {
        if ($status->trashed()) {
            $status->restore();
        }

        return $status;
    }

    /**
     * A new internal status at the end of its group, or the one given changed (§11.4). Moving it to
     * another public group changes what the buyers of its orders see, at once and without a message
     * — the plan counts them. Switching it off is held to the same rules as deleting it.
     *
     * @param  array<string, mixed>  $input  `public_status_id`, `name` by language, `color`, `sets_milestone`, `is_active`
     *
     * @throws ValidationException
     * @throws StatusRefused
     */
    public function write(array $input, ?OrderStatus $status = null, bool $dryRun = false): StatusPlan
    {
        $fallback = $this->locales->fallback();

        /** @var array{public_status_id: int, name: array<string, string|null>, color: string, sets_milestone?: string|null, is_active?: bool} $valid */
        $valid = $this->validation->make($input, [
            'public_status_id' => ['required', 'integer', Rule::exists('order_public_statuses', 'id')],
            'name' => ['required', 'array'],
            "name.{$fallback}" => ['required', 'string', 'max:120'],
            'name.*' => ['nullable', 'string', 'max:120'],
            'color' => ['required', Rule::enum(StatusColor::class)],
            'sets_milestone' => ['nullable', Rule::in(array_map(static fn (Milestone $milestone): string => $milestone->value, Milestone::settable()))],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'public_status_id' => (string) __('webx-orders::panel.statuses.group'),
            'name' => (string) __('webx-orders::panel.statuses.name'),
            "name.{$fallback}" => (string) __('webx-orders::panel.statuses.name'),
            'name.*' => (string) __('webx-orders::panel.statuses.name'),
            'color' => (string) __('webx-orders::panel.statuses.color'),
            'sets_milestone' => (string) __('webx-orders::panel.statuses.milestone'),
        ])->validate();

        $group = PublicStatus::withTrashed()->findOrFail((int) $valid['public_status_id']);
        $active = (bool) ($valid['is_active'] ?? true);
        $orders = $status !== null ? ($this->counts()[$status->id] ?? 0) : 0;
        $rebound = $status !== null && $status->public_status_id !== $group->id
            ? [['id' => $status->id, 'from' => $status->public_status_id, 'to' => $group->id, 'orders' => $orders]]
            : [];

        // A deleted status may be bound anew to a deleted group: that is how it is readied to come back.
        $refusal = match (true) {
            $group->trashed() && ($status === null || ! $status->trashed()) => StatusRefused::PUBLIC_DELETED,
            $status !== null && ! $status->trashed() && $status->is_active && ! $active => $this->leaving($status, true),
            $rebound !== [] && $status !== null && ! $status->trashed() && $status->is_active => $this->leaving($status, false),
            default => null,
        };

        $plan = new StatusPlan($refusal, $orders, $rebound, [], $dryRun, $status?->id);

        if ($dryRun) {
            return $plan;
        }

        if ($refusal !== null) {
            throw new StatusRefused($plan);
        }

        $status ??= new OrderStatus;

        if (! $status->exists || $rebound !== []) {
            $status->position = (int) OrderStatus::withTrashed()->max('position') + 1;
        }

        $status->public_status_id = $group->id;
        $status->setAttribute('name', self::words($valid['name']));
        $status->color = StatusColor::from($valid['color']);
        $status->sets_milestone = isset($valid['sets_milestone']) ? Milestone::from($valid['sets_milestone']) : null;
        $status->is_active = $active;
        $status->save();

        return $plan->done($status->id);
    }

    /**
     * Delete an internal status softly (§5.6): its orders stay in it, marked «deleted» in the list,
     * and buyers keep seeing its group; no order comes into it any more, and nobody hears of it.
     *
     * @throws StatusRefused the status new orders come into, or the last active one of a group with orders
     */
    public function remove(OrderStatus $status, bool $dryRun = false): StatusPlan
    {
        $orders = $this->counts()[$status->id] ?? 0;
        $refusal = ! $status->trashed() && $status->is_active ? $this->leaving($status, true) : null;
        $plan = new StatusPlan($refusal, $orders, [], [], $dryRun, $status->id);

        if ($dryRun || $status->trashed()) {
            return $plan;
        }

        if ($refusal !== null) {
            throw new StatusRefused($plan);
        }

        $status->delete();

        return $plan->done($status->id);
    }

    /**
     * A deleted status back as it was — its name, its place, its milestone. Its group must be alive:
     * bring that back first, or bind the status to another one.
     *
     * @throws StatusRefused
     */
    public function restore(OrderStatus $status): StatusPlan
    {
        $plan = new StatusPlan(
            $status->trashed() && $status->publicStatus->trashed() ? StatusRefused::PUBLIC_DELETED : null,
            $this->counts()[$status->id] ?? 0,
            id: $status->id,
        );

        if ($plan->refusal !== null) {
            throw new StatusRefused($plan);
        }

        if ($status->trashed()) {
            $status->restore();
        }

        return $plan;
    }

    /**
     * The order of both levels as the screen was dragged into (§11.4): the public groups in their
     * order, and each group's statuses in theirs. A status dragged into another group is bound to
     * it — what its buyers see changes at once, without a message; the plan counts them.
     *
     * Every group and every status alive is named, each once; the deleted ones keep their places.
     *
     * @param  list<int>  $publics
     * @param  array<int, list<int>>  $groups  public id => its statuses, in their order
     *
     * @throws ValidationException
     * @throws StatusRefused
     */
    public function arrange(array $publics, array $groups, bool $dryRun = false): StatusPlan
    {
        $live = PublicStatus::query()->pluck('id')->map(intval(...))->all();
        $statuses = OrderStatus::query()->get()->keyBy('id');
        $named = array_merge(...array_values(array_map(static fn (array $ids): array => array_map(intval(...), $ids), $groups ?: [[]])));
        $publics = array_map(intval(...), $publics);

        if (! self::same($publics, $live) || array_diff(array_map(intval(...), array_keys($groups)), $live) !== [] || ! self::same($named, $statuses->keys()->map(intval(...))->all())) {
            throw ValidationException::withMessages(['groups' => (string) __('webx-orders::panel.statuses.errors.arrangement')]);
        }

        $counts = $this->counts();
        $rebound = [];
        $after = [];

        foreach ($groups as $group => $ids) {
            foreach ($ids as $id) {
                $status = $statuses->get((int) $id);
                $after[(int) $id] = (int) $group;

                if ($status !== null && $status->public_status_id !== (int) $group) {
                    $rebound[] = ['id' => $status->id, 'from' => $status->public_status_id, 'to' => (int) $group, 'orders' => $counts[$status->id] ?? 0];
                }
            }
        }

        $refusal = $this->emptied($rebound, $after, $statuses->all(), $counts);
        $plan = new StatusPlan($refusal, array_sum(array_column($rebound, 'orders')), $rebound, [], $dryRun);

        if ($dryRun) {
            return $plan;
        }

        if ($refusal !== null) {
            throw new StatusRefused($plan);
        }

        $this->db->transaction(function () use ($publics, $groups, $statuses): void {
            // A dozen rows, written only when the screen is dragged: one statement a row is
            // simpler than a CASE, and as quick here.
            foreach ($publics as $position => $id) {
                PublicStatus::query()->whereKey($id)->where('position', '!=', $position)->update(['position' => $position]);
            }

            // The order of the public groups is the scale on the buyer's page: no event above.
            $this->groups->bump(OrderPage::GROUP);

            $position = 0;

            foreach ($publics as $group) {
                foreach ($groups[$group] ?? [] as $id) {
                    $status = $statuses->get((int) $id);

                    if ($status !== null && ($status->position !== $position || $status->public_status_id !== $group)) {
                        $status->position = $position;
                        $status->public_status_id = $group;
                        $status->save();
                    }

                    $position++;
                }
            }
        });

        return $plan;
    }

    /**
     * The orders in each status, deleted ones too — one query.
     *
     * @return array<int, int>
     */
    public function counts(): array
    {
        return Order::query()
            ->toBase()
            ->selectRaw('status_id, count(*) as aggregate')
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id')
            ->mapWithKeys(static fn (mixed $count, mixed $id): array => [(int) $id => (int) $count])
            ->all();
    }

    /**
     * The statuses new orders come into now — the ones the settings name, or the first ones of the
     * set when they name none.
     *
     * @return list<int>
     */
    public function initial(): array
    {
        return $this->initial->ids();
    }

    /**
     * Why an active status may not stop being one of its group — deleted, switched off, or moved
     * out of it: new orders come into it, or it is the last active status of a group with orders
     * (or of the whole set — a new order would have nowhere to come into).
     */
    private function leaving(OrderStatus $status, bool $gone): ?string
    {
        if ($gone && in_array($status->id, $this->initial(), true)) {
            return StatusRefused::INITIAL;
        }

        $others = OrderStatus::query()->where('is_active', true)->whereKeyNot($status->id);

        if ($gone && ! (clone $others)->exists()) {
            return StatusRefused::LAST_ACTIVE;
        }

        if ((clone $others)->where('public_status_id', $status->public_status_id)->exists()) {
            return null;
        }

        $inGroup = OrderStatus::withTrashed()->where('public_status_id', $status->public_status_id)->select('id');

        return Order::query()->whereIn('status_id', $inGroup)->exists() ? StatusRefused::LAST_ACTIVE : null;
    }

    /**
     * The last-active rule for a whole arrangement: a group that loses its statuses must keep an
     * active one when orders stand in it.
     *
     * @param  list<array{id: int, from: int, to: int, orders: int}>  $rebound
     * @param  array<int, int>  $after  status id => its group after the arrangement
     * @param  array<int, OrderStatus>  $statuses  the live ones
     * @param  array<int, int>  $counts
     */
    private function emptied(array $rebound, array $after, array $statuses, array $counts): ?string
    {
        $left = array_unique(array_column($rebound, 'from'));

        if ($left === []) {
            return null;
        }

        $trashed = OrderStatus::onlyTrashed()->whereIn('public_status_id', $left)->get(['id', 'public_status_id']);

        foreach ($left as $group) {
            $orders = 0;
            $active = false;

            foreach ($after as $id => $in) {
                if ($in === $group) {
                    $orders += $counts[$id] ?? 0;
                    $active = $active || ($statuses[$id]->is_active ?? false);
                }
            }

            foreach ($trashed as $one) {
                $orders += $one->public_status_id === $group ? ($counts[$one->id] ?? 0) : 0;
            }

            if (! $active && $orders > 0) {
                return StatusRefused::LAST_ACTIVE;
            }
        }

        return null;
    }

    /**
     * The words of every language given, the empty ones left out.
     *
     * @param  array<string, mixed>|null  $words
     * @return array<string, string>
     */
    private static function words(?array $words): array
    {
        $kept = [];

        foreach ($words ?? [] as $locale => $value) {
            if (is_string($value) && trim($value) !== '') {
                $kept[(string) $locale] = trim($value);
            }
        }

        return $kept;
    }

    /**
     * @param  list<int>  $given
     * @param  list<int>  $expected
     */
    private static function same(array $given, array $expected): bool
    {
        sort($given);
        sort($expected);

        return $given === $expected;
    }
}
