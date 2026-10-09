<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use JsonException;
use WebxUi\Themes\Exceptions\ThemeException;

/**
 * What a theme says about itself (spec §5). A packaged theme keeps it under `extra.webx.theme`
 * of its composer.json; a local theme has no composer.json of its own and keeps the same keys
 * under `theme` in `theme/theme.json`. Everything else is convention (§4): a `views/` directory
 * is views, and no key repeats a directory name.
 */
final readonly class ThemeManifest
{
    /**
     * @param  list<string>  $uses  What the theme stands on, top layer first.
     * @param  list<string>  $requires
     * @param  list<string>  $locales
     * @param  list<string>  $editable
     * @param  list<array<string, mixed>>  $fonts
     */
    public function __construct(
        public string $name,
        public string $path,
        public bool $local,
        public string $title = '',
        public array $uses = [],
        public array $requires = [],
        public array $locales = [],
        public array $editable = [],
        public array $fonts = [],
    ) {}

    /** A packaged theme: a directory with a composer.json of `"type": "webx-theme"`. */
    public static function fromPackage(string $path): self
    {
        $path = self::normalise($path);
        $file = $path.'/composer.json';
        $composer = self::read($file);

        if (($composer['type'] ?? null) !== 'webx-theme') {
            throw ThemeException::invalidManifest($file, 'a packaged theme has "type": "webx-theme".');
        }

        $name = $composer['name'] ?? null;

        if (! is_string($name) || $name === '') {
            throw ThemeException::invalidManifest($file, 'the package has no name.');
        }

        $theme = $composer['extra']['webx']['theme'] ?? [];

        return self::make($name, $path, false, is_array($theme) ? $theme : [], $file);
    }

    /**
     * A local theme: `theme.json` in a directory of the site. It is named by that directory
     * relative to the project, which is also what `webx-themes.theme` holds for it.
     */
    public static function fromLocal(string $path, string $name): self
    {
        $path = self::normalise($path);
        $file = $path.'/theme.json';
        $theme = self::read($file)['theme'] ?? [];

        return self::make($name, $path, true, is_array($theme) ? $theme : [], $file);
    }

    /** The directory of Blade views, if the theme has one. */
    public function views(): ?string
    {
        return is_dir($this->path.'/views') ? $this->path.'/views' : null;
    }

    /** The theme's overrides of a module namespace: `views/vendor/<ns>`, if it has any. */
    public function namespaceViews(string $namespace): ?string
    {
        $dir = $this->path.'/views/vendor/'.$namespace;

        return is_dir($dir) ? $dir : null;
    }

    /**
     * The layer's tokens.json (§7.3). A theme without one has nothing to say about tokens and
     * takes them all from below; a file that is there but malformed is an error, not silence.
     */
    public function tokens(): TokenFile
    {
        $file = $this->path.'/tokens.json';

        if (! is_file($file)) {
            return new TokenFile($file);
        }

        $fail = ThemeException::invalidTokens(...);
        $data = self::read($file, $fail);

        $unknown = array_diff(array_keys($data), ['defaults', 'presets', 'vocabulary']);

        if ($unknown !== []) {
            throw $fail($file, 'unknown key "'.implode('", "', $unknown).'"; a tokens.json has "defaults", "presets" and "vocabulary".');
        }

        $presets = [];

        foreach (self::map($data, 'presets', $file) as $name => $preset) {
            if (! is_array($preset) || ! is_string($preset['title'] ?? '') || array_diff(array_keys($preset), ['title', 'tokens']) !== []) {
                throw $fail($file, "preset \"{$name}\" is an object with \"title\" and \"tokens\".");
            }

            $presets[$name] = [
                'title' => (string) ($preset['title'] ?? $name),
                'tokens' => self::values($preset, 'tokens', $file, "preset \"{$name}\""),
            ];
        }

        $vocabulary = [];

        foreach (self::values($data, 'vocabulary', $file) as $name => $type) {
            $vocabulary[$name] = TokenType::tryFrom($type)
                ?? throw $fail($file, "\"{$name}\" has an unknown type \"{$type}\"; the types are ".implode(', ', array_column(TokenType::cases(), 'value')).'.');
        }

        return new TokenFile($file, self::values($data, 'defaults', $file), $presets, $vocabulary);
    }

    /**
     * An object of token names: the names follow the vocabulary's spelling, the values are strings.
     *
     * @param  array<mixed>  $data
     * @return array<string, string>
     */
    private static function values(array $data, string $key, string $file, ?string $where = null): array
    {
        $values = [];

        foreach (self::map($data, $key, $file) as $name => $value) {
            if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1 || ! is_string($value)) {
                throw ThemeException::invalidTokens($file, '"'.($where === null ? $key : "{$where}.{$key}")."\" maps lower-case token names to strings; \"{$name}\" does not.");
            }

            $values[$name] = trim($value);
        }

        return $values;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    private static function map(array $data, string $key, string $file): array
    {
        $value = $data[$key] ?? [];

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw ThemeException::invalidTokens($file, "\"{$key}\" is an object.");
        }

        $map = [];

        foreach ($value as $name => $item) {
            $map[(string) $name] = $item;
        }

        return $map;
    }

    /**
     * @param  array<mixed>  $theme
     */
    private static function make(string $name, string $path, bool $local, array $theme, string $file): self
    {
        $title = $theme['title'] ?? '';

        if (! is_string($title)) {
            throw ThemeException::invalidManifest($file, '"title" is a string.');
        }

        $fonts = $theme['fonts'] ?? [];

        if (! is_array($fonts) || ! array_is_list($fonts) || array_filter($fonts, fn ($font) => ! is_array($font)) !== []) {
            throw ThemeException::invalidManifest($file, '"fonts" is a list of objects.');
        }

        /** @var list<array<string, mixed>> $fonts */
        return new self(
            name: $name,
            path: $path,
            local: $local,
            title: $title,
            uses: self::strings($theme, 'uses', $file),
            requires: self::strings($theme, 'requires', $file),
            locales: self::strings($theme, 'locales', $file),
            editable: self::strings($theme, 'editable', $file),
            fonts: $fonts,
        );
    }

    /**
     * @param  array<mixed>  $theme
     * @return list<string>
     */
    private static function strings(array $theme, string $key, string $file): array
    {
        $value = $theme[$key] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            throw ThemeException::invalidManifest($file, "\"{$key}\" is a list of strings.");
        }

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                throw ThemeException::invalidManifest($file, "\"{$key}\" is a list of strings.");
            }
        }

        return $value;
    }

    /**
     * @param  (callable(string, string): ThemeException)|null  $fail
     * @return array<mixed>
     */
    private static function read(string $file, ?callable $fail = null): array
    {
        $fail ??= ThemeException::invalidManifest(...);

        if (! is_file($file)) {
            throw $fail($file, 'the file does not exist.');
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw $fail($file, $e->getMessage());
        }

        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw $fail($file, 'the top level is an object.');
        }

        return $data;
    }

    /** One spelling of a path, so the view finder never holds the same directory twice. */
    private static function normalise(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
