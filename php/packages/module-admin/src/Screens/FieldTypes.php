<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * Type name → how its value is checked, kept and read. Filled with the core types by this
 * package, with `wx-media` by the media module, and with a project's own by the project.
 *
 * A type nobody registered is not an error: its value is stored as it came, without rules,
 * and read back the same. The front end shows such a node loudly; the server just does not
 * pretend to know what it is.
 */
final class FieldTypes
{
    /** @var array<string, FieldType> */
    private array $types = [];

    public function register(string $type, FieldType $fieldType): void
    {
        $this->types[$type] = $fieldType;
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    public function get(string $type): ?FieldType
    {
        return $this->types[$type] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_keys($this->types);
        sort($names);

        return $names;
    }
}
