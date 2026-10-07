<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ChecksNewValues;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Blocks\Rendering\Values;
use WebxUi\Localization\Locales;

/**
 * What arrives for a block, checked and turned into what it keeps.
 *
 * The mirror of {@see Values}: there a stored value is handed to its field type to be read,
 * here an incoming one is handed to the same type to be checked and kept. A described screen has
 * had this since it was written ({@see ScreenValues::validate()}) — it looks the node's type up,
 * runs its `rules()` and casts the value with `store()` — and a block's values are screen nodes
 * too, so a value put in a block is held to the same line as a value put on a screen. Without it
 * a number past its `max`, an option nobody offered or a `javascript:` link went into a page from
 * an agent, an autosave or a publication, and the site printed it.
 *
 * Every door content comes in by passes through here: the forms that save an entity
 * ({@see HasBlocks::storeBlocks()}), the agent's tools, a region's save, and a publication
 * ({@see HasBlocks}, on `publishing`) for a draft written before these checks existed. What is
 * checked besides the values is where blocks stand: a container's `allow` and `max`, a type's
 * `allowed_in` and `max_per_entity` — what the picker already holds the panel to, now held on the
 * server for everybody.
 *
 * Three things pass through unchecked, and all three on purpose: a value whose key the schema does
 * not name — a field removed after the page was written, which the stray-values clean-up is for —
 * a value of a type nobody registered, and a node of a block type that does not exist, which every
 * writer refuses before it gets here.
 *
 * @phpstan-type Problem array{key: string|null, type: string, field: string|null, message: string}
 */
final class ContentValues
{
    /** @var array<string, array<string, array<string, mixed>>> slug → the nodes of its schema, by id */
    private array $fields = [];

    /** @var array<string, BlockType|null> */
    private array $blockTypes = [];

    /**
     * What the entity held before this write, key → values, for the checks only a new value has
     * to pass ({@see ChecksNewValues}); null while every value counts as new.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $held = null;

    public function __construct(
        private readonly BlockTypes $blocks,
        private readonly FieldTypes $types,
        private readonly Locales $locales,
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * A tree on its way into an entity: checked, then every value cast by the type its schema
     * names.
     *
     * @param  iterable<array-key, mixed>|null  $tree
     * @param  string  $attribute  What the errors are keyed under — the entity's blocks column.
     * @param  string  $root  What the top level is called in `allowed_in`: `root`, or `region:<name>`.
     * @param  iterable<array-key, mixed>|null  $before  What the entity held until now: a value it already had is not new.
     * @return list<mixed>
     *
     * @throws ValidationException naming the block's key and the field
     */
    public function store(?iterable $tree, string $attribute = 'blocks', string $root = 'root', ?iterable $before = null): array
    {
        $problems = $this->problems($tree, $root, $before);

        if ($problems !== []) {
            throw ValidationException::withMessages(self::messages($problems, $attribute));
        }

        return $this->cast($tree);
    }

    /**
     * The checks alone, for a tree that is already stored and is about to be published.
     *
     * @param  iterable<array-key, mixed>|null  $tree
     *
     * @throws ValidationException
     */
    public function check(?iterable $tree, string $attribute = 'blocks', string $root = 'root'): void
    {
        // Nothing in a stored tree is new: a picture deleted from the library since is the
        // editor's to take out, not a reason to refuse the publication.
        $problems = $this->problems($tree, $root, $tree);

        if ($problems !== []) {
            throw ValidationException::withMessages(self::messages($problems, $attribute));
        }
    }

