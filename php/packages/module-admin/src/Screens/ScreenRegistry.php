<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * Every screen the panel can draw, by name, with the patches other packages and the project
 * have laid over it.
 *
 * A module registers its reference tree from its service provider; anybody else extends it
 * from theirs. Order is registration order — the provider of the project boots last, so its
 * patch is applied last, which is what "the project has the final word" means in code.
 *
 * @phpstan-type Node array<string, mixed>
 */
final class ScreenRegistry
{
    /** @var array<string, list<Node>> */
    private array $trees = [];

    /** @var array<string, list<list<array<string, mixed>>>> */
    private array $patches = [];

    /** @var array<string, list<Node>> */
    private array $built = [];

    /**
     * @param  string|array<int|string, mixed>  $screen  A path to a JSON file, or the decoded
     *                                                   description: the root list, or the
     *                                                   whole file with `screen` and `root`.
     */
    public function register(string $name, string|array $screen): void
    {
        if (preg_match('/^[a-z0-9-]+\.[a-z0-9-]+$/', $name) !== 1) {
            throw ScreenException::badName($name);
        }

        if (isset($this->trees[$name])) {
            throw ScreenException::duplicate($name);
        }

        $decoded = is_string($screen) ? $this->read($screen) : $screen;
        $root = array_key_exists('root', $decoded) ? $decoded['root'] : $decoded;

        $problems = ScreenValidator::screen($root);

        if ($problems !== []) {
            throw ScreenException::invalid("The screen [{$name}]", $problems);
        }

        /** @var list<Node> $root */
        $this->trees[$name] = $root;
        unset($this->built[$name]);
    }

    /**
     * Lays a patch over a screen. The screen need not be registered yet — a project's provider
     * may boot before the module's — so the target is checked when the tree is first built.
     *
     * @param  string|list<array<string, mixed>>  $patch  A path to a JSON file, or the operations.
     */
    public function extend(string $name, string|array $patch): void
    {
        $ops = is_string($patch) ? $this->read($patch) : $patch;
        $problems = ScreenValidator::patch($ops);

        if ($problems !== []) {
            throw ScreenException::invalid("The patch on [{$name}]", $problems);
        }

        /** @var list<array<string, mixed>> $ops */
        $this->patches[$name][] = $ops;
        unset($this->built[$name]);
    }

    public function has(string $name): bool
    {
        return isset($this->trees[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_keys($this->trees);
        sort($names);

        return $names;
    }

    /**
     * The tree with every registered patch applied — the whole screen, nothing cut out and
     * nothing translated. What the write side validates against.
     *
     * @return list<Node>
     */
    public function tree(string $name): array
    {
        if (! isset($this->trees[$name])) {
            throw ScreenException::unknown($name);
        }

        if (! isset($this->built[$name])) {
            $root = $this->trees[$name];

            foreach ($this->patches[$name] ?? [] as $patch) {
                $root = Patcher::apply($name, $root, $patch);
            }

            $this->built[$name] = $root;
        }

        return $this->built[$name];
    }

    /**
     * What one administrator gets to see: nodes without their permission cut out, and every
     * `trans::` string turned into words in their language.
     *
     * @param  (callable(string): bool)|null  $can  Without it every node with a `can` is cut out.
     * @param  (callable(string): string)|null  $translate  Without it the keys stay as they are.
     * @return list<Node>
     */
    public function render(string $name, ?callable $can = null, ?callable $translate = null): array
    {
        $root = Tree::filter($this->tree($name), $can ?? static fn (string $permission): bool => false);

        if ($translate !== null) {
            /** @var list<Node> $root */
            $root = Tree::translate($root, $translate);
        }

        return $root;
    }

    /**
     * The field nodes of the whole screen, in document order.
     *
     * @return list<Node>
     */
    public function fields(string $name): array
    {
        return Tree::fields($this->tree($name));
    }

    /**
     * @return array<int|string, mixed>
     */
    private function read(string $path): array
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        $decoded = $contents === false ? null : json_decode($contents, true);

        if (! is_array($decoded)) {
            throw ScreenException::unreadable($path);
        }

        return $decoded;
    }
}
