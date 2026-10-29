<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * An order cannot be linked to this visitor (spec §13.3): there is no such account — it was
 * deleted, or the site has no visitors module at all. Nothing was written.
 */
final class VisitorRefused extends Refusal
{
    public const string NOT_FOUND = 'visitor_not_found';

    public function __construct()
    {
        parent::__construct(self::NOT_FOUND, 'There is no such visitor.');
    }
}
