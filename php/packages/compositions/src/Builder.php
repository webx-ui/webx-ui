<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

use WebxUi\Compositions\Exceptions\CompositionException;

/**
 * The tree of a composition for a handle, layer by layer (COMPOSITIONS §5.2):
 *
 *     1. the base — the one default.json with a `root`, or with `extends` and the parent's final
 *        tree for the same handle — and that layer's own <handle>.json
 *     2. the modules, by id               — each: default, then <handle>
 *     3. the themes, bottom up            — each: default, then <handle>
 *     4. the site's config                — default, then <handle>
 *
 * A layer whole, then the next: a higher layer always wins over a lower one, the way the theme
 * chain does.
 */
final class Builder
{
    /** @var array<string, Tree> */
    private array $built = [];

    /** @var list<Source>|null */
    private ?array $sources = null;

    public function __construct(
        private readonly Layers $layers,
        private readonly Reader $reader,
        private readonly Patcher $patcher,
    ) {}

    /** @return list<Source> */
    public function sources(): array
    {
        return $this->sources ??= $this->layers->sources();
    }

    /**
     * Every composition some layer has a base for.
     *
     * @return list<string>
     */
    public function compositions(): array
    {
        $names = [];

        foreach ($this->sources() as $source) {
            foreach ($source->compositions() as $name) {
                $data = $source->read($name, 'default');

                if ($data !== null && $this->reader->isBase($data)) {
                    $names[$name] = true;
                }
            }
        }

        $names = array_keys($names);
        sort($names);

        return array_values(array_map('strval', $names));
    }

    /**
     * The handles of a composition that some layer has a file for — its parent's too — and
     * `default`, which is always there.
     *
     * @param  list<string>  $seen  the compositions on the way up `extends`, for a circle
     * @return list<string>
     */
    public function handles(string $composition, array $seen = []): array
    {
        $handles = ['default' => true];

        foreach ($this->sources() as $source) {
            foreach ($source->handles($composition) as $handle) {
                $handles[$handle] = true;
            }
        }

        $parent = $this->parent($composition);

        if ($parent !== null && ! in_array($parent, $seen, true)) {
            foreach ($this->handles($parent, [...$seen, $composition]) as $handle) {
                $handles[$handle] = true;
            }
        }

        $handles = array_map('strval', array_keys($handles));
        sort($handles);

        return array_values(array_unique(['default', ...$handles]));
    }

    /**
     * @param  list<string>  $stack  the compositions being built, for a circle of `extends`
     */
    public function build(string $composition, string $handle = 'default', array $stack = []): Tree
    {
        if (isset($this->built[$composition.'@'.$handle])) {
            return $this->built[$composition.'@'.$handle]->copy();
        }

        if (in_array($composition, $stack, true)) {
            throw new CompositionException('extends goes in a circle: '.implode(' → ', [...$stack, $composition]).'.');
        }

        [$baseSource, $base] = $this->base($composition);
        $where = $baseSource->where($composition, 'default');

        if (isset($base['extends'])) {
            $parent = (string) $base['extends'];

            if (! in_array($parent, $this->compositions(), true)) {
                throw new CompositionException("{$where}: extends \"{$parent}\", which no layer has a base for.");
            }

            $tree = $this->build($parent, $handle, [...$stack, $composition]);
            $this->patcher->apply($tree, $base, $baseSource->label, $where, base: true);
        } else {
            $tree = new Tree($this->reader->build((array) $base['root'], $baseSource->label, base: true));
            $tree->touchedBy($baseSource->label);
            $this->protect($tree);
            $this->duplicates($tree, $where);
        }

        $this->layer($tree, $baseSource, $composition, $handle, skipDefault: true);

        foreach ($this->sources() as $source) {
            if ($source !== $baseSource) {
                $this->layer($tree, $source, $composition, $handle);
            }
        }

        ['vars' => $tree->vars, 'dispatcher' => $tree->dispatcher] = $this->contract($composition);
        $this->built[$composition.'@'.$handle] = $tree;

        return $tree->copy();
    }

    /** The composition `extends` names, if its base has one. */
    public function parent(string $composition): ?string
    {
        foreach ($this->sources() as $source) {
            $data = $source->read($composition, 'default');

            if ($data !== null && isset($data['extends']) && is_string($data['extends'])) {
                return $data['extends'];
            }
        }

        return null;
    }

    /**
     * What the base says about the composition besides its tree (§2.2): the variables its leaves
     * get — a variant that `extends` without its own gets its parent's, it is included the same
     * way — and the view that includes it, which is never inherited: each variant has its own.
     *
     * @param  list<string>  $seen  the compositions on the way up `extends`, for a circle
     * @return array{vars: list<string>, dispatcher: string|null}
     */
    public function contract(string $composition, array $seen = []): array
    {
        [, $base] = $this->base($composition);
        $vars = isset($base['vars']) ? array_values(array_map('strval', (array) $base['vars'])) : null;
        $parent = isset($base['extends']) ? (string) $base['extends'] : null;

        if ($vars === null && $parent !== null && ! in_array($parent, [...$seen, $composition], true)) {
            $vars = $this->contract($parent, [...$seen, $composition])['vars'];
        }

        return ['vars' => $vars ?? [], 'dispatcher' => isset($base['dispatcher']) ? (string) $base['dispatcher'] : null];
    }

    private function layer(Tree $tree, Source $source, string $composition, string $handle, bool $skipDefault = false): void
    {
        foreach (array_unique(['default', $handle]) as $file) {
            if ($file === 'default' && $skipDefault) {
                continue;
            }

            $data = $source->read($composition, $file);

            if ($data === null) {
                continue;
            }

            $where = $source->where($composition, $file);
            $this->reader->check($data, $where, $file === 'default');

            if ($this->reader->isBase($data)) {
                throw new CompositionException("{$where}: a second base of \"{$composition}\" — a layer above the base patches it.");
            }

            $this->patcher->apply($tree, $data, $source->label, $where);
        }
    }

    /**
     * @return array{Source, array<string, mixed>}
     */
    private function base(string $composition): array
    {
        $found = null;

        foreach ($this->sources() as $source) {
            $data = $source->read($composition, 'default');

            if ($data === null || ! $this->reader->isBase($data)) {
                continue;
            }

            $where = $source->where($composition, 'default');
            $this->reader->check($data, $where, true);

            if ($found !== null) {
                throw new CompositionException("{$where}: a second base of \"{$composition}\" — {$found[0]->where($composition, 'default')} is the first.");
            }

            $found = [$source, $data];
        }

        if ($found === null) {
            throw new CompositionException("No layer has a base for the composition \"{$composition}\".");
        }

        return $found;
    }

    private function protect(Tree $tree): void
    {
        foreach ($tree->root->walk() as $node) {
            if ($node->protected) {
                $node->home = $node->parent?->id;
            }
        }
    }

    private function duplicates(Tree $tree, string $where): void
    {
        $seen = [];

        foreach ($tree->root->walk() as $node) {
            if (isset($seen[$node->id])) {
                throw new CompositionException("{$where}: \"{$node->id}\" twice in the base.");
            }

            $seen[$node->id] = true;
        }
    }
}
