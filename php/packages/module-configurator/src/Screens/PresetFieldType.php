<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Screens;

use WebxUi\Admin\Screens\FieldType;
use WebxUi\Configurator\Engine\Engine;

/**
 * `wx-configurator-preset`: where a block's configurator starts — `{ step: value, field: number }`
 * over the schema's `initial` (§12.1, decision 10). Chosen in the panel from the steps and the
 * values of the configurator picked beside it, never typed as JSON.
 *
 * Kept as it was chosen: the schema may change after the block is saved, and what it no longer has
 * is dropped by the site when it prints the block, not here — a step renamed back next week finds
 * its preset again. Empty is no preset at all.
 */
final class PresetFieldType implements FieldType
{
    /** How many keys one preset may hold: more than any configurator has steps and sizes. */
    private const MAX = 64;

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'array', 'max:'.self::MAX];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, string|int|float>|null
     */
    public function store(mixed $value, array $node): ?array
    {
        $preset = self::clean($value);

        return $preset === [] ? null : $preset;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, string|int|float>
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): array
    {
        return self::clean($stored);
    }

    /**
     * Keys that could be a step or a field, each with a value or a number.
     *
     * @return array<string, string|int|float>
     */
    public static function clean(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $preset = [];

        foreach ($value as $key => $one) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1) {
                continue;
            }

            if (is_int($one) || is_float($one)) {
                $preset[$key] = Engine::number((float) $one);
            } elseif (is_string($one) && trim($one) !== '') {
                $preset[$key] = trim($one);
            }

            if (count($preset) >= self::MAX) {
                break;
            }
        }

        return $preset;
    }
}
