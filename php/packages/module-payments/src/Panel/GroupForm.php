<?php

declare(strict_types=1);

namespace WebxUi\Payments\Panel;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Cache\CacheGroups;
use WebxUi\Localization\Locales;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodGroup;
use WebxUi\Payments\MethodGroups;
use WebxUi\Payments\Methods;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentGroup;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Words;

/**
 * The groups in the panel (groups spec §7): the manager's edits over the config, and the order of
 * the groups with the methods in them. Which groups there are stays the config's: nothing here
 * creates or removes one (decision 12), only a row of edits under a key the config lists.
 */
final readonly class GroupForm
{
    public function __construct(
        private MethodGroups $groups,
        private MethodTypes $types,
        private Locales $locales,
        private CacheGroups $cache,
        private Factory $views,
    ) {}

    /** @return array{groups: list<array<string, mixed>>, pictograms: list<array{name: string, svg: string}>} */
    public function describe(): array
    {
        return [
            'groups' => $this->rows(),
            'pictograms' => array_map(fn (string $name): array => [
                'name' => $name,
                // Through the view: a theme's own drawing of a pictogram is the one a manager picks.
                'svg' => trim($this->views->make('webx-payments::icons.'.$name)->render()),
            ], MethodGroups::PICTOGRAMS),
        ];
    }

    /** @return list<array<string, mixed>> the groups of the site, in their order */
    public function rows(): array
    {
        return array_map($this->row(...), $this->groups->all());
    }

    /**
     * Every method of the panel's list under the group it stands in, the groups in their order and
     * empty ones too: what `order()` takes, for a caller that changes only a part of it.
     *
     * @return array<string, list<PaymentMethod>>
     */
    public function layout(): array
    {
        $layout = [];

        foreach ($this->groups->all() as $group) {
            $layout[$group->key] = [];
        }

        foreach (PaymentMethod::query()->orderBy('position')->orderBy('id')->get() as $row) {
            $key = $this->groups->current(Method::of($row), $this->types->has($row->type) ? $this->types->get($row->type) : null);

            if ($key !== null) {
                $layout[$key][] = $row;
            }
        }

        return $layout;
    }

    /** @return array<string, mixed> */
    private function row(MethodGroup $group): array
    {
        $locale = $this->locales->current();

        return [
            'key' => $group->key,
            'title' => $group->title($locale),
            'description' => $group->description($locale),
            'config_title' => $group->configWords($locale),
            'titles' => (object) $group->titles,
            'descriptions' => (object) $group->descriptions,
            'pictogram' => $group->pictogram,
        ];
    }

    /**
     * An edit of a group: what is sent is written, what is empty goes back to the config's.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function save(string $key, array $input): array
    {
        $row = PaymentGroup::query()->firstOrNew(['key' => $key]);

        foreach (['titles' => 'title', 'descriptions' => 'description'] as $field => $column) {
            if (array_key_exists($field, $input)) {
                $row->setAttribute($column, $this->words($input[$field]));
            }
        }

        if (array_key_exists('pictogram', $input)) {
            $row->pictogram = $this->pictogram($key, $input['pictogram']);
        }

        $row->save();

        foreach ($this->groups->all() as $group) {
            if ($group->key === $key) {
                return $this->row($group);
            }
        }

        return [];
    }

    /**
     * The order as dragged: every group of the config once, every method once, in one transaction
     * (§7). A method's row changes only if it really changed group: a move to a group gone from the
     * config reads as the type's on screen, and it must survive a drag of something else (§2.6).
     *
     * @param  mixed  $input  `[{ key, methods: [id…] }]`
     */
    public function order(mixed $input): void
    {
        $order = $this->orderOf($input);
        $rows = PaymentMethod::query()->get()->keyBy('id');
        $groups = array_keys($order);

        if (! $this->same($groups, $this->groups->keys()) || ! $this->same(array_merge([], ...array_values($order)), $rows->keys()->map(intval(...))->all())) {
            throw $this->refused();
        }

        $before = array_map(static fn (MethodGroup $group): string => $group->key, $this->groups->all());

        DB::transaction(function () use ($order, $rows, $groups, $before): void {
            $position = 0;

            foreach ($order as $key => $methods) {
                foreach ($methods as $id) {
                    /** @var PaymentMethod $row */
                    $row = $rows[$id];
                    $type = $this->types->has($row->type) ? $this->types->get($row->type) : null;
                    $changes = ['position' => $position++];

                    if ($this->groups->current(Method::of($row), $type) !== $key) {
                        $changes['group_key'] = $this->groups->moved($key, $type);
                    }

                    PaymentMethod::query()->whereKey($id)->update($changes);
                }
            }

            // Only a real change of the groups' order: a group the config adds later still finds its place.
            if ($groups !== $before) {
                foreach ($groups as $position => $key) {
                    PaymentGroup::query()->updateOrCreate(['key' => $key], ['position' => $position]);
                }
            }
        });

        // A mass update fires no model events: the step's list is cached (§18 of the base spec).
        $this->cache->bump(Methods::GROUP);
    }

    /**
     * @return array<string, list<int>> the methods' ids by group, in the order sent
     */
    private function orderOf(mixed $input): array
    {
        $order = [];

        foreach (is_array($input) ? $input : [] as $group) {
            $key = is_array($group) ? ($group['key'] ?? null) : null;
            $methods = is_array($group) ? ($group['methods'] ?? null) : null;

            if (! is_string($key) || ! is_array($methods) || array_key_exists($key, $order)) {
                throw $this->refused();
            }

            $order[$key] = array_values(array_map('intval', array_filter($methods, 'is_numeric')));
        }

        return $order;
    }

    /**
     * @param  list<int|string>  $a
     * @param  list<int|string>  $b
     */
    private function same(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }

    private function refused(): ValidationException
    {
        return ValidationException::withMessages(['groups' => [__('webx-payments::panel.errors.order')]]);
    }

    /** @return array<string, string>|null */
    private function words(mixed $words): ?array
    {
        return Words::clean(is_array($words) ? $words : []) ?: null;
    }

    /** A name of the set (§2.5), and only one that differs from the config's: an empty field follows it. */
    private function pictogram(string $key, mixed $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        if (! is_string($name) || ! in_array($name, MethodGroups::PICTOGRAMS, true)) {
            throw ValidationException::withMessages(['pictogram' => [__('webx-payments::panel.errors.unknown_pictogram', ['name' => is_scalar($name) ? (string) $name : ''])]]);
        }

        return $name === ($this->groups->configured()[$key]['pictogram'] ?? null) ? null : $name;
    }
}
