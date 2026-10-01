<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

/**
 * A field of the search document (§4.2 of the architecture): how a feature lies in an index that
 * is not the database. `SqlEngine` never reads these — the database is its index — but an engine
 * that keeps one builds its schema from every contributor's fields and compares it with the live
 * one.
 */
final class IndexField
{
    public const INT = 'int';

    public const FLOAT = 'float';

    public const BOOL = 'bool';

    public const STRING = 'string';

    /** Full text: searched, not filtered. */
    public const TEXT = 'text';

    public const TIMESTAMP = 'timestamp';

    /**
     * A map that grows without changing the schema: the numbers of properties by id. A facet
     * reads one key of it as `{field}.{key}` — `pn.12`.
     */
    public const JSON = 'json';

    /**
     * @param  bool  $multi  Several values per product: the categories with their ancestors.
     * @param  bool  $localized  One field per language, `name_en`, `name_ru`.
     * @param  bool  $code  A code of the product — the article number, the barcode: an engine with
     *                      an index searches it as written and as its letters and digits alone, by
     *                      any part of it, and puts the product whose code is the search first.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $multi = false,
        public readonly bool $localized = false,
        public readonly bool $code = false,
    ) {}
}
