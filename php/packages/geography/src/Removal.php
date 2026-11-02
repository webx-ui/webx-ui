<?php

declare(strict_types=1);

namespace WebxUi\Geography;

/** What removing a locality did (decision 42). */
enum Removal: string
{
    /** Nothing held it: the row is gone. */
    case Deleted = 'deleted';

    /** Points, bindings, selections or orders hold it: it stays, out of the suggestions. */
    case Deactivated = 'deactivated';
}
