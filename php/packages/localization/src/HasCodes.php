<?php

declare(strict_types=1);

namespace WebxUi\Localization;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * A machine name somebody types — a key of a menu, a code of a group — is brought to its shape,
 * never refused for it (#125): «тормозные колодки» is kept as `tormoznye-kolodki`.
 *
 * On the model, because that is the one thing the panel, an agent, an import and a plain `save()`
 * all pass through. A request that checks the code before it reaches the model brings it first
 * with {@see codeFor()}, so the check sees what will be kept and refuses only what cannot be
 * mended: nothing left of it, or a code already taken. `''` is what is left of `!!!` — the model
 * falls back to a code of its own, or its check refuses it.
 *
 * The model says which columns are codes and their shape, the field's own pattern kept:
 *
 * ```php
 * protected static function codes(): array
 * {
 *     return ['key' => ['max' => 64, 'separator' => Slug::BOTH]];
 * }
 * ```
 *
 * `separator` — `-` (the default), `_`, or {@see Slug::BOTH}; `lead` — what a code that must
 * start with a letter is put after when it starts with a digit. `CodeCoverageTest` holds every
 * model with a code to this trait or to a reason.
 *
 * @mixin Model
 */
trait HasCodes
{
    /**
     * The columns that are codes, and the shape of each.
     *
     * @return array<string, array{max: int, separator?: string, lead?: string}>
     */
    abstract protected static function codes(): array;

    public static function bootHasCodes(): void
    {
        static::saving(static function (Model $model): void {
            foreach (array_keys(static::codes()) as $column) {
                $value = $model->getAttribute($column);

                if ($model->isDirty($column) && is_string($value)) {
                    $model->setAttribute($column, static::codeFor($column, $value));
                }
            }
        });
    }

    /**
     * What `$value` is kept as in `$column`: the code a request checks before it saves. Anything
     * that is not a string, or a column that is not a code, comes back as it was.
     */
    public static function codeFor(string $column, mixed $value): mixed
    {
        $shape = static::codes()[$column] ?? null;

        if ($shape === null || ! is_string($value)) {
            return $value;
        }

        // The language is asked for only when it matters: it is a read of the `locales` table.
        $locale = Slug::isLatin($value) ? 'en' : Container::getInstance()->make(Locales::class)->defaultCode();

        return Slug::code($value, $locale, $shape['max'], $shape['separator'] ?? '-', $shape['lead'] ?? null);
    }
}
