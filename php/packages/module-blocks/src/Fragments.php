<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use WebxUi\Admin\Screens\FieldTypes;

/**
 * Which shared blocks a tree places (the shared blocks spec): the values of its `wx-fragment`
 * fields, at any depth.
 *
 * By the field rather than by the slug `fragment`: the offered type is a site's to rename or to
 * copy into one of its own, and a block that places a region is one whatever it is called.
 * A type that is not published is read as its draft, so that a tree checked in the middle of an
 * editor's work is judged by the type the editor sees.
 */
final class Fragments
{
    /** The field type whose value is the name of a shared block. */
    public const FIELD = 'wx-fragment';

    public function __construct(
        private readonly BlockTypes $types,
        private readonly FieldTypes $fields,
    ) {}

    /**
     * The names, each once, in the order they stand; hidden blocks included — switched off is
     * still placed, and switched back on in one click.
     *
     * @param  iterable<array-key, mixed>|null  $tree
     * @return list<string>
     */
    public function placed(?iterable $tree): array
    {
        /** @var array<string, list<string>> $fieldsOf the fragment fields of each slug met so far */
        $fieldsOf = [];
        $names = [];

        Content::walk($tree, function (array $node) use (&$fieldsOf, &$names): void {
            $slug = (string) $node['type'];
            $fieldsOf[$slug] ??= $this->fieldsOf($slug);

            foreach ($fieldsOf[$slug] as $field) {
                $name = $node['values'][$field] ?? null;

                if (is_string($name) && $name !== '') {
                    $names[$name] = true;
                }
            }
        });

        return array_keys($names);
    }

    /**
     * @return list<string>
     */
    private function fieldsOf(string $slug): array
    {
        $type = $this->types->find($slug) ?? $this->types->draft($slug);

        if ($type === null) {
            return [];
        }

        $fields = [];

        foreach (Schema::valueFields($type->schema, $this->fields) as $id => $node) {
            if (($node['type'] ?? null) === self::FIELD) {
                $fields[] = (string) $id;
            }
        }

        return $fields;
    }
}
