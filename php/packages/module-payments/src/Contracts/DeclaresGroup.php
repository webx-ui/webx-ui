<?php

declare(strict_types=1);

namespace WebxUi\Payments\Contracts;

/**
 * A type whose methods belong elsewhere than its kind suggests (groups spec §2.3): an invoice for
 * companies issued by an online gateway declares `transfer`. Optional: most types are placed by
 * `kind()` and `awaitsPayment()`, and a key the site does not have falls back to them.
 */
interface DeclaresGroup
{
    /** A key of `MethodGroups`: one of the site's groups, `config('webx-payments.groups')`. */
    public function defaultGroup(): string;
}
