<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Screens;

use WebxUi\Admin\Screens\ChecksNewValues;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Configurator\Models\Configurator;

/**
 * `wx-configurator`: one configurator, by its key — what `<x-webx-configurator key="…" />` takes
 * and what a block of the site keeps (decision 11), the way `wx-inbox-form` keeps a form's slug.
 *
 * A key no configurator has is refused, but only when it is being written ({@see ChecksNewValues}):
 * a configurator put in the bin after the page was saved must not stop the page from being saved
 * again — the picker draws it as missing — while a key typed by an agent or by hand is the
 * configurator that silently never appears. One in the bin is not offered: its key is taken, and
 * the site prints nothing for it.
 *
 * Empty is "none", stored as null.
 */
final class ConfiguratorFieldType implements ChecksNewValues, FieldType
{
    /** How many keys a refusal lists: enough to pick from, not a page of them. */
    private const LISTED = 20;

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'string', 'max:64'];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): ?string
    {
        return self::key($value);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): ?string
    {
        return self::key($stored);
    }

    /**
     * A key no configurator has, in a value written now — with the keys there are, so whoever
     * wrote it, an agent most of all, can put the right one in the next call.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    public function newValueProblems(mixed $value, array $node): array
    {
        $key = self::key($value);

        if ($key === null || Configurator::query()->where('key', $key)->exists()) {
            return [];
        }

        $keys = Configurator::query()->orderBy('id')->limit(self::LISTED)->pluck('key')->all();

        return [$keys === []
            ? (string) __('webx-configurator::configurator.unknown-none', ['key' => $key])
            : (string) __('webx-configurator::configurator.unknown', ['key' => $key, 'keys' => implode(', ', $keys)])];
    }

    private static function key(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $key = trim($value);

        return $key === '' ? null : $key;
    }
}
