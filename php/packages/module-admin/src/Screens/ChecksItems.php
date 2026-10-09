<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * A field type whose value is made of items, each with fields of its own — `wx-repeater`.
 *
 * Its `rules()` can only fail the field as a whole, so "Row 3: …" under the list was all the panel
 * could show, below the "Add" button and away from the row. A type that implements this names
 * each problem by its path inside the value instead, and {@see ScreenValues} reports it under the
 * field's name plus that path: `contacts.phones.2.number`. The panel marks that row, opens it and
 * shows the words under the field; an agent reads the row from the key.
 */
interface ChecksItems
{
    /**
     * What is wrong inside the value, keyed by path from it — `2.number`, `0.title.en` — and
     * empty when nothing is. What concerns the list as a whole is left to `rules()`.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, list<string>>
     */
    public function itemErrors(mixed $value, array $node): array;
}
