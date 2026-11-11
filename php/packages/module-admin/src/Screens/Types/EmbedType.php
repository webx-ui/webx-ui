<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use WebxUi\Admin\Screens\Embeds;
use WebxUi\Admin\Screens\EmbedSource;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-embed`: the frame of a service, pasted as the service gives it — the whole `<iframe …>` or
 * its address — and kept as the address alone. `props.source` names the service ({@see Embeds}):
 * `google-maps` takes Google Maps' "Embed a map" and nothing else.
 *
 * The check is in the rules, not a check of new values only: HTML is never kept, whoever writes —
 * the panel, an agent's `blocks_*`, an import — and a value already kept is an address that passes
 * again. A source nobody registered refuses what is not empty: an unchecked frame is worse than none.
 */
final class EmbedType implements FieldType
{
    /** A pasted code is a few hundred characters; the address of a frame, under two thousand. */
    private const int MAX = 10000;

    public function __construct(private readonly Embeds $embeds) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $source = $this->source($node);
        $name = is_string($node['props']['source'] ?? null) ? $node['props']['source'] : '';

        return ['nullable', 'string', 'max:'.self::MAX, static function (string $attribute, mixed $value, Closure $fail) use ($source, $name): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }

            if ($source === null) {
                $fail((string) __('webx-admin::screens.embed-source-unknown', ['source' => $name]));

                return;
            }

            if ($source->address($value) === null) {
                $fail($source->refusal());
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        // Refused by the rules before it gets here — and were it not, HTML is not what is kept.
        return $this->source($node)?->address($value);
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
    private function source(array $node): ?EmbedSource
    {
        $name = $node['props']['source'] ?? null;

        return is_string($name) ? $this->embeds->get($name) : null;
    }
}
