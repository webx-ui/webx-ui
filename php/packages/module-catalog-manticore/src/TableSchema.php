<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

use LogicException;
use WebxUi\Catalog\Facets\IndexField;

/**
 * The table of one language, from every contributor's fields (decisions 5–7 of the Manticore
 * spec).
 *
 * A text the contributors write per language — `name_en`, `name_ru` — is one column here: the
 * table's own language, or the site's main language where there is no translation, as the
 * storefront shows it. Every other language of the site goes into one column, `other_languages`,
 * weighed lower: a product is found by a word in any language of the site from a page in any
 * language, and its own language ranks first. Everything else is an attribute, one per field.
 *
 * Manticore has no nulls in plain attributes and no strings in multi-valued ones, so a number
 * that may be missing carries a flag beside it (`price__set`), and several strings, floats or
 * flags are a JSON array. A field the facets read by key — `pn.12` — is a JSON map.
 *
 * The morphology is that of every language of the site, the table's own first: among languages of
 * one alphabet the first processor wins, and the German table must read German.
 *
 * The codes of a product — every field marked {@see IndexField::$code}: the article number, the
 * barcode, the external id — are searched twice over (decisions 19–22): as written, split where
 * they split (`codes`), and as their letters and digits alone (`codes_flat`), which the query
 * reads by any part of it, `*1234*`. `code_keys` holds the same flat codes whole, for the one
 * whose code the search is. The infix that the part of a code needs is the whole table's — Manticore
 * has no infix per field in a real-time table, and `CALL QSUGGEST` needs it too — but only a code
 * is ever asked for by a part: a name is asked for by its words and their beginnings.
 */
final class TableSchema
{
    public const OTHER = 'other_languages';

    /** Text columns that are also attributes, so that a list can be sorted by them. */
    public const SORTABLE = ['name'];

    /** The codes as written, split where they split: `AT-1234/56` is at, 1234, 56. */
    public const CODES = 'codes';

    /** The codes as letters and digits alone, asked for by any part: `at123456`. */
    public const CODES_FLAT = 'codes_flat';

    /** The flat codes whole, a JSON list: whose code the search is. */
    public const CODE_KEYS = 'code_keys';

    private const NAME = '/^[a-z_][a-z0-9_]*$/';

    /** @var array<string, array{create: string, type: string, props: string}> */
    private array $columns = [];

    /** @var array<string, IndexField> attribute column → its field */
    private array $attributes = [];

    /** @var list<string> the bases of the texts written per language */
    private array $localized = [];

    /** @var list<string> */
    private array $texts = [];

    /** @var list<string> the fields that are codes of the product */
    private array $codes = [];

    /**
     * @param  list<IndexField>  $fields  as the documents' schema lists them: one per language
     * @param  list<string>  $locales  the site's languages
     * @param  array<string, string>  $morphology  language → processors
     */
    public function __construct(
        array $fields,
        public readonly string $locale,
        private readonly array $locales,
        private readonly string $main,
        private readonly array $morphology = [],
        private readonly int $minPrefixLen = 3,
        private readonly int $minInfixLen = 3,
    ) {
        $named = [];

        foreach ($fields as $field) {
            $named[$field->name] = $field;
        }

        foreach ($fields as $field) {
            if ($field->name === 'id') {
                continue;
            }

            if ($field->type === IndexField::TEXT) {
                $base = $this->baseOf($field->name, $named);

                if ($base !== null) {
                    if (! in_array($base, $this->localized, true)) {
                        $this->localized[] = $base;
                        $this->column($base, in_array($base, self::SORTABLE, true) ? 'text indexed attribute' : 'text indexed');
                    }

                    continue;
                }

                $this->texts[] = $field->name;
                $this->column($field->name, 'text indexed');

                continue;
            }

            if ($field->code && ! $field->multi) {
                $this->codes[] = $field->name;
            }

            $this->attributes[$field->name] = $field;
            $this->column($field->name, $this->attributeType($field));

            if ($this->flagged($field)) {
                $this->column($field->name.'__set', 'bool');
            }
        }

        if ($this->localized !== [] && count($this->locales) > 1) {
            $this->column(self::OTHER, 'text indexed');
        }

        if ($this->codes !== []) {
            $this->column(self::CODES, 'text indexed');
            $this->column(self::CODES_FLAT, 'text indexed');
            $this->column(self::CODE_KEYS, 'json');
        }
    }

