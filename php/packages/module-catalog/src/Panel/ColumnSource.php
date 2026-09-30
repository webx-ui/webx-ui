<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

/**
 * Columns of the panel's list that live in the database rather than in code — a property marked
 * «column in the list» (§7.2 of the properties spec). {@see ProductColumns} asks each source when
 * it is read, not when it boots: at boot there may be no database yet, and a column added in the
 * panel has to show without a restart.
 */
interface ColumnSource
{
    /**
     * @return list<ProductColumn> after the registered columns, in this order
     */
    public function columns(): array;
}
