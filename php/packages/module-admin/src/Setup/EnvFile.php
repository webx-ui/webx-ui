<?php

declare(strict_types=1);

namespace WebxUi\Admin\Setup;

use Illuminate\Filesystem\Filesystem;

/**
 * The site's `.env`, edited in place.
 *
 * Laravel reads the file immutably: the first assignment of a name wins. So a value appended at
 * the bottom next to one that is already up the file changes nothing at all, and the run that
 * wrote it looks like it worked — which is why every write here replaces what is there rather
 * than adding to it, and only appends a name the file does not mention.
 */
final class EnvFile
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly string $path,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return $this->files->exists($this->path);
    }

    /** The value as the file has it, with the quotes taken off; null when it says nothing. */
    public function get(string $key): ?string
    {
        foreach ($this->lines() as $line) {
            if ($this->assigns($line, $key)) {
                return $this->unquote(trim(substr(trim($line), strlen($key) + 1)));
            }
        }

        return null;
    }

    /**
     * The value, unless it is one nobody chose.
     *
     * The skeleton ships placeholders — `APP_NAME=Laravel`, `DB_DATABASE=laravel` — and a
     * default worked out from the directory is a better answer than repeating one of those
     * back at the person who has never seen the file.
     *
     * @param  list<string>  $placeholders
     */
    public function chosen(string $key, array $placeholders = []): ?string
    {
        $value = $this->get($key);

        return $value === null || $value === '' || in_array($value, $placeholders, true) ? null : $value;
    }

    /**
     * Write these names, replacing what the file says about each of them.
     *
     * @param  array<string, string>  $values
     */
    public function write(array $values): void
    {
        $lines = $this->lines();

        foreach ($values as $key => $value) {
            $assignment = $key.'='.$this->quote($value);
            $found = false;

            foreach ($lines as $index => $line) {
                if ($this->assigns($line, $key)) {
                    // Every one of them, not only the first: two assignments that disagree are
                    // a file where the answer depends on which one somebody deletes later.
                    $lines[$index] = $assignment;
                    $found = true;
                }
            }

            if ($found) {
                continue;
            }

            // A commented-out assignment is where the name belongs — the Laravel skeleton ships
            // `# DB_HOST=127.0.0.1` — and filling it in reads better than a second one below.
            foreach ($lines as $index => $line) {
                if ($this->assigns(ltrim($line, "# \t"), $key)) {
                    $lines[$index] = $assignment;
                    $found = true;

                    break;
                }
            }

            if (! $found) {
                $lines[] = $assignment;
            }
        }

        $this->files->put($this->path, implode("\n", $lines)."\n");
    }

    /** @return list<string> */
    private function lines(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $contents = (string) $this->files->get($this->path);

        return explode("\n", rtrim(str_replace("\r\n", "\n", $contents), "\n"));
    }

    private function assigns(string $line, string $key): bool
    {
        return str_starts_with(trim($line), $key.'=');
    }

    private function unquote(string $value): string
    {
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
            return stripcslashes(substr($value, 1, -1));
        }

        return $value;
    }

    private function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.\/:@^~+-]+$/', $value) === 1) {
            return $value;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
