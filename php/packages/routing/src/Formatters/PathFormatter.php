<?php

declare(strict_types=1);

namespace WebxUi\Routing\Formatters;

use Illuminate\Database\Eloquent\Model;

/**
 * How a kind of entity spells its address.
 *
 * A formatter is a pure function of the entity, and that is a requirement rather than a taste:
 *
 * 1. the observer and `webx:routes:rebuild` have to compute the same thing, or a rebuild would
 *    quietly move half the site;
 * 2. the form shows a live preview of the address before anything is saved;
 * 3. changing the address scheme of a type is then one command, with the old addresses left
 *    behind as aliases.
 */
interface PathFormatter
{
    /** Without a leading slash and without the language prefix; '' means the site root. */
    public function format(Model $entity, string $locale): string;
}
