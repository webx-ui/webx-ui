<?php

declare(strict_types=1);

namespace WebxUi\Media\Usage;

/**
 * How a module says one of its rows that uses a library file: what it is, what it is called,
 * and where the panel edits it — «Страница: How We Can Help», linked.
 *
 * Optional. Without one, the library says a row from `webx-media.usage.places` and, failing
 * that, by the row's own title and its table. A module whose rows need more than a column and
 * a pattern — a label put together from two tables, a link that depends on a type — tags one
 * with {@see MediaUsage::DESCRIBERS}.
 */
interface PlaceDescriber
{
    /**
     * `null` when the table is not this describer's. `kind` is a word of `webx-media::places`
     * or one already translated; `edit_url` is relative to the panel (`/pages/12`).
     *
     * @return array{kind?: string|null, label?: string|null, edit_url?: string|null}|null
     */
    public function describe(string $table, string $column, int|string|null $id): ?array;
}
