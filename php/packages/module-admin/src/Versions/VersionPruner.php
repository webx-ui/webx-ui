<?php

declare(strict_types=1);

namespace WebxUi\Admin\Versions;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * What keeps the versions table from growing without end.
 *
 * Runs after every write, so the limit cannot be exceeded by an entity that is being edited;
 * the command runs it over everything for the case where the limit was lowered afterwards.
 * Pinned versions are outside the count altogether: a version somebody marked as worth keeping
 * neither goes nor pushes another one out.
 */
final class VersionPruner
{
    public function __construct(private readonly Config $config) {}

    /** How many publications an entity keeps. */
    public function limit(): int
    {
        return max(1, (int) $this->config->get('webx-admin.versions.limit', 30));
    }

    /** How many autosaves an entity keeps. */
    public function autosaves(): int
    {
        return max(0, (int) $this->config->get('webx-admin.versions.autosaves', 5));
    }

    /**
     * Trim one entity's history and ring.
     *
     * @return int How many rows went.
     */
    public function prune(string $type, mixed $id): int
    {
        return $this->trim($type, $id, EntityVersion::KIND_PUBLISHED, $this->limit())
            + $this->trim($type, $id, EntityVersion::KIND_AUTOSAVE, $this->autosaves());
    }

    /**
     * Trim every entity that has versions.
     *
     * @return int How many rows went.
     */
    public function pruneAll(): int
    {
        $removed = 0;

        $entities = EntityVersion::query()
            ->toBase()
            ->select(['versionable_type', 'versionable_id'])
            ->distinct()
            ->orderBy('versionable_type')
            ->orderBy('versionable_id')
            ->get();

        foreach ($entities as $entity) {
            $removed += $this->prune((string) $entity->versionable_type, $entity->versionable_id);
        }

        return $removed;
    }

    private function trim(string $type, mixed $id, string $kind, int $keep): int
    {
        $ids = EntityVersion::query()
            ->where('versionable_type', $type)
            ->where('versionable_id', $id)
            ->where('kind', $kind)
            ->where('is_pinned', false)
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        return $ids->isEmpty() ? 0 : EntityVersion::query()->whereKey($ids->all())->delete();
    }
}
