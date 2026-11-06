<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Illuminate\Contracts\Config\Repository;
use WebxUi\Cache\CacheGroups;
use WebxUi\Payments\Contracts\DeclaresGroup;
use WebxUi\Payments\Contracts\MethodType;
use WebxUi\Payments\Models\PaymentGroup;

/**
 * The groups of the site (groups spec §2): which there are is `config('webx-payments.groups')`,
 * how they read and stand is the rows of `payment_groups` over it.
 *
 * Only the rows are cached. The merge happens on reading, so a changed config needs no reset of the
 * cache, and a row whose key left the config is simply not walked over: the key coming back brings
 * the manager's edits back. Which group a method is in needs the config alone — no query, no cache.
 */
final readonly class MethodGroups
{
    public const string ON_RECEIPT = 'on_receipt';

    public const string ONLINE = 'online';

    public const string TRANSFER = 'transfer';

    /** The package's set: `resources/views/icons/<name>.blade.php`, printed by `<x-webx-payments::pictogram>`. */
    public const array PICTOGRAMS = ['card', 'cash', 'document', 'bank', 'wallet', 'truck', 'store', 'qr'];

    public function __construct(
        private CacheGroups $cache,
        private Repository $config,
    ) {}

    /**
     * The groups the config lists, by key, in its order: a title that may be a dictionary's key and
     * the name of a pictogram, as written.
     *
     * @return array<string, array{title: string, pictogram: string|null}>
     */
    public function configured(): array
    {
        $groups = $this->config->get('webx-payments.groups');
        $configured = [];

        foreach (is_array($groups) ? $groups : [] as $key => $group) {
            if (! is_string($key)) {
                continue;
            }

            $title = is_array($group) ? ($group['title'] ?? null) : null;
            $pictogram = is_array($group) ? ($group['pictogram'] ?? null) : null;

            $configured[$key] = [
                'title' => is_string($title) ? $title : $key,
                'pictogram' => is_string($pictogram) ? $pictogram : null,
            ];
        }

        return $configured;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->configured());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->configured());
    }

    /**
     * The groups of the site with the manager's edits, in the order the buyer sees them: by the
     * position a manager gave, then the rest in the order of the config.
     *
     * @return list<MethodGroup>
     */
    public function all(): array
    {
        $rows = $this->rows();
        $groups = [];

        foreach ($this->configured() as $key => $group) {
            $row = $rows[$key] ?? null;
            $pictogram = $row['pictogram'] ?? $group['pictogram'];

            $groups[] = new MethodGroup(
                key: $key,
                configTitle: $group['title'],
                titles: $row['titles'] ?? [],
                descriptions: $row['descriptions'] ?? [],
                pictogram: in_array($pictogram, self::PICTOGRAMS, true) ? $pictogram : null,
                position: $row['position'] ?? null,
            );
        }

        // A stable sort: groups without a position keep the order of the config among themselves.
        usort($groups, static fn (MethodGroup $a, MethodGroup $b): int => [$a->position === null, $a->position] <=> [$b->position === null, $b->position]);

        return $groups;
    }

    /**
     * The group a method stands in: where the manager moved it if the site has that group, else the
     * group of its type. The row keeps the move either way: the key coming back brings it back.
     * Null — the site has no groups at all.
     */
    public function of(Method $method, MethodType $type): ?string
    {
        return $method->groupKey !== null && $this->has($method->groupKey) ? $method->groupKey : $this->ofType($type);
    }

    /**
     * The group of a method the panel lists, whose type may not be installed any more: where it was
     * moved, else the first group. Such a method is not offered, but a manager still has to see it.
     */
    public function current(Method $method, ?MethodType $type): ?string
    {
        if ($type !== null) {
            return $this->of($method, $type);
        }

        return $method->groupKey !== null && $this->has($method->groupKey) ? $method->groupKey : ($this->keys()[0] ?? null);
    }

    /** What a method's row keeps for a group (groups spec §7): the group of its type is no move at all. */
    public function moved(string $key, ?MethodType $type): ?string
    {
        return $type !== null && $key === $this->ofType($type) ? null : $key;
    }

    /** The group of a type on this site; its own gone from the config — the first there is. */
    public function ofType(MethodType $type): ?string
    {
        $wanted = $this->wanted($type);

        return $this->has($wanted) ? $wanted : ($this->keys()[0] ?? null);
    }

    /** The group a type asks for, whether or not the site has it. */
    public function wanted(MethodType $type): string
    {
        if ($type instanceof DeclaresGroup && $this->has($type->defaultGroup())) {
            return $type->defaultGroup();
        }

        return match (true) {
            $type->kind() === Kind::Online => self::ONLINE,
            $type->awaitsPayment() => self::TRANSFER,
            default => self::ON_RECEIPT,
        };
    }

    /**
     * The manager's edits by key. Every language of a group is in its row: one entry for the site.
     *
     * @return array<string, array{titles: array<string, string>, descriptions: array<string, string>, pictogram: string|null, position: int|null}>
     */
    private function rows(): array
    {
        /** @var array<string, array{titles: array<string, string>, descriptions: array<string, string>, pictogram: string|null, position: int|null}> */
        return $this->cache->remember('payments.groups', [Methods::GROUP], static fn (): array => PaymentGroup::query()
            ->get()
            ->mapWithKeys(static fn (PaymentGroup $row): array => [$row->key => [
                'titles' => Words::clean($row->title),
                'descriptions' => Words::clean($row->description),
                'pictogram' => $row->pictogram,
                'position' => $row->position,
            ]])
            ->all(), localized: false);
    }
}
