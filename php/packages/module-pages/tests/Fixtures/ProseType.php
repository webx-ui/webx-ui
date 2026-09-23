<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests\Fixtures;

use WebxUi\Admin\Screens\FieldType;

/**
 * A field type that cleans what it is given: the shape a rich text field has, where `store()`
 * is the only thing between pasted markup and the page it will be printed on.
 *
 * What is being tested with it is the editor's save, so the type has to be one the editor's
 * screen could hold — its own package has the same stand-in for the agent's door.
 */
final class ProseType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'string'];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if (! is_string($value)) {
            return null;
        }

        // Whole element, not just its tags: `strip_tags` alone would leave the script's own
        // words standing in the page as text.
        $clean = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $value);
        $clean = strip_tags($clean, ['p', 'strong', 'em', 'a']);

        return trim(strip_tags($clean)) === '' ? null : $clean;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