    /**
     * What is wrong with a tree, block by block — empty when nothing is.
     *
     * @param  iterable<array-key, mixed>|null  $tree
     * @param  iterable<array-key, mixed>|null  $before  What the entity held until now; null when every value is new.
     * @return list<Problem>
     */
    public function problems(?iterable $tree, string $root = 'root', ?iterable $before = null): array
    {
        $problems = [];
        $counts = [];
        $this->held = $before === null ? null : self::held(self::nodes($before));

        try {
            $this->checkList(self::nodes($tree), null, null, $root, $problems, $counts);
        } finally {
            $this->held = null;
        }

        foreach ($counts as $slug => $count) {
            $type = $this->blockType($slug);

            if ($type !== null && $type->maxPerEntity !== null && $count > $type->maxPerEntity) {
                $problems[] = [
                    'key' => null,
                    'type' => $slug,
                    'field' => null,
                    'message' => (string) __('webx-blocks::validation.max-per-entity', ['type' => $type->title, 'max' => $type->maxPerEntity]),
                ];
            }
        }

        return $problems;
    }

    /**
     * One problem as a sentence an agent can act on: which block, which field, what is wrong.
     *
     * @param  Problem  $problem
     */
    public static function describe(array $problem): string
    {
        $where = $problem['type'].($problem['key'] !== null ? " [{$problem['key']}]" : '');

        if ($problem['field'] !== null) {
            $where .= ", field [{$problem['field']}]";
        }

        return "{$where}: {$problem['message']}";
    }

    /**
     * The problems as a validation error bag, keyed `blocks.<key>.<field>` — the address the
     * panel puts a message under, in the block's form and under the field.
     *
     * @param  list<Problem>  $problems
     * @return array<string, list<string>>
     */
    public static function messages(array $problems, string $attribute = 'blocks'): array
    {
        $messages = [];

        foreach ($problems as $problem) {
            $path = implode('.', array_filter([$attribute, $problem['key'] ?? $problem['type'], $problem['field']], static fn (?string $part): bool => $part !== null && $part !== ''));
            $messages[$path][] = $problem['message'];
        }

        return array_map(static fn (array $lines): array => array_values(array_unique($lines)), $messages);
    }

    /**
     * @param  list<array<string, mixed>>  $list
     * @param  array<string, mixed>|null  $field  The `wx-blocks` node the list is the value of.
     * @param  list<Problem>  $problems
     * @param  array<string, int>  $counts
     */
    private function checkList(array $list, ?BlockType $parent, ?array $field, string $root, array &$problems, array &$counts): void
    {
        foreach ($list as $node) {
            $slug = (string) $node['type'];
            $key = is_string($node['key'] ?? null) ? $node['key'] : null;
            $counts[$slug] = ($counts[$slug] ?? 0) + 1;
            $type = $this->blockType($slug);

            // Refused at every door before it gets here; with no schema there is nothing to check.
            if ($type === null) {
                continue;
            }

            $placement = $this->placement($type, $parent, $field, $root);

            if ($placement !== null) {
                $problems[] = ['key' => $key, 'type' => $slug, 'field' => null, 'message' => $placement];
            }

            $fields = $this->fields($slug);

            foreach (is_array($node['values'] ?? null) ? $node['values'] : [] as $name => $value) {
                $name = (string) $name;
                $schema = $fields[$name] ?? null;

                if ($schema === null) {
                    continue;
                }

                if (($schema['type'] ?? null) === 'wx-blocks') {
                    $this->checkNested($type, $schema, $key, $name, $value, $root, $problems, $counts);

                    continue;
                }

                if (Content::isNodeList($value)) {
                    $problems[] = ['key' => $key, 'type' => $slug, 'field' => $name, 'message' => (string) __('webx-blocks::validation.holds-no-blocks')];

                    continue;
                }

                $new = $this->held === null || $key === null || ! array_key_exists($name, $this->held[$key] ?? []) || $this->held[$key][$name] != $value;

                foreach ($this->valueProblems($schema, $value, $new) as $message) {
                    $problems[] = ['key' => $key, 'type' => $slug, 'field' => $name, 'message' => $message];
                }
            }
        }
    }

