<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Fields;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use WebxUi\Admin\Screens\ResolvesMissing;

/**
 * `wx-slot` — a named slot of a component, `<x-slot:aside>` at the call.
 *
 * What a tag passes is markup the caller already rendered, so it is printed as it is. The same
 * goes for the sample, where the slot is a textarea of HTML: the component on its own sample has
 * to look the way it looks when it is called.
 *
 * Missing is empty, never null ({@see ResolvesMissing}): a template writes `{{ $aside }}` or
 * `@if ($aside->isNotEmpty())`, and a slot the caller left out must not turn either into an
 * error on the one page that left it out.
 */
final class SlotType implements ResolvesMissing
{
    public function rules(array $node): array
    {
        return ['nullable', 'string'];
    }

    public function store(mixed $value, array $node): mixed
    {
        return is_string($value) ? $value : null;
    }

    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        if ($stored instanceof Htmlable) {
            return $stored;
        }

        return new HtmlString(is_string($stored) ? $stored : '');
    }
}
