<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

use WebxUi\Compositions\Exceptions\CompositionException;

/**
 * What a composition file may say (COMPOSITIONS §2, the schema in resources/schema): a base — a
 * `root` or an `extends` — or a patch, and nodes with exactly one kind each. What it refuses is a
 * file that cannot be read as meant, and that stops the compile; a patch that names a node which
 * is not there is the patcher's finding, not this.
 */
final class Reader
{
    public const string ID = '/^[a-z0-9-]+(\.[a-z0-9-]+)*$/';

    public const string VARIABLE = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    private const array OPERATIONS = ['add', 'move', 'replace', 'attrs', 'props', 'remove'];

    private const array POSITIONS = ['before', 'after', 'first', 'last'];

    /** What a base says about the composition besides its tree (§2.2): the leaves' variables, the view it stands in for. */
    private const array CONTRACT = ['vars', 'dispatcher'];

    /** The keys each kind of node takes besides `id`, `$comment` and `protected`. */
    private const array KEYS = [
        'tag' => ['tag', 'attrs', 'children'],
        'group' => ['children'],
        'view' => ['view', 'with'],
        'component' => ['component', 'props', 'attrs', 'children'],
        'region' => ['region', 'fallback'],
        'slot' => ['slot', 'fallback'],
        'stack' => ['stack'],
        'directive' => ['directive', 'args'],
        'html' => ['html'],
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function check(array $data, string $file, bool $default): void
    {
        $allowed = ['$schema', '$comment', 'root', 'extends', ...self::CONTRACT, ...self::OPERATIONS];

        foreach (array_keys($data) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw $this->refuse($file, "unknown key \"{$key}\"");
            }
        }

        if (isset($data['root']) && isset($data['extends'])) {
            throw $this->refuse($file, 'a base has a root or extends, not both');
        }

        if ((isset($data['root']) || isset($data['extends'])) && ! $default) {
            throw $this->refuse($file, 'only default.json can be a base');
        }

        if (isset($data['root']) && (array_intersect(array_keys($data), self::OPERATIONS) !== [])) {
            throw $this->refuse($file, 'a base with a root has no operations: write the tree as it is');
        }

        if (isset($data['extends']) && (! is_string($data['extends']) || $data['extends'] === '')) {
            throw $this->refuse($file, 'extends names a composition');
        }

        $this->contract($data, $file);

        if (isset($data['root'])) {
            $this->node($data['root'], $file, 'root', base: true);
        }

        $this->operations($data, $file);
    }

    /**
     * Whether the file is a base.
     *
     * @param  array<string, mixed>  $data
     */
    public function isBase(array $data): bool
    {
        return isset($data['root']) || isset($data['extends']);
    }

    /**
     * A node of the file as a node of the tree, its children with it.
     *
     * @param  array<string, mixed>  $data
     */
    public function build(array $data, string $origin, bool $base, ?string $id = null): Node
    {
        $kind = $this->kind($data);
        $value = $kind === 'group' ? '' : (string) $data[$kind];

        $fallback = [];
        $regionFallback = null;

        if ($kind === 'slot') {
            foreach ((array) ($data['fallback'] ?? []) as $item) {
                $fallback[] = is_string($item)
                    ? new Node(($id ?? (string) $data['id']).'.'.$this->kebab($item), 'directive', $item, origin: $origin, base: $base)
                    : $this->build((array) $item, $origin, $base);
            }
        } elseif ($kind === 'region') {
            $regionFallback = isset($data['fallback']) ? (string) $data['fallback'] : null;
        }

        /** @var array<string, string|bool> $attrs */
        $attrs = isset($data['attrs']) ? array_filter((array) $data['attrs'], static fn (mixed $value): bool => $value !== null && $value !== false) : [];

        $node = new Node(
            id: $id ?? (string) $data['id'],
            kind: $kind,
            value: $value,
            attrs: $attrs,
            props: (array) ($data['props'] ?? []),
            with: (array) ($data['with'] ?? []),
            fallback: $fallback,
            regionFallback: $regionFallback,
            args: isset($data['args']) ? (string) $data['args'] : null,
            protected: $base && ($data['protected'] ?? false) === true,
            origin: $origin,
            base: $base,
        );

        foreach ((array) ($data['children'] ?? []) as $child) {
            $node->insert($this->build((array) $child, $origin, $base));
        }

        return $node;
    }

