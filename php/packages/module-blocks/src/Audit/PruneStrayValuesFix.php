<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\FixPreview;
use WebxUi\Audit\Contracts\AuditFix;
use WebxUi\Blocks\StrayValues;

/**
 * `blocks.prune-stray`: take the stray values out of the one entity a finding names — what
 * `webx:blocks:prune` does for every entity, measured by the same {@see StrayValues}. A node of a
 * type nobody knows is never touched.
 */
final readonly class PruneStrayValuesFix implements AuditFix
{
    public const ID = 'blocks.prune-stray';

    public function __construct(private StrayValues $strays) {}

    public function textNamespace(): string
    {
        return 'webx-blocks';
    }

    public function id(): string
    {
        return self::ID;
    }

    public function fixes(): array
    {
        return [StrayValuesCheck::ID];
    }

    public function available(Finding $finding): bool
    {
        $entity = $this->entity($finding);

        return $entity !== null && $this->strays->find($entity) !== [];
    }

    public function preview(Finding $finding): FixPreview
    {
        $entity = $this->entity($finding);
        $changes = [];

        foreach ($entity === null ? [] : $this->strays->find($entity) as $where => $dropped) {
            foreach ($dropped as $one) {
                $changes[] = [
                    'label' => $one['type'].' · '.($one['key'] ?? '—').' ('.$where.')',
                    'before' => implode(', ', $one['fields']),
                    'after' => null,
                ];
            }
        }

        return new FixPreview($changes);
    }

    public function apply(Finding $finding): void
    {
        $entity = $this->entity($finding);

        if ($entity !== null) {
            $this->strays->prune($entity);
        }
    }

    /** The entity back from the finding's key: `page:12`, `region:3`. */
    private function entity(Finding $finding): ?Model
    {
        [$type, $id] = array_pad(explode(':', $finding->key, 2), 2, '');
        $class = Relation::getMorphedModel($type) ?? $type;

        if ($id === '' || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        /** @var Model $model */
        $model = new $class;
        $query = in_array(SoftDeletes::class, class_uses_recursive($model), true)
            ? $model->newQueryWithoutScopes()
            : $model->newQuery();

        return $query->find($id);
    }
}
