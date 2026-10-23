<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

use WebxUi\Compositions\Exceptions\CompositionException;

/**
 * Every composition × handle the layers give, built the way the compiler builds them but kept
 * apart from the compiled set (COMPOSITIONS §8): what `webx:composition:show`, the theme's check
 * and the doctor read. A file that cannot be read is said for that file, and the rest is still
 * looked at — one broken patch should not hide the findings of every other.
 */
final class Inspector
{
    private readonly Reader $reader;

    private ?Builder $builder = null;

    public function __construct(
        private readonly Layers $layers,
        private readonly Probe $probe,
    ) {
        $this->reader = new Reader;
    }

    public function builder(): Builder
    {
        return $this->builder ??= new Builder($this->layers, $this->reader, new Patcher($this->reader));
    }

    public function layers(): Layers
    {
        return $this->layers;
    }

    /**
     * The files of every layer that cannot be read as meant: not JSON, against the schema, a base
     * of a handle — each with why.
     *
     * @return list<array{file: string, message: string}>
     */
    public function unreadable(): array
    {
        $failures = [];

        foreach ($this->builder()->sources() as $source) {
            foreach ($source->compositions() as $composition) {
                foreach ($source->handles($composition) as $handle) {
                    try {
                        $data = $source->read($composition, $handle);

                        if ($data !== null) {
                            $this->reader->check($data, $source->where($composition, $handle), $handle === 'default');
                        }
                    } catch (CompositionException $e) {
                        $failures[] = ['file' => $source->where($composition, $handle), 'message' => $e->getMessage()];
                    }
                }
            }
        }

        return $failures;
    }

    /**
     * The compositions some layer has a base for; empty when reading them fails.
     *
     * @return list<string>
     */
    public function compositions(): array
    {
        try {
            return $this->builder()->compositions();
        } catch (CompositionException) {
            return [];
        }
    }

    /**
     * The handles of a composition the compiler compiles: its files' and those the config's
     * `layouts` sends to it.
     *
     * @return list<string>
     */
    public function handles(string $composition): array
    {
        $handles = $this->builder()->handles($composition);

        foreach ($this->layers->layouts() as $handle => $to) {
            if ($to === $composition) {
                $handles[] = $handle;
            }
        }

        return array_values(array_unique($handles));
    }

    /**
     * The final tree of a pair with what compiling it would say — the views and components that
     * are not there included. Throws when the tree cannot be built at all.
     */
    public function tree(string $composition, string $handle = 'default'): Tree
    {
        $tree = $this->builder()->build($composition, $handle);
        (new Emitter($this->probe))->view($tree, $composition, $handle);

        return $tree;
    }

    /** The Blade the compiler writes for a pair. */
    public function blade(string $composition, string $handle = 'default'): string
    {
        return (new Emitter($this->probe))->view($this->builder()->build($composition, $handle), $composition, $handle);
    }

    /**
     * Every pair: its tree, or why it cannot be built.
     *
     * @return list<array{composition: string, handle: string, tree: Tree|null, error: string|null}>
     */
    public function all(): array
    {
        $pairs = [];

        foreach ($this->compositions() as $composition) {
            try {
                $handles = $this->handles($composition);
            } catch (CompositionException $e) {
                $pairs[] = ['composition' => $composition, 'handle' => 'default', 'tree' => null, 'error' => $e->getMessage()];

                continue;
            }

            foreach ($handles as $handle) {
                try {
                    $pairs[] = ['composition' => $composition, 'handle' => $handle, 'tree' => $this->tree($composition, $handle), 'error' => null];
                } catch (CompositionException $e) {
                    $pairs[] = ['composition' => $composition, 'handle' => $handle, 'tree' => null, 'error' => $e->getMessage()];
                }
            }
        }

        return $pairs;
    }

    /**
     * The findings of every pair, each once — a finding of `default.json` repeats on every handle.
     *
     * @return list<Finding>
     */
    public function findings(): array
    {
        $findings = [];

        foreach ($this->all() as $pair) {
            if ($pair['tree'] !== null) {
                foreach ($this->said($pair['tree']) as $finding) {
                    $findings[(string) $finding] = $finding;
                }
            }
        }

        return array_values($findings);
    }

    /**
     * The findings of a tree as a person should read them. A view, a component or a directive the
     * base or a module names and the site does not have is what the site did not install — the
     * slot of the head falls back to @webxSeo on a site without the SEO module — and is a note;
     * one a theme or the site put there is their mistake.
     *
     * @return list<Finding>
     */
    public function said(Tree $tree): array
    {
        $quiet = [$tree->layers[0] ?? '', ...array_map(static fn (Source $source): string => $source->label, array_filter($this->builder()->sources(), static fn (Source $source): bool => $source->kind === Source::MODULE))];

        return array_map(static fn (Finding $finding): Finding => ! $finding->note && self::emitted($finding) && in_array($finding->file, $quiet, true)
            ? new Finding($finding->file, $finding->operation, $finding->message, note: true)
            : $finding, $tree->findings);
    }

    /** A finding of the emitter: something the tree names that the application does not have. */
    public static function emitted(Finding $finding): bool
    {
        return preg_match('/^(view|component|directive) /', $finding->operation) === 1;
    }
}