    /**
     * A container's field: blocks, as many as it takes, of the types it takes.
     *
     * @param  array<string, mixed>  $schema
     * @param  list<Problem>  $problems
     * @param  array<string, int>  $counts
     */
    private function checkNested(BlockType $type, array $schema, ?string $key, string $name, mixed $value, string $root, array &$problems, array &$counts): void
    {
        if ($value === null || $value === []) {
            return;
        }

        if (! Content::isNodeList($value)) {
            $problems[] = ['key' => $key, 'type' => $type->slug, 'field' => $name, 'message' => (string) __('webx-blocks::validation.not-blocks')];

            return;
        }

        $max = $schema['props']['max'] ?? null;

        if (is_numeric($max) && count($value) > (int) $max) {
            $problems[] = ['key' => $key, 'type' => $type->slug, 'field' => $name, 'message' => (string) __('webx-blocks::validation.too-many', ['max' => (int) $max])];
        }

        $this->checkList(array_values($value), $type, $schema, $root, $problems, $counts);
    }

    /**
     * Why a block may not stand where it is, or null when it may — the rule the picker and "Move
     * to" go by on the client: the field's `allow` when it has one, the type's `allow` otherwise,
     * and the child's own `allowed_in`.
     *
     * @param  array<string, mixed>|null  $field
     */
    private function placement(BlockType $type, ?BlockType $parent, ?array $field, string $root): ?string
    {
        if ($parent === null) {
            return $type->allowedIn !== null && ! in_array($root, $type->allowedIn, true)
                ? (string) __('webx-blocks::validation.not-at-top', ['type' => $type->title])
                : null;
        }

        $allow = self::allowOf($parent, $field);

        if ($allow !== null && ! in_array($type->slug, $allow, true)) {
            return (string) __('webx-blocks::validation.not-inside', ['type' => $type->title, 'parent' => $parent->title]);
        }

        if ($type->allowedIn !== null && ! in_array($parent->slug, $type->allowedIn, true)) {
            return (string) __('webx-blocks::validation.not-inside', ['type' => $type->title, 'parent' => $parent->title]);
        }

        return null;
    }

    /**
     * What a container's field takes: its own `props.allow`, or the type's `allow` when the field
     * names none — null when neither does, which takes any block. One answer for the server, the
     * picker and the settings screen.
     *
     * @param  array<string, mixed>|null  $field
     * @return list<string>|null
     */
    public static function allowOf(BlockType $parent, ?array $field): ?array
    {
        $own = $field['props']['allow'] ?? null;

        if (is_array($own)) {
            return array_values(array_map('strval', $own));
        }

        return $parent->allow;
    }

    /**
     * The field type's own rules, on one value — on every language of it when it holds a map.
     *
     * @param  array<string, mixed>  $schema
     * @param  bool  $new  Whether the value was not there before this write.
     * @return list<string>
     */
    private function valueProblems(array $schema, mixed $value, bool $new = true): array
    {
        $type = $this->types->get((string) ($schema['type'] ?? ''));

        if (! $type instanceof FieldType) {
            return [];
        }

        $rules = $type->rules($schema);
        $label = $this->label($schema);

        $validator = $this->locales->isMap($value, ($schema['localized'] ?? false) === true)
            ? $this->validator->make(['value' => $value], ['value' => ['array'], 'value.*' => $rules], [], ['value' => $label, 'value.*' => $label])
            : $this->validator->make(['value' => $value], ['value' => $rules], [], ['value' => $label]);

        if ($validator->fails()) {
            return array_values(array_unique($validator->errors()->all()));
        }

        if (! $new || ! $type instanceof ChecksNewValues || $value === null) {
            return [];
        }

        $problems = [];

        foreach ($this->locales->isMap($value, ($schema['localized'] ?? false) === true) ? $value : [$value] as $one) {
            $problems = [...$problems, ...$type->newValueProblems($one, $schema)];
        }

        return array_values(array_unique($problems));
    }

