<?php

declare(strict_types=1);

namespace WebxUi\Admin\Panel;

/**
 * The panel's entry file, edited between its markers and nowhere else.
 *
 * Almost everything in `resources/js/admin.ts` belongs to the site: the avatar resolver, the
 * media field handed to the modules that ask for one, whatever else only the site knows is
 * installed. So the command does not read the file for shapes it recognises — it writes into
 * three regions it put there itself, and if they are gone it says what to add and stops.
 */
final class EntryFile
{
    /** The regions, in the order they appear in the stub. */
    public const REGIONS = ['imports', 'styles', 'modules'];

    private bool $changed = false;

    public function __construct(private string $contents) {}

    public function contents(): string
    {
        return $this->contents;
    }

    public function changed(): bool
    {
        return $this->changed;
    }

    /** Whether all three regions are there to write into. */
    public function hasRegions(): bool
    {
        foreach (self::REGIONS as $region) {
            if ($this->region($region) === null) {
                return false;
            }
        }

        return true;
    }

    public function region(string $name): ?string
    {
        return preg_match($this->pattern($name), $this->contents, $matches) === 1
            ? $matches['body']
            : null;
    }

    /** Whether a module's registration is already in the file, under any arguments. */
    public function registers(string $name): bool
    {
        return preg_match('/\b'.preg_quote($name, '/').'\s*\(/', $this->scope('modules')) === 1;
    }

    public function importsFrom(string $specifier): bool
    {
        return preg_match($this->importPattern($specifier), $this->scope('imports')) === 1;
    }

    public function hasStyle(string $specifier): bool
    {
        return str_contains($this->scope('styles'), "'{$specifier}'");
    }

    /**
     * Add an import, folding it into the line that already reads from the same module.
     *
     * A fresh entry file with the sign-in plugin in it already imports `auth` from
     * `@webx-ui/module-auth`; the sections that package also brings come from the registry.
     * Two import statements for one module compile, but nobody writes them by hand, and the
     * file is meant to look like something a person wrote.
     *
     * @param  list<string>  $names
     */
    public function addImport(string $line, string $specifier, array $names): void
    {
        $existing = $this->importLine($specifier);

        if ($existing === null) {
            $this->append('imports', $line);

            return;
        }

        if (preg_match('/\{([^}]*)\}/', $existing, $matches) !== 1) {
            return; // A default or namespace import; leaving it alone is the safe reading.
        }

        $already = array_values(array_filter(array_map(trim(...), explode(',', $matches[1]))));
        $merged = array_values(array_unique([...$already, ...$names]));

        if (count($merged) === count($already)) {
            return;
        }

        usort($merged, static fn (string $a, string $b): int => strcasecmp($a, $b));

        $this->replace($existing, str_replace($matches[0], '{ '.implode(', ', $merged).' }', $existing));
    }

    public function addStyle(string $specifier): void
    {
        $this->append('styles', "import '{$specifier}'");
    }

    public function addModule(string $call): void
    {
        $this->append('modules', "{$call},");
    }

    /** Put a line in front of a region's closing marker, at the region's own indentation. */
    private function append(string $region, string $line): void
    {
        $pattern = $this->pattern($region);

        if (preg_match($pattern, $this->contents, $matches) !== 1) {
            return;
        }

        $indent = $matches['indent'];
        $replacement = $matches['open'].$matches['body'].$indent.$line."\n".$matches['close'];

        $this->replace($matches[0], $replacement);
    }

    private function importLine(string $specifier): ?string
    {
        return preg_match($this->importPattern($specifier), $this->region('imports') ?? '', $matches) === 1
            ? $matches[0]
            : null;
    }

    private function importPattern(string $specifier): string
    {
        return '/^.*from\s+[\'"]'.preg_quote($specifier, '/').'[\'"].*$/m';
    }

    /**
     * The region to read a question out of — the whole file when there is no such region.
     *
     * Erased markers leave nowhere to write, but the question of whether something is already
     * there still has an answer, and it is the one worth giving.
     */
    private function scope(string $region): string
    {
        return $this->region($region) ?? $this->contents;
    }

    private function replace(string $search, string $replacement): void
    {
        $position = strpos($this->contents, $search);

        if ($position === false) {
            return;
        }

        $this->contents = substr_replace($this->contents, $replacement, $position, strlen($search));
        $this->changed = true;
    }

    private function pattern(string $name): string
    {
        $name = preg_quote($name, '/');

        return '/(?<open>^(?<indent>[ \t]*)\/\/ webx:'.$name.'[ \t]*\R)'
            .'(?<body>.*?)'
            .'(?<close>^[ \t]*\/\/ \/webx:'.$name.'[ \t]*$)/ms';
    }
}
