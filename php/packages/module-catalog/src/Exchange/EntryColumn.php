<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

/**
 * A column that fills one entry of a field whose value is an object: one property of
 * `properties.values`, which the form takes whole as `{ "<property id>": value }` (§7.2 of the
 * exchange spec). The columns of one field make its value between them, so a row with three
 * property columns is one field of the form, not three that overwrite each other.
 *
 * A localized one fills `{ "<entry>": { "<locale>": text } }`.
 */
interface EntryColumn extends ExchangeColumn
{
    /** The key inside the field's object. */
    public function entry(): string;
}
