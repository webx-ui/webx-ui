<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Audit;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Blocks\StrayValues;

/**
 * Blocks holding values for fields their type does not define (`blocks.stray_values`).
 *
 * Nothing of it reaches a visitor; it reaches everybody else — the editor's data, what an agent
 * reads, the line a block is recognised by. One finding per entity, with a row per block: its
 * type, its key, the stray keys and whether they are in what the site shows or in the draft.
 * What is measured is {@see StrayValues}, the same as `webx:blocks:prune` and the fix.
 */
final class StrayValuesCheck extends ModuleCheck
{
    public const ID = 'blocks.stray_values';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::NOTICE;

    protected const NEEDS = ['database'];

    protected const NAMESPACE = 'webx-blocks';

    public function __construct(
        private readonly StrayValues $strays,
        private readonly AuditContentSources $sources,
    ) {}

    public function run(AuditContext $context): iterable
    {
        $records = null;

        foreach ($this->strays->entities() as $entity) {
            $found = $this->strays->find($entity);

            if ($found === []) {
                continue;
            }

            // Read once, and only when something is found: the name and the editor of every
            // record the audit's content sources know, by model.
            $records ??= $this->records();
            [$label, $edit] = $records[self::key($entity)] ?? [class_basename($entity).' #'.$entity->getKey(), null];

            $rows = [];
            $count = 0;

            foreach ($found as $where => $dropped) {
                foreach ($dropped as $one) {
                    $count++;
                    $rows[] = [
                        'block' => $one['type'].' · '.($one['key'] ?? '—'),
                        'fields' => implode(', ', $one['fields']),
                        // What the site shows, or only the draft.
                        'published' => $where === 'site',
                        'edit' => $edit,
                    ];
                }
            }

            yield $this->found(
                'stray-values',
                ['entity' => $label, 'count' => $count],
                key: self::key($entity),
                table: [
                    'columns' => [
                        Finding::column('block'),
                        Finding::column('fields'),
                        Finding::column('published', 'bool'),
                        Finding::column('edit', 'edit'),
                    ],
                    'rows' => $rows,
                ],
            );
        }
    }

    /** The entity as a finding names it, and as the fix finds it again: morph class and id. */
    public static function key(Model $entity): string
    {
        return $entity->getMorphClass().':'.$entity->getKey();
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    private function records(): array
    {
        $records = [];

        foreach ($this->sources->all() as $source) {
            foreach ($source->records() as $record) {
                if ($record->subject instanceof Model) {
                    $records[self::key($record->subject)] = [$record->label, $record->editUrl];
                }
            }
        }

        return $records;
    }
}
