<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Database\DatabaseManager;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Relations\Relations as Rows;
use WebxUi\Admin\Relations\RelationTargets;

/**
 * Relations that point at a module which is not installed (§3.3 of the recipes spec).
 *
 * The rows are kept on purpose — a module put back finds its relations whole — and read as if
 * they were not there, so nothing on the site says anything about them. This is the one place
 * that does: how many, from what to what, so a module removed for good can be cleaned up after
 * by somebody who knows it was.
 */
final class Relations implements Check
{
    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly RelationTargets $targets,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        try {
            $db = $this->databases->connection();

            if (! $db->getSchemaBuilder()->hasTable(Rows::TABLE)) {
                return [];
            }

            $orphans = $db->table(Rows::TABLE)
                ->selectRaw('owner_type, target_type, count(*) as total')
                ->when($this->targets->keys() !== [], fn ($query) => $query->whereNotIn('target_type', $this->targets->keys()))
                ->groupBy('owner_type', 'target_type')
                ->orderBy('owner_type')
                ->orderBy('target_type')
                ->get();
        } catch (Throwable) {
            // No database to ask is the migrations check's line, not this one.
            return [];
        }

        $found = [];

        foreach ($orphans as $row) {
            $found[] = Diagnosis::warn(
                'relations',
                "{$row->total} of {$row->owner_type} point at {$row->target_type}, which no installed module answers for — "
                .'they are kept for the module to come back; if it is gone for good, delete them: '
                .'DELETE FROM '.Rows::TABLE." WHERE target_type = '{$row->target_type}'",
            );
        }

        return $found;
    }
}
