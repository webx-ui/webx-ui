<?php

declare(strict_types=1);

namespace WebxUi\Media\Screens;

use WebxUi\Admin\Screens\ChecksNewValues;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-file`: one file, a document more often than not.
 *
 * The same value as `wx-media`, kept and read the same way — what differs is that a document has
 * no `alt` worth asking for and no preview worth framing, so the field draws a glyph, a name and
 * a size instead.
 *
 * One thing of its own: where the node says `props.download: true`, the value may carry
 * `download: true`, and the site then reads `download` as the name the file was uploaded under —
 * what `<a download="…">` takes, as a file link of `wx-link` does. Elsewhere the flag is not kept,
 * and `download` reads null.
 */
final class FileFieldType implements ChecksNewValues, FieldType
{
    public function __construct(private readonly MediaValues $values) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return $this->values->rules($node, $this->values->accept($node));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        $stored = $this->values->store($value);

        if ($stored !== null && $this->offersDownload($node) && is_array($value) && filter_var($value['download'] ?? false, FILTER_VALIDATE_BOOL)) {
            $stored['download'] = true;
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        $resolved = $this->values->resolve($stored, $locale);

        if ($resolved === null) {
            return null;
        }

        $wanted = is_array($stored) && ($stored['download'] ?? false) === true && $this->offersDownload($node);
        $resolved['download'] = $wanted ? $this->values->downloadName((string) $resolved['path']) : null;

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function offersDownload(array $node): bool
    {
        return ($node['props']['download'] ?? false) === true;
    }

    /**
     * A key the library never had, in a value written now: a path typed rather than picked.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    public function newValueProblems(mixed $value, array $node): array
    {
        return $this->values->missing($value);
    }
}
