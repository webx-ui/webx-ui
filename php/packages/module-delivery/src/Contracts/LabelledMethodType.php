<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Contracts;

/**
 * A method type that names itself for the panel: «Carrier A branch», and one line of what it is.
 *
 * Optional on top of {@see MethodType}, as every addition to the contract is, so that an older
 * satellite keeps working. A type without it is still named in words, never by its key: the
 * panel builds the name from the kind after the dot (`branch`, `locker`, `courier`) or from what
 * the type asks the buyer, with the carrier's title in it.
 */
interface LabelledMethodType extends MethodType
{
    /** What the panel calls the type in this language. */
    public function title(string $locale): string;

    /** One line under the title in «What kind of method?»; null — the panel's own line. */
    public function description(string $locale): ?string;
}