    /**
     * `vars` and `dispatcher` of a base (§2.2): the variables every leaf gets from the place the
     * composition is included, and the view that includes it — the one a site copies.
     *
     * @param  array<string, mixed>  $data
     */
    private function contract(array $data, string $file): void
    {
        foreach (self::CONTRACT as $key) {
            if (array_key_exists($key, $data) && ! $this->isBase($data)) {
                throw $this->refuse($file, "{$key} is said by the base of a composition, not a patch");
            }
        }

        if (array_key_exists('vars', $data)) {
            $vars = $data['vars'];

            if (! is_array($vars) || ! array_is_list($vars)) {
                throw $this->refuse($file, 'vars lists the names of variables');
            }

            foreach ($vars as $var) {
                if (! is_string($var) || preg_match(self::VARIABLE, $var) !== 1) {
                    throw $this->refuse($file, 'vars lists the names of variables without $: "article", not '.json_encode($var));
                }
            }
        }

        if (array_key_exists('dispatcher', $data) && (! is_string($data['dispatcher']) || $data['dispatcher'] === '')) {
            throw $this->refuse($file, 'dispatcher names a view: "webx-blog::article"');
        }
    }

    /** @param array<string, mixed> $data */
    private function operations(array $data, string $file): void
    {
        foreach (['add', 'move', 'remove'] as $list) {
            if (isset($data[$list]) && ! array_is_list((array) $data[$list])) {
                throw $this->refuse($file, "{$list} is a list");
            }
        }

        foreach ((array) ($data['add'] ?? []) as $index => $item) {
            if (! is_array($item) || ! isset($item['to']) || ! is_string($item['to'])) {
                throw $this->refuse($file, "add[{$index}] needs \"to\": the node it goes into");
            }

            $this->position($item, $file, "add[{$index}]");
            $this->node(array_diff_key($item, array_flip(['to', ...self::POSITIONS])), $file, "add[{$index}]", base: false);
        }

        foreach ((array) ($data['move'] ?? []) as $index => $item) {
            if (! is_array($item) || ! isset($item['id']) || ! is_string($item['id'])) {
                throw $this->refuse($file, "move[{$index}] needs \"id\"");
            }

            $extra = array_diff(array_keys($item), ['id', 'to', '$comment', ...self::POSITIONS]);

            if ($extra !== []) {
                throw $this->refuse($file, "move[{$index}]: unknown key \"".reset($extra).'"');
            }

            $this->position($item, $file, "move[{$index}]");
        }

        foreach (['replace', 'attrs', 'props'] as $map) {
            if (isset($data[$map]) && (! is_array($data[$map]) || array_is_list($data[$map]) && $data[$map] !== [])) {
                throw $this->refuse($file, "{$map} is an object by id");
            }
        }

        foreach ((array) ($data['replace'] ?? []) as $id => $node) {
            if (! is_array($node)) {
                throw $this->refuse($file, "replace.{$id} is a node");
            }

            if (isset($node['id']) && $node['id'] !== $id) {
                throw $this->refuse($file, "replace.{$id} keeps its id: replace another id with remove and add");
            }

            $this->node(['id' => (string) $id, ...$node], $file, "replace.{$id}", base: false);
        }

        foreach (['attrs', 'props'] as $map) {
            foreach ((array) ($data[$map] ?? []) as $id => $values) {
                if (! is_array($values) || array_is_list($values) && $values !== []) {
                    throw $this->refuse($file, "{$map}.{$id} is an object");
                }

                foreach ($values as $name => $value) {
                    $map === 'attrs'
                        ? $this->attribute((string) $name, $value, $file, "attrs.{$id}")
                        : $this->literal($value, $file, "props.{$id}.{$name}");
                }
            }
        }

        foreach ((array) ($data['remove'] ?? []) as $id) {
            if (! is_string($id)) {
                throw $this->refuse($file, 'remove lists ids');
            }
        }
    }

