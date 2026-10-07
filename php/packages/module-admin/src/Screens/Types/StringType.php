<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use WebxUi\Admin\Screens\FieldType;

/**
 * Text: `wx-input`, `wx-textarea`. The limit is the node's `props.maxlength` when it says one,
 * else what a text column comfortably holds. `props.type` — `email`, `url` or `tel` — holds the
 * value to that format as well.
 */
final class StringType implements FieldType
{
    public function __construct(private readonly int $max = 65535) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $max = $node['props']['maxlength'] ?? $this->max;
        $rules = ['nullable', 'string', 'max:'.(int) $max];

        // The input's `type` is what the browser checks and which keyboard a phone opens; the
        // server holds the same line, so an address saved by an agent or a script is no looser
        // than one typed in the panel. Empty stays allowed: `nullable` lets null through, and
        // Laravel skips these rules for an empty string.
        return match ($node['props']['type'] ?? null) {
            'email' => [...$rules, 'email'],
            'url' => [...$rules, 'url:http,https'],
            // Lenient on purpose: numbers are written every which way across countries, so
            // only the characters are held, and enough digits for it to be a number at all.
            'tel' => [...$rules, 'regex:/^[0-9\s()+.\-]*$/', 'regex:/(?:\d\D*){3,}/'],
            default => $rules,
        };
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
