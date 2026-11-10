<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use WebxUi\Admin\Screens\FieldType;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Slug;

/**
 * A machine name somebody types — the code of a stock status, of a label: `wx-code`.
 *
 * Brought to its shape rather than refused: «тормозные колодки» is kept as `tormoznye-kolodki`.
 * The panel does the same as it is typed (`WxCodeField`), this is the same for a save that did
 * not come through it, and the model a code lives on does it once more before its own check, so
 * an agent and an import that skip the screen end up with the same code (`Slug::code()`).
 *
 * The shape is the node's: `props.separator` — `-` (the default) or `_` — and `props.maxlength`,
 * which cuts the code rather than refusing a longer text, since what is typed is not what is kept.
 * Whether the code is free is the model's to say: only it knows among what.
 */
final class CodeType implements FieldType
{
    /** The longest text taken in, before it is cut to the code's own length. */
    private const INPUT = 2000;

    /** A code with no `maxlength` of its own: what a `string` column of an index holds. */
    private const LENGTH = 190;

    public function __construct(private readonly Locales $locales) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'string', 'max:'.self::INPUT];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if ($value === null) {
            return null;
        }

        $typed = (string) $value;
        // The language is asked for only when it matters: it is a read of the `locales` table.
        $locale = Slug::isLatin($typed) ? 'en' : $this->locales->defaultCode();

        return Slug::code($typed, $locale, self::length($node), self::separator($node));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public static function separator(array $node): string
    {
        return ($node['props']['separator'] ?? '-') === '_' ? '_' : '-';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public static function length(array $node): int
    {
        $max = $node['props']['maxlength'] ?? null;

        return is_numeric($max) && (int) $max > 0 ? (int) $max : self::LENGTH;
    }
}
