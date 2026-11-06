<?php

declare(strict_types=1);

namespace WebxUi\Templates;

use JsonSerializable;

/**
 * A value that goes into XML whole, as `<![CDATA[…]]>`: HTML for a marketplace, which raw output
 * would give too, and raw output is refused. The `xml` strategy of {@see Escapes} writes it; every
 * other way of escaping reads it as its text. The browser gets it as `{ "$cdata": "…" }`.
 *
 * Empty is null rather than an empty wrapper, so `{{#description_cdata}}` works as for any value.
 *
 * Inside its own section Mustache looks names up on this object and calls a method of that name
 * with no arguments, so a template can reach whatever is here: there is no constructor to call
 * again, and `of()` has to survive being called with nothing.
 */
final class Cdata implements JsonSerializable
{
    private string $text = '';

    public static function of(?string $text = null): ?self
    {
        if ($text === null || $text === '') {
            return null;
        }

        $cdata = new self;
        $cdata->text = $text;

        return $cdata;
    }

    public function toText(): string
    {
        return $this->text;
    }

    /**
     * @return array{'$cdata': string}
     */
    public function jsonSerialize(): array
    {
        return ['$cdata' => $this->text];
    }
}