    /**
     * Every node of a tree by key, with its values.
     *
     * @param  list<array<string, mixed>>  $tree
     * @return array<string, array<string, mixed>>
     */
    private static function held(array $tree): array
    {
        $held = [];

        Content::walk($tree, static function (array $node) use (&$held): void {
            if (is_string($node['key'] ?? null)) {
                $held[$node['key']] = is_array($node['values'] ?? null) ? $node['values'] : [];
            }
        });

        return $held;
    }

    /**
     * @param  iterable<array-key, mixed>|null  $tree
     * @return list<mixed>
     */
    private function cast(?iterable $tree): array
    {
        $stored = [];

        foreach ($tree ?? [] as $node) {
            $stored[] = Content::isNode($node) ? $this->node($node) : $node;
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function node(array $node): array
    {
        $values = is_array($node['values'] ?? null) ? $node['values'] : [];

        if ($values === []) {
            return $node;
        }

        $fields = $this->fields((string) $node['type']);
        $kept = [];

        foreach ($values as $name => $value) {
            $kept[$name] = $this->value($fields[(string) $name] ?? null, $value);
        }

        // Laid back into the node rather than built from a list of keys. A node carries more
        // than its values — `key`, and `hidden` since §23 — and a writer that names the keys it
        // knows loses the next one somebody adds, silently: the write succeeds, the key is not
        // in the row, and nothing anywhere says so.
        $node['values'] = $kept;

        return $node;
    }

    /**
     * @param  array<string, mixed>|null  $field
     */
    private function value(?array $field, mixed $value): mixed
    {
        // A nested constructor, recognised by shape the way the rest of {@see Content} is: a
        // `wx-blocks` field dropped from the schema still holds blocks, and they are still
        // blocks. No field type owns them.
        if (Content::isNodeList($value)) {
            return $this->cast($value);
        }

        $type = $field === null ? null : $this->types->get((string) ($field['type'] ?? ''));

        if ($field === null || ! $type instanceof FieldType) {
            return $value;
        }

        // A language map is cast language by language, whatever the schema says now; anything
        // else is one value, also whatever the schema says. The flag is not trusted on its own:
        // a field switched to `localized` still holds the plain values written before, and
        // casting a list of tags as a map of languages emptied every tag in it.
        //
        // Never `?? $value`. A type is allowed to answer null — an editor emptied of everything
        // but its markup holds no document — and a fallback would put back exactly the value
        // that was refused.
        if ($this->locales->isMap($value, ($field['localized'] ?? false) === true)) {
            return array_map(static fn (mixed $one): mixed => $type->store($one, $field), $value);
        }

        return $type->store($value, $field);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function label(array $schema): string
    {
        $label = $schema['label'] ?? null;
        $translated = $label === null ? null : Tree::translate($label, static fn (string $key): string => (string) __($key));

        return is_string($translated) && $translated !== '' ? $translated : (string) ($schema['id'] ?? $schema['name'] ?? '');
    }

    /**
     * @param  iterable<array-key, mixed>|null  $tree
     * @return list<array<string, mixed>>
     */
    private static function nodes(?iterable $tree): array
    {
        $nodes = [];

        foreach ($tree ?? [] as $node) {
            if (Content::isNode($node)) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * The type a write is checked by: the published version — the schema the constructor drew
     * the block with and the site prints it with — or the draft of one never published, so that
     * an agent writing content with a type it has just made is held to it too.
     */
    private function blockType(string $slug): ?BlockType
    {
        if (! array_key_exists($slug, $this->blockTypes)) {
            $this->blockTypes[$slug] = $this->blocks->find($slug) ?? $this->blocks->draft($slug);
        }

        return $this->blockTypes[$slug];
    }

    /**
     * The fields of a block type, by id, kept for as long as this write lasts: a page holds
     * twenty blocks of half a dozen types, and the schema of one of them does not change
     * halfway through saving it.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fields(string $slug): array
    {
        if (! isset($this->fields[$slug])) {
            $type = $this->blockType($slug);

            $this->fields[$slug] = $type === null ? [] : Schema::valueFields($type->schema, $this->types);
        }

        return $this->fields[$slug];
    }
}
