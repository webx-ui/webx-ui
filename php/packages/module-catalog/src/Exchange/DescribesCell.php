<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

/**
 * A column that can say how its cell reads, for `catalog://exchange`: an agent builds a file
 * right more cheaply than it reads the errors of a wrong one. A column without it is shown as
 * text taken as it is.
 */
interface DescribesCell
{
    /** One or two sentences in English: what the cell holds and how the import reads it. */
    public function cellFormat(): string;
}
