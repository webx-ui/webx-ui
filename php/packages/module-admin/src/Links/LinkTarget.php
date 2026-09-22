<?php

declare(strict_types=1);

namespace WebxUi\Admin\Links;

/**
 * What a link points at, said out loud rather than guessed.
 *
 * "If there is no entity it must be a URL" costs a day less to write and a great deal more
 * later: by such a record you cannot tell a link nobody has finished choosing from one that
 * deliberately goes nowhere (§2, decision 4).
 */
enum LinkTarget: string
{
    /** A record of this site, by morph alias and key. Its address is asked for on every read. */
    case Entity = 'entity';

    /** A path on this site, or an absolute address somewhere else. */
    case Url = 'url';

    /** Nowhere: a group heading, a separator, a button whose script does the work. */
    case None = 'none';
}
