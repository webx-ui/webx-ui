<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use WebxUi\Blocks\BlockType;

/**
 * Blade from the database to PHP on disk.
 *
 * One file per version, named by slug and number, so nothing is ever invalidated: a new
 * version is a new file, and the old one keeps serving whatever still points at it.
 * `view:cache` does not know these files, so the first request after a deploy compiles again —
 * and that is all it costs.
 */
final class TemplateCompiler
{
    public function __construct(
        private readonly BladeCompiler $blade,
        private readonly Filesystem $files,
        private readonly string $directory,
    ) {}

    /** The compiled file of a version, written on the first ask. */
    public function path(BlockType $type): string
    {
        $path = $this->directory.'/'.$type->slug.'-'.$type->version.'.php';

        if (! $this->files->exists($path)) {
            $this->write($path, $type->template);
        }

        return $path;
    }

    /**
     * A template nobody has saved yet — the editor rendering what is being typed. Keyed by its
     * own content, so the same text compiles once and a different text never collides.
     */
    public function adHoc(string $slug, string $template): string
    {
        $path = $this->directory.'/'.$slug.'-tmp-'.sha1($template).'.php';

        if (! $this->files->exists($path)) {
            $this->write($path, $template);
        }

        return $path;
    }

    /** The ad-hoc files, once the editor is done with them. */
    public function forgetAdHoc(): void
    {
        foreach ($this->files->glob($this->directory.'/*-tmp-*.php') as $path) {
            $this->files->delete($path);
        }
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Which line of the template a line of its compiled file came from.
     *
     * Blade doubles the newline after every echo (PHP eats one after `?>`), so the compiled
     * file is longer than the template and the exception's line points past the mistake. The
     * way back is to compile a copy with a marker at the head of every line and read the last
     * marker before the failing one — the copy is never run, so a marker landing inside an
     * expression breaks nothing.
     */
    public function templateLine(string $template, int $compiledLine): ?int
    {
        $marked = [];

        foreach (preg_split('/\r?\n/', $template) ?: [] as $index => $line) {
            $marked[] = '|---wx-line:'.($index + 1).'---|'.$line;
        }

        $lines = preg_split('/\r?\n/', $this->blade->compileString(implode("\n", $marked))) ?: [];

        for ($number = min($compiledLine, count($lines)); $number >= 1; $number--) {
            if (preg_match_all('/\|---wx-line:(\d+)---\|/', $lines[$number - 1], $found) > 0) {
                return (int) ($number === $compiledLine ? $found[1][0] : end($found[1]));
            }
        }

        return null;
    }

    private function write(string $path, string $template): void
    {
        $this->files->ensureDirectoryExists($this->directory);
        $this->files->put($path, $this->blade->compileString($template));
    }
}
