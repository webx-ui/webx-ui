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
 * The shape is the node's: `props.separator` — `-` (the default), `_`, or `-_` / `_-` (either kept,
 * {@see Slug::BOTH}), `props.lead` for a code that must start with a letter, and `props.maxlength`,
 * which cuts the code rather than refusing a longer text, since what is typed is not what is kept.
 * Whether the code is free is the model's to say: only it knows among what.
 *
 * A `localized` code is kept per language and is made a code in each language's own way — «Гальмівні»
 * is `halmivni` in Ukrainian, `galmivni` read the Russian way. A value reaches {@see store()} one
 * language at a time without saying which, so only what reads the same in every language — plain
 * Latin — is brought here; the rest is left as typed for the model, which knows the language of each
 * (`Slug::code()` per language on its `saving`). The panel brings each language as it is typed.
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
        $latin = Slug::isLatin($typed);

        if (! $latin && ($node['localized'] ?? false) === true) {
            return $typed;
        }

        // The language is asked for only when it matters: it is a read of the `locales` table.
        $locale = $latin ? 'en' : $this->locales->defaultCode();

        return Slug::code($typed, $locale, self::length($node), self::separator($node), self::lead($node));
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
        $separator = $node['props']['separator'] ?? '-';

        return in_array($separator, ['_', Slug::BOTH, Slug::BOTH_UNDERSCORED], true) ? $separator : '-';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public static function lead(array $node): ?string
    {
        $lead = $node['props']['lead'] ?? null;

        return is_string($lead) && $lead !== '' ? $lead : null;
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
