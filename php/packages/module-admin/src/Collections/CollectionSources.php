<?php

declare(strict_types=1);

namespace WebxUi\Admin\Collections;

use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Links\LinkSources;

/**
 * The records this site's modules offer to show as blocks, by the key a schema names them with.
 *
 * A singleton by the same pattern as {@see LinkSources}: modules register on
 * boot and everything reads on use, so the order providers boot in never matters.
 */
final class CollectionSources
{
    /** @var array<string, CollectionSource> */
    private array $sources = [];

    public function register(CollectionSource $source): void
    {
        $this->sources[$source->key()] = $source;
    }

    public function find(string $key): ?CollectionSource
    {
        return $this->sources[$key] ?? null;
    }

    /**
     * @return list<CollectionSource>
     */
    public function all(): array
    {
        $sources = array_values($this->sources);

        usort($sources, static fn (CollectionSource $a, CollectionSource $b): int => strcmp($a->key(), $b->key()));

        return $sources;
    }

    /**
     * The sources this administrator may place, by the same rule the link picker draws its
     * sections with: a super administrator carries no permissions and passes everything.
     *
     * @return list<CollectionSource>
     */
    public function allowed(?object $user): array
    {
        return array_values(array_filter(
            $this->all(),
            static function (CollectionSource $source) use ($user): bool {
                $permission = $source->permission();

                if ($permission === null) {
                    return true;
                }

                return $user instanceof HasPermissions && $user->hasPermission($permission);
            },
        ));
    }

    public function forget(): void
    {
        $this->sources = [];
    }
}