    /**
     * A code as its letters and digits alone, in lower case: `AT-1234/56` is `at123456` — and so
     * is the search typed for it, whichever way it was typed.
     */
    public static function flat(string $code): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($code));
    }

    /** @return array<string, array{create: string, type: string, props: string}> */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @return array<string, string> setting → value */
    public function settings(): array
    {
        $processors = [];

        foreach ([$this->locale, ...$this->locales] as $locale) {
            foreach (explode(',', $this->morphology[$locale] ?? '') as $processor) {
                $processor = trim($processor);

                if ($processor !== '' && ! in_array($processor, $processors, true)) {
                    $processors[] = $processor;
                }
            }
        }

        $settings = ['min_prefix_len' => (string) $this->minPrefixLen];

        if ($this->minInfixLen > 0) {
            $settings['min_infix_len'] = (string) $this->minInfixLen;
        }

        if ($processors !== []) {
            $settings['morphology'] = implode(', ', $processors);
        }

        return $settings;
    }

    public function create(string $table): string
    {
        $columns = [];

        foreach ($this->columns as $name => $column) {
            $columns[] = $name.' '.$column['create'];
        }

        $settings = [];

        foreach ($this->settings() as $setting => $value) {
            $settings[] = $setting.'='.Manticore::quote($value);
        }

        return 'CREATE TABLE '.$table.' ('.implode(', ', $columns).') '.implode(' ', $settings);
    }

    /**
     * Why the live table is not this one, or null when it is: a column missing, extra or of
     * another type, or other settings.
     *
     * @param  array<string, array{type: string, props: string}>  $live  column → what `DESCRIBE` says
     * @param  array<string, string>  $settings  what `SHOW TABLE … SETTINGS` says
     */
    public function differs(array $live, array $settings): ?string
    {
        unset($live['id']);

        foreach ($this->columns as $name => $column) {
            $found = $live[$name] ?? null;

            if ($found === null) {
                return "the column [{$name}] is missing";
            }

            if ($found['type'] !== $column['type'] || $found['props'] !== $column['props']) {
                return "the column [{$name}] is {$found['type']} {$found['props']}, not {$column['type']} {$column['props']}";
            }
        }

        foreach (array_keys($live) as $name) {
            if (! isset($this->columns[$name])) {
                return "the column [{$name}] is no longer written";
            }
        }

        foreach ($this->settings() as $setting => $value) {
            $have = str_replace(' ', '', $settings[$setting] ?? '');

            if ($have !== str_replace(' ', '', $value)) {
                return "{$setting} is [".($settings[$setting] ?? '')."], not [{$value}]";
            }
        }

        if (! isset($this->settings()['morphology']) && ($settings['morphology'] ?? '') !== '') {
            return "morphology is [{$settings['morphology']}], not none";
        }

        return null;
    }

    /**
     * A product's document as a row of this table.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function row(array $document): array
    {
        $row = [];
        $other = [];

        foreach ($this->localized as $base) {
            $own = self::text($document[$base.'_'.$this->locale] ?? null);
            $fallback = $own === '';
            $row[$base] = $fallback ? self::text($document[$base.'_'.$this->main] ?? null) : $own;

            foreach ($this->locales as $locale) {
                if ($locale === $this->locale || ($fallback && $locale === $this->main)) {
                    continue;
                }

                $text = self::text($document[$base.'_'.$locale] ?? null);

                if ($text !== '' && $text !== $row[$base]) {
                    $other[] = $text;
                }
            }
        }

        if (isset($this->columns[self::OTHER])) {
            $row[self::OTHER] = implode("\n", array_values(array_unique($other)));
        }

        foreach ($this->texts as $name) {
            $row[$name] = self::text($document[$name] ?? null);
        }

        if ($this->codes !== []) {
            $written = [];
            $flat = [];

            foreach ($this->codes as $name) {
                $code = self::text($document[$name] ?? null);

                if ($code !== '') {
                    $written[] = $code;
                    $flat[] = self::flat($code);
                }
            }

            $flat = array_values(array_unique(array_filter($flat, static fn (string $one): bool => $one !== '')));
            $row[self::CODES] = implode(' ', $written);
            $row[self::CODES_FLAT] = implode(' ', $flat);
            $row[self::CODE_KEYS] = $flat;
        }

        foreach ($this->attributes as $name => $field) {
            $value = $document[$name] ?? null;
            $row[$name] = $this->attributeValue($field, $value);

            if ($this->flagged($field)) {
                $row[$name.'__set'] = is_numeric($value);
            }
        }

        return $row;
    }

    /** The attribute a field of a facet lies in, or null when the table has none. */
    public function attribute(string $name): ?IndexField
    {
        return $this->attributes[$name] ?? null;
    }

    /** Whether a missing value of this attribute has a flag column beside it. */
    public function flagged(IndexField $field): bool
    {
        return ! $field->multi && $field->type === IndexField::FLOAT;
    }

    /** Whether the attribute keeps its values as a JSON array or map. */
    public function json(IndexField $field): bool
    {
        return $this->attributeType($field) === 'json';
    }

    /** The text column a localised field of this language is sorted by, or null. */
    public function sortable(string $field): ?string
    {
        foreach ($this->localized as $base) {
            if ($field === $base.'_'.$this->locale && in_array($base, self::SORTABLE, true)) {
                return $base;
            }
        }

        return null;
    }

    /** @return list<string> the text columns of the table's own language and the rest */
    public function textColumns(): array
    {
        return [...$this->localized, ...$this->texts, ...($this->codes === [] ? [] : [self::CODES, self::CODES_FLAT])];
    }

    /** Whether the table has codes to search by a part and to put first. */
    public function hasCodes(): bool
    {
        return $this->codes !== [];
    }

    /** The shortest word whose beginning is searched. */
    public function prefixLength(): int
    {
        return $this->minPrefixLen;
    }

    /** The shortest part of a code that is searched; 0 is none. */
    public function infixLength(): int
    {
        return $this->minInfixLen;
    }

    public function hasOther(): bool
    {
        return isset($this->columns[self::OTHER]);
    }

    /**
     * @param  array<string, IndexField>  $named
     */
    private function baseOf(string $name, array $named): ?string
    {
        foreach ($this->locales as $locale) {
            $suffix = '_'.$locale;

            if (! str_ends_with($name, $suffix)) {
                continue;
            }

            $base = substr($name, 0, -strlen($suffix));

            foreach ($this->locales as $every) {
                if (($named[$base.'_'.$every] ?? null)?->type !== IndexField::TEXT) {
                    return null;
                }
            }

            return $base;
        }

        return null;
    }

    private function column(string $name, string $create): void
    {
        if (preg_match(self::NAME, $name) !== 1) {
            throw new LogicException("An index field is `[a-z_][a-z0-9_]*` in Manticore; [{$name}] is not.");
        }

        if (isset($this->columns[$name])) {
            throw new LogicException("Two index fields make the Manticore column [{$name}].");
        }

        // What `DESCRIBE` answers for what was created.
        [$type, $props] = match ($create) {
            'text indexed attribute' => ['string', 'indexed attribute'],
            'text indexed' => ['text', 'indexed'],
            'multi64' => ['mva64', ''],
            default => [$create, ''],
        };

        $this->columns[$name] = ['create' => $create, 'type' => $type, 'props' => $props];
    }

    private function attributeType(IndexField $field): string
    {
        if ($field->type === IndexField::JSON) {
            return 'json';
        }

        if ($field->multi) {
            return $field->type === IndexField::INT ? 'multi64' : 'json';
        }

        return match ($field->type) {
            IndexField::INT => 'bigint',
            IndexField::FLOAT => 'float',
            IndexField::BOOL => 'bool',
            IndexField::TIMESTAMP => 'timestamp',
            default => 'string',
        };
    }

    private function attributeValue(IndexField $field, mixed $value): mixed
    {
        if ($field->type === IndexField::JSON) {
            return is_array($value) && $value !== [] ? $value : (object) [];
        }

        if ($field->multi) {
            $values = is_array($value) ? array_values($value) : ($value === null ? [] : [$value]);

            return match ($field->type) {
                IndexField::INT => array_values(array_map('intval', array_filter($values, 'is_numeric'))),
                IndexField::FLOAT => array_values(array_map('floatval', array_filter($values, 'is_numeric'))),
                IndexField::BOOL => array_values(array_map('boolval', $values)),
                IndexField::TIMESTAMP => array_values(array_map('intval', array_filter($values, 'is_numeric'))),
                default => array_values(array_map(static fn (mixed $one): string => self::text($one), $values)),
            };
        }

        return match ($field->type) {
            IndexField::INT, IndexField::TIMESTAMP => is_numeric($value) ? (int) $value : 0,
            IndexField::FLOAT => is_numeric($value) ? (float) $value : 0.0,
            IndexField::BOOL => (bool) $value,
            default => self::text($value),
        };
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
