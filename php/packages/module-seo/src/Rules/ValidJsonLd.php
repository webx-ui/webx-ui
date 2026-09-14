<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The JSON-LD field: an object, a list of objects, or the text of either.
 *
 * The panel edits it in a code editor and sends what it parsed, but a rule written by an agent
 * or imported from somewhere sends a string. Both are the same thing said twice, so both are
 * accepted and stored decoded.
 */
final class ValidJsonLd implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $value === []) {
            return;
        }

        $decoded = self::decode($value);

        if ($decoded === null) {
            $fail('webx-seo::errors.bad-json-ld')->translate();
        }
    }

    /**
     * What to store: a list of blocks, or null when it is not JSON-LD at all.
     *
     * @return list<array<string, mixed>>|null
     */
    public static function decode(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value) || $value === []) {
            return null;
        }

        if (! array_is_list($value)) {
            /** @var array<string, mixed> $value */
            return [$value];
        }

        $blocks = [];

        foreach ($value as $block) {
            if (! is_array($block) || $block === [] || array_is_list($block)) {
                return null;
            }

            /** @var array<string, mixed> $block */
            $blocks[] = $block;
        }

        return $blocks;
    }
}
