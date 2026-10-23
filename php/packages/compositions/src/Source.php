<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

use JsonException;
use WebxUi\Compositions\Exceptions\CompositionException;

/**
 * One layer's files (COMPOSITIONS §5.1): a module's `resources/compositions/`, a theme's
 * `compositions/`, or the `patches` of the site's config — `<composition>/<handle>.json` each.
 */
final class Source
{
    public const string MODULE = 'module';

    public const string THEME = 'theme';

    public const string CONFIG = 'config';

    /**
     * @param  string  $label  who the layer is, as the findings and the generated views say it
     * @param  array<string, array<string, array<string, mixed>>>  $patches  the config's, by composition and handle
     */
    public function __construct(
        public readonly string $label,
        public readonly string $kind,
        public readonly ?string $dir = null,
        private readonly array $patches = [],
        private readonly string $display = '',
    ) {}

    /** @return list<string> the compositions this layer has files for */
    public function compositions(): array
    {
        if ($this->dir === null) {
            return array_values(array_map('strval', array_keys($this->patches)));
        }

        $names = [];

        foreach (glob($this->dir.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $names[] = basename($directory);
        }

        return $names;
    }

    /** @return list<string> the handles this layer has a file for in one composition */
    public function handles(string $composition): array
    {
        if ($this->dir === null) {
            return array_values(array_map('strval', array_keys($this->patches[$composition] ?? [])));
        }

        return array_values(array_map(static fn (string $file): string => basename($file, '.json'), glob($this->dir.'/'.$composition.'/*.json') ?: []));
    }

    /** Where a file of this layer is, as a person finds it: relative to the site when it is in it. */
    public function where(string $composition, string $handle): string
    {
        return $this->dir === null
            ? "{$this->display}[patches][{$composition}][{$handle}]"
            : ($this->display === '' ? $this->dir : $this->display)."/{$composition}/{$handle}.json";
    }

    /**
     * The decoded file, or null when the layer has none for this pair.
     *
     * @return array<string, mixed>|null
     */
    public function read(string $composition, string $handle): ?array
    {
        if ($this->dir === null) {
            $patch = $this->patches[$composition][$handle] ?? null;

            if ($patch === null) {
                return null;
            }

            if (! is_array($patch)) {
                throw new CompositionException("{$this->where($composition, $handle)}: a patch is an array.");
            }

            /** @var array<string, mixed> $patch */
            return $patch;
        }

        $path = $this->dir.'/'.$composition.'/'.$handle.'.json';

        if (! is_file($path)) {
            return null;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CompositionException("{$this->where($composition, $handle)}: invalid JSON — {$e->getMessage()}.", 0, $e);
        }

        if (! is_array($data) || array_is_list($data) && $data !== []) {
            throw new CompositionException("{$this->where($composition, $handle)}: a composition file is a JSON object.");
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * What a change of this layer shows on disk (§6.2): its directory, the directory of each
     * composition (a new handle file moves its mtime) and every file.
     *
     * @return list<string>
     */
    public function watched(): array
    {
        if ($this->dir === null) {
            return [];
        }

        $paths = [$this->dir];

        foreach (glob($this->dir.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $paths[] = $directory;
            array_push($paths, ...(glob($directory.'/*.json') ?: []));
        }

        return $paths;
    }
}