    /** @param array<array-key, mixed> $item */
    private function position(array $item, string $file, string $where): void
    {
        $given = array_values(array_intersect(array_keys($item), self::POSITIONS));

        if (count($given) > 1) {
            throw $this->refuse($file, "{$where}: one of before, after, first, last");
        }

        if (in_array('first', $given, true) && $item['first'] !== true || in_array('last', $given, true) && $item['last'] !== true) {
            throw $this->refuse($file, "{$where}: first and last are true");
        }

        foreach (['before', 'after'] as $key) {
            if (isset($item[$key]) && ! is_string($item[$key])) {
                throw $this->refuse($file, "{$where}: {$key} names a node");
            }
        }
    }

    private function node(mixed $data, string $file, string $where, bool $base): void
    {
        if (! is_array($data) || array_is_list($data)) {
            throw $this->refuse($file, "{$where} is a node");
        }

        if (! isset($data['id']) || ! is_string($data['id']) || preg_match(self::ID, $data['id']) !== 1) {
            throw $this->refuse($file, "{$where}: a node needs an id of a-z, 0-9, - and dots");
        }

        $kind = $this->kind($data, $file, $where);
        $allowed = ['id', '$comment', ...self::KEYS[$kind]];

        if ($base) {
            $allowed[] = 'protected';
        }

        foreach (array_keys($data) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw $this->refuse($file, "{$where} ({$data['id']}): \"{$key}\" is not a key of a {$kind} node".($key === 'protected' ? ' — only a base protects' : ''));
            }
        }

        if ($kind !== 'group' && (! is_string($data[$kind]) || $data[$kind] === '')) {
            throw $this->refuse($file, "{$where} ({$data['id']}): {$kind} is a name");
        }

        foreach ((array) ($data['attrs'] ?? []) as $name => $value) {
            $this->attribute((string) $name, $value, $file, "{$where} ({$data['id']})");
        }

        foreach ([...(array) ($data['props'] ?? []), ...(array) ($data['with'] ?? [])] as $value) {
            $this->literal($value, $file, "{$where} ({$data['id']})");
        }

        if (isset($data['fallback'])) {
            if ($kind === 'region' && ! is_string($data['fallback'])) {
                throw $this->refuse($file, "{$where} ({$data['id']}): the fallback of a region is a view");
            }

            if ($kind === 'slot') {
                foreach ((array) $data['fallback'] as $index => $item) {
                    is_string($item) ? null : $this->node($item, $file, "{$where}.fallback[{$index}]", $base);
                }
            }
        }

        if (isset($data['children'])) {
            if (! is_array($data['children']) || ! array_is_list($data['children'])) {
                throw $this->refuse($file, "{$where} ({$data['id']}): children is a list");
            }

            foreach ($data['children'] as $index => $child) {
                $this->node($child, $file, "{$where}.children[{$index}]", $base);
            }
        }
    }

    /** @param array<array-key, mixed> $data */
    private function kind(array $data, string $file = '', string $where = ''): string
    {
        $kinds = array_values(array_intersect(Node::KINDS, array_keys($data)));

        if (array_key_exists('vue', $data)) {
            throw $this->refuse($file, "{$where}: vue nodes are not here yet");
        }

        if (count($kinds) > 1) {
            throw $this->refuse($file, "{$where}: a node is one kind, not ".implode(' and ', $kinds));
        }

        if ($kinds === [] && ! isset($data['children'])) {
            throw $this->refuse($file, "{$where}: a node without a kind is a group and needs children");
        }

        return $kinds[0] ?? 'group';
    }

    private function attribute(string $name, mixed $value, string $file, string $where): void
    {
        if (preg_match('/^[A-Za-z_:@][A-Za-z0-9_:.@-]*$/', $name) !== 1 || str_starts_with($name, ':')) {
            throw $this->refuse($file, "{$where}: \"{$name}\" is not an attribute — values are literals, not expressions");
        }

        $remove = $name === 'class' && is_array($value) && array_keys($value) === ['remove'] && is_string($value['remove']);

        if (! is_string($value) && ! is_bool($value) && $value !== null && ! $remove) {
            throw $this->refuse($file, "{$where}: {$name} is a string, true, false or null");
        }
    }

    private function literal(mixed $value, string $file, string $where): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->literal($item, $file, $where);
            }

            return;
        }

        if (! is_scalar($value) && $value !== null) {
            throw $this->refuse($file, "{$where}: a literal");
        }
    }

    private function kebab(string $directive): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $directive));
    }

    private function refuse(string $file, string $message): CompositionException
    {
        return new CompositionException("{$file}: {$message}.");
    }
}
