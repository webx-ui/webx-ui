<?php

declare(strict_types=1);

namespace WebxUi\Admin\Cache;

use Illuminate\Contracts\Config\Repository as Config;
use InvalidArgumentException;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\History\Journal;

/**
 * «Reset the site cache» — the button, the command and the agent's tool behind one door.
 *
 * A reset moves the versions of the groups asked for and nothing else: `cache:clear` would take
 * the sessions, the locks and the rate limits of the store with it. Every reset is a row of the
 * journal under the type `cache`, with who pressed and which groups — because a reset somebody
 * had to make by hand is a `bump` somebody forgot, and the journal is where that shows.
 */
final class CacheReset
{
    public const TYPE = 'cache';

    /** The journal's record for the whole cache: one subject, every reset under it. */
    public const SUBJECT = 0;

    public const ALL = '*';

    public function __construct(
        private readonly CacheGroups $groups,
        private readonly Journal $journal,
        private readonly Config $config,
    ) {}

    /**
     * Move these groups — all of them with none named — and write it down.
     *
     * @param  list<string>  $groups
     * @return list<string> the groups moved, in the order declared
     */
    public function reset(array $groups = []): array
    {
        $moved = $this->resolve($groups);

        $this->groups->bump(...$moved);

        $this->journal->recordFor(self::TYPE, self::SUBJECT, HistoryEntry::UPDATED, [[
            'field' => 'groups',
            'from' => null,
            'to' => $groups === [] ? self::ALL : implode(', ', $moved),
        ]]);

        return $moved;
    }

    /**
     * The groups a request names, checked and in the order declared; every group with none named.
     *
     * @param  list<string>  $groups
     * @return list<string>
     */
    public function resolve(array $groups): array
    {
        $known = $this->groups->ids();

        if ($groups === []) {
            return $known;
        }

        foreach ($groups as $group) {
            if (! $this->groups->has($group)) {
                throw new InvalidArgumentException(sprintf(
                    'There is no cache group [%s]; the groups are: %s.',
                    $group,
                    implode(', ', $known),
                ));
            }
        }

        return array_values(array_intersect($known, $groups));
    }

    /**
     * What the screen and the tool show: the store, and every group with its version and models.
     *
     * @return array{driver: string, store: string, enabled: bool, ttl: int, groups: list<array{id: string, title: string, version: int, models: list<string>}>}
     */
    public function report(): array
    {
        $versions = $this->groups->versions(...$this->groups->ids());
        $rows = [];

        foreach ($this->groups->all() as $id => $group) {
            $rows[] = [
                'id' => $id,
                'title' => $group->title(),
                'version' => $versions[$id],
                'models' => array_map(
                    static fn (string $model): string => substr($model, (int) strrpos('\\'.$model, '\\')),
                    $group->models(),
                ),
            ];
        }

        return [
            'driver' => $this->driver(),
            'store' => $this->store(),
            'enabled' => $this->groups->enabled(),
            'ttl' => $this->groups->ttl(),
            'groups' => $rows,
        ];
    }

    public function store(): string
    {
        $store = $this->config->get('cache.default');

        return is_string($store) ? $store : 'array';
    }

    public function driver(): string
    {
        $driver = $this->config->get('cache.stores.'.$this->store().'.driver');

        return is_string($driver) ? $driver : $this->store();
    }
}
