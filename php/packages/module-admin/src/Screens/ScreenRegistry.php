<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;

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

    /** @var array<string, list<list<array<string, mixed>>|Closure(): list<array<string, mixed>>>> */
    private array $patches = [];

    /** @var array<string, list<Node>> */
    private array $built = [];

    /** @var array<string, true> Screens whose defaults were checked against the types. */
    private array $checked = [];

    /** @var array<string, list<array<string, mixed>|Closure(): array<string, mixed>>> */
    private array $rules = [];

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
        unset($this->built[$name], $this->checked[$name]);
    }

    /**
     * Lays a patch over a screen. The screen need not be registered yet — a project's provider
     * may boot before the module's — so the target is checked when the tree is first built.
     *
     * A closure is a patch that depends on what other providers register — a field type, a
     * source — and so cannot be written down at boot. It keeps its place among the patches but
     * is asked for its operations each time the tree is built, and a tree built before the
     * application has booted is not kept: the answer may still change.
     *
     * @param  string|list<array<string, mixed>>|(Closure(): list<array<string, mixed>>)  $patch  A path to a JSON file, the operations, or a closure that returns them.
     */
    public function extend(string $name, string|array|Closure $patch): void
    {
        if ($patch instanceof Closure) {
            $this->patches[$name][] = $patch;
            unset($this->built[$name], $this->checked[$name]);

            return;
        }

        $ops = is_string($patch) ? $this->read($patch) : $patch;
        $problems = ScreenValidator::patch($ops);

        if ($problems !== []) {
            throw ScreenException::invalid("The patch on [{$name}]", $problems);
        }

        /** @var list<array<string, mixed>> $ops */
        $this->patches[$name][] = $ops;
        unset($this->built[$name], $this->checked[$name]);
    }

    /**
     * The rules the module checks a screen's fields by on its own write path — what an order's
     * contacts are held to by `Orders`, not by the field's type. Keyed by field name, Laravel's
     * rules as they are; told so the panel can mark what is always required ({@see render()}).
     * Nothing is validated by them here: the module that wrote them does that.
     *
     * A closure is asked each time the screen is drawn, for rules that depend on settings.
     *
     * @param  array<string, mixed>|(Closure(): array<string, mixed>)  $rules
     */
    public function rules(string $name, array|Closure $rules): void
    {
        $this->rules[$name][] = $rules;
    }

    /**
     * Every rule a field of the screen answers to: its type's first, then the module's.
     *
     * @param  Node  $node
     * @return list<mixed>
     */
    public function fieldRules(string $name, array $node): array
    {
        $field = $node['name'] ?? null;

        if (! is_string($field)) {
            return [];
        }

        $container = Container::getInstance();
        $type = $container->bound(FieldTypes::class) ? $container->make(FieldTypes::class)->get((string) ($node['type'] ?? '')) : null;
        $rules = $type?->rules($node) ?? [];

        foreach ($this->rules[$name] ?? [] as $set) {
            $set = $set instanceof Closure ? $set() : $set;
            $own = $set[$field] ?? [];

            foreach (is_string($own) ? explode('|', $own) : (is_array($own) ? $own : [$own]) as $rule) {
                $rules[] = $rule;
            }
        }

        return array_values($rules);
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
     * The tree with every registered patch applied — the whole screen, nothing translated and
     * nothing cut out but the fields this site has nothing for ({@see Withdraws}). What the write
     * side validates against.
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

            $deferred = false;

            foreach ($this->patches[$name] ?? [] as $patch) {
                if ($patch instanceof Closure) {
                    $deferred = true;
                    $patch = $this->deferred($name, $patch);
                }

                $root = Patcher::apply($name, $root, $patch);
            }

            // Defaults are not judged before boot either ({@see checkDefaults()}), so nothing is lost.
            if ($deferred && ! $this->booted()) {
                return $this->withdraw($root);
            }

            $this->built[$name] = $root;
        }

        $this->checkDefaults($name, $this->built[$name]);

        // Not cached with the patches: what a node has to edit is registered by other modules'
        // providers, and a tree built before the last of them booted must not keep the answer.
        return $this->withdraw($this->built[$name]);
    }

    /**
     * What one administrator gets to see: nodes without their permission cut out, and every
     * `trans::` string turned into words in their language.
     *
     * A field its rules always require is drawn with `required: true` — the asterisk at its label
     * ({@see Required}). A description may say so itself for a field its project checks; it is
     * never taken away here.
     *
     * @param  (callable(string): bool)|null  $can  Without it every node with a `can` is cut out.
     * @param  (callable(string): string)|null  $translate  Without it the keys stay as they are.
     * @return list<Node>
     */
    public function render(string $name, ?callable $can = null, ?callable $translate = null): array
    {
        $root = $this->required($name, Tree::filter($this->tree($name), $can ?? static fn (string $permission): bool => false));

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
     * @param  list<Node>  $nodes
     * @return list<Node>
     */
    private function required(string $name, array $nodes): array
    {
        foreach ($nodes as $index => $node) {
            // A named node owns what is under it, as in {@see Tree::fields()}: the fields of a
            // repeater's row are its keys, not the screen's.
            if (isset($node['name']) && is_string($node['name']) && $node['name'] !== '') {
                if (($node['required'] ?? false) !== true && Required::among($this->fieldRules($name, $node))) {
                    $nodes[$index]['required'] = true;
                }

                continue;
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $nodes[$index]['children'] = $this->required($name, Tree::children($node));
            }
        }

        return $nodes;
    }

    /**
     * @param  list<Node>  $nodes
     * @return list<Node>
     */
    private function withdraw(array $nodes): array
    {
        // Asked for on use, never injected: building the field types builds their dependencies
        // (the library's addresses, the link sources), and a registry made early in boot would
        // fix them before the site — or a test — has put its own in place.
        $container = Container::getInstance();

        if (! $container->bound(FieldTypes::class)) {
            return $nodes;
        }

        $types = $container->make(FieldTypes::class);
        $kept = [];

        foreach ($nodes as $node) {
            $type = $types->get((string) ($node['type'] ?? ''));

            if ($type instanceof Withdraws && $type->withdrawn($node)) {
                continue;
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = $this->withdraw(Tree::children($node));
            }

            $kept[] = $node;
        }

        return $kept;
    }

    /**
     * A `default` the field's type would refuse is a broken description, thrown like any other
     * ({@see ScreenValidator::defaults()}). Judged once per built tree, and only after the
     * application has booted: before that a module's types may not be registered yet, and a
     * default of an unknown type would pass unchecked for good.
     *
     * @param  list<Node>  $root
     */
    private function checkDefaults(string $name, array $root): void
    {
        if (isset($this->checked[$name])) {
            return;
        }

        $container = Container::getInstance();

        if (! $container->bound(FieldTypes::class) || ! $container->bound(ValidationFactory::class)) {
            return;
        }

        if (! $this->booted()) {
            return;
        }

        $problems = ScreenValidator::defaults($root, $container->make(FieldTypes::class), $container->make(ValidationFactory::class));

        if ($problems !== []) {
            throw ScreenException::invalid("The screen [{$name}]", $problems);
        }

        $this->checked[$name] = true;
    }

    /**
     * The operations of a closure patch, checked like any other patch.
     *
     * @param  Closure(): list<array<string, mixed>>  $patch
     * @return list<array<string, mixed>>
     */
    private function deferred(string $name, Closure $patch): array
    {
        $ops = $patch();
        $problems = ScreenValidator::patch($ops);

        if ($problems !== []) {
            throw ScreenException::invalid("The patch on [{$name}]", $problems);
        }

        return $ops;
    }

    /** Every provider has booted: what they register is in place, and an answer may be kept. */
    private function booted(): bool
    {
        $container = Container::getInstance();

        return ! $container instanceof Application || $container->isBooted();
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
