<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Blocks\BlockComponents;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Regions;

/**
 * A block type under a new slug, and everything that names it brought along.
 *
 * A slug is not a label: content keeps it in every node (`type`), other types keep it in their
 * `allow` and `allowed_in`, the type's own template marks its root with it (`data-wx-block`, which
 * is what its script is bound by) and its styles are prefixed with it (`.b-{slug}`). Renaming the
 * row alone left every page holding a type nobody had any more — "There is no published block
 * type" where the block stood.
 *
 * What is rewritten, in one transaction: the content of every entity, what the site shows and the
 * draft, regions included; the `allow` and `allowed_in` of the other types; and the type's own
 * marker and prefix, as a new version — published at once when the old one was what the site
 * printed, so the script keeps finding its block. What is refused: a type other templates call
 * by tag (their templates would need new versions, and a version is somebody's work to publish)
 * and a slug a module declared (the module calls it by that name).
 *
 * Not rewritten: the history of entities and of other types. The old slug is kept on the row
 * instead (`former_slugs`), and restoring a version from before the rename follows it to the
 * new one ({@see self::forward()}).
 */
final class Renamer
{
    public function __construct(
        private readonly Usage $usage,
        private readonly Graph $graph,
        private readonly BlockComponents $components,
        private readonly Container $container,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * Why the type may not be renamed, or null when it may.
     */
    public function refusal(Block $block): ?string
    {
        $callers = $this->graph->usedBy($block->slug);

        if ($callers !== []) {
            return (string) __('webx-blocks::page.rename-called', [
                'types' => implode(', ', array_map(static fn (array $parent): string => $parent['title'].' ('.$parent['slug'].')', $callers)),
            ]);
        }

        if ($this->components->has($block->slug)) {
            return (string) __('webx-blocks::page.rename-declared');
        }

        return null;
    }

    /**
     * @return array{entities: int, types: int, version: int|null} What was rewritten: entities, other types, and the new version of this one.
     *
     * @throws BlocksException when the rename is refused
     */
    public function rename(Block $block, string $to, string $source = BlockVersion::SOURCE_PANEL, ?int $authorId = null): array
    {
        $from = $block->slug;

        if ($to === $from) {
            return ['entities' => 0, 'types' => 0, 'version' => null];
        }

        $refusal = $this->refusal($block);

        if ($refusal !== null) {
            throw new BlocksException($refusal);
        }

        return $this->db->transaction(function () use ($block, $from, $to, $source, $authorId): array {
            $entities = $this->content($from, $to);
            $types = $this->others($block, $from, $to, $source, $authorId);

            $block->slug = $to;
            // The trail an old version of a page follows to the type it became (§ forward()).
            $block->former_slugs = array_values(array_unique([...array_diff($block->former_slugs ?? [], [$to]), $from]));
            $block->save();

            $version = $this->own($block, $from, $to, $source, $authorId);

            return ['entities' => $entities, 'types' => $types, 'version' => $version];
        });
    }

    /** The slug in every node of every entity's content, quietly: nothing is published by it. */
    private function content(string $from, string $to): int
    {
        $changed = 0;

        foreach ($this->usage->entities() as $entity) {
            $column = method_exists($entity, 'blocksColumn') ? (string) $entity->blocksColumn() : 'blocks';
            $dirty = false;
            $live = $entity->getAttribute($column);

            if (is_array($live) && in_array($from, Content::types($live), true)) {
                $entity->setAttribute($column, self::retype($live, $from, $to));
                $dirty = true;
            }

            if (method_exists($entity, 'draftValues') && method_exists($entity, 'draftColumn')) {
                $draft = $entity->draftValues();

                if (is_array($draft[$column] ?? null) && in_array($from, Content::types($draft[$column]), true)) {
                    $draft[$column] = self::retype($draft[$column], $from, $to);
                    $entity->setAttribute((string) $entity->draftColumn(), $draft);
                    $dirty = true;
                }
            }

            if ($dirty) {
                $this->save($entity);
                $changed++;
            }
        }

        return $changed;
    }

    private function save(Model $entity): void
    {
        $entity->saveQuietly();

        if ($entity instanceof Region) {
            $this->container->make(Regions::class)->forget($entity->name);
        }
    }

    /**
     * The other types that name this one: `allow` and `allowed_in` on their rows, and the
     * `props.allow` of a container field in their schema — a version of theirs, rewritten the way
     * this type's own marker is ({@see self::rewrite()}). Left as it was, a container whose field
     * still named the old slug refused the renamed block on the next save of every page.
     */
    private function others(Block $block, string $from, string $to, string $source, ?int $authorId): int
    {
        $changed = 0;
        $schema = static fn (array $content): ?array => self::schemaRenamed($content, $from, $to);

        foreach (Block::query()->whereKeyNot($block->getKey())->with(['draftVersion', 'publishedVersion'])->get() as $other) {
            $dirty = false;

            foreach (['allow', 'allowed_in'] as $list) {
                $slugs = $other->getAttribute($list);

                if (is_array($slugs) && in_array($from, $slugs, true)) {
                    $other->setAttribute($list, array_values(array_unique(array_map(static fn (string $slug): string => $slug === $from ? $to : $slug, $slugs))));
                    $dirty = true;
                }
            }

            if ($dirty) {
                $other->save();
            }

            $rewrote = $this->rewrite($other, $schema, $source, $authorId, "Allows {$to}, renamed from {$from}") !== null;

            if ($dirty || $rewrote) {
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * The type's own marker and prefix. The published version is rewritten into a new version and
     * published straight away — the same template with one word changed, which the site would
     * otherwise print with a marker its script no longer answers to. A draft being worked on is
     * rewritten into the next version and stays a draft.
     */
    private function own(Block $block, string $from, string $to, string $source, ?int $authorId): ?int
    {
        return $this->rewrite($block, static function (array $content) use ($from, $to): ?array {
            // A container that takes itself names its own slug in its schema, too.
            $schema = self::schemaRenamed($content, $from, $to);

            if ($schema === null && ! self::mentions($content, $from)) {
                return null;
            }

            return self::renamed($schema ?? $content, $from, $to);
        }, $source, $authorId, "Renamed from {$from}");
    }

    /**
     * A version's content changed by `$change` (null when there is nothing to change): the published
     * one into a new version published straight away, the draft into the next version, still a
     * draft. Straight to the pointer rather than through `publish()`: the content the site already
     * prints with one word changed has nothing new to check, and a sample that fails today must not
     * be what stops a rename.
     *
     * @param  callable(array<string, mixed>): ?array<string, mixed>  $change
     * @return int|null The last version written, or null when none was.
     */
    private function rewrite(Block $block, callable $change, string $source, ?int $authorId, string $comment): ?int
    {
        $block->loadMissing(['draftVersion', 'publishedVersion']);
        $published = $block->publishedVersion;
        $draft = $block->draftVersion;
        $last = null;

        $live = $published instanceof BlockVersion ? $change($published->content()) : null;

        if ($published instanceof BlockVersion && $live !== null) {
            $version = $block->saveVersion($live, $source, $authorId, $comment);

            $block->published_version_id = $version->id;
            $block->draft_version_id = $draft instanceof BlockVersion ? $draft->id : null;
            $block->save();
            $block->unsetRelation('draftVersion');
            $block->unsetRelation('publishedVersion');
            $last = $version->number;
        }

        $next = $draft instanceof BlockVersion ? $change($draft->content()) : null;

        if ($next !== null) {
            $last = $block->saveVersion($next, $source, $authorId, $comment)->number;
        }

        return $last;
    }

    /**
     * Whether a version's own files name the slug: the marker on its root, or the prefix of a class.
     *
     * @param  array<string, mixed>  $content
     */
    private static function mentions(array $content, string $from): bool
    {
        $template = (string) ($content['template'] ?? '');
        $styles = (string) ($content['styles'] ?? '');

        return preg_match('/data-wx-block\s*=\s*["\']'.preg_quote($from, '/').'["\']/', $template) === 1
            || str_contains($template, 'b-'.$from)
            || str_contains($styles, 'b-'.$from);
    }

    /**
     * A schema whose container fields take `$from` taking `$to` instead; null when none did.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>|null
     */
    private static function schemaRenamed(array $content, string $from, string $to): ?array
    {
        $changed = false;
        $schema = self::allowRenamed(is_array($content['schema'] ?? null) ? $content['schema'] : [], $from, $to, $changed);

        if (! $changed) {
            return null;
        }

        $content['schema'] = $schema;

        return array_intersect_key($content, array_flip(BlockVersion::CONTENT));
    }

    /**
     * @param  array<array-key, mixed>  $nodes
     * @return array<array-key, mixed>
     */
    private static function allowRenamed(array $nodes, string $from, string $to, bool &$changed): array
    {
        foreach ($nodes as $index => $node) {
            if (! is_array($node)) {
                continue;
            }

            $allow = $node['props']['allow'] ?? null;

            if (is_array($allow) && in_array($from, $allow, true)) {
                $node['props']['allow'] = array_values(array_unique(array_map(static fn (mixed $slug): mixed => $slug === $from ? $to : $slug, $allow)));
                $changed = true;
            }

            if (is_array($node['children'] ?? null)) {
                $node['children'] = self::allowRenamed($node['children'], $from, $to, $changed);
            }

            $nodes[$index] = $node;
        }

        return $nodes;
    }

    /**
     * Content written in the same breath as a rename — the editor's form, an agent's `content`
     * beside `rename_to` — carried over to the new slug: its marker, prefix and own `allow`.
     * Saved as it came, it was the old template written as the draft after the rename's version,
     * and publishing that draft lost the block its marker and styles. Only the keys that came.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function carry(array $content, string $from, string $to): array
    {
        if (is_array($content['schema'] ?? null)) {
            $changed = false;
            $content['schema'] = self::allowRenamed($content['schema'], $from, $to, $changed);
        }

        $quoted = preg_quote($from, '/');
        $prefix = '/(?<![A-Za-z0-9_-])b-'.$quoted.'(?![a-z0-9])/';

        if (is_string($content['template'] ?? null)) {
            $template = (string) preg_replace('/(data-wx-block\s*=\s*["\'])'.$quoted.'(["\'])/', '${1}'.$to.'${2}', $content['template']);
            $content['template'] = (string) preg_replace($prefix, 'b-'.$to, $template);
        }

        if (is_string($content['styles'] ?? null)) {
            $content['styles'] = (string) preg_replace($prefix, 'b-'.$to, $content['styles']);
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private static function renamed(array $content, string $from, string $to): array
    {
        $content['template'] = (string) ($content['template'] ?? '');
        $content['styles'] = (string) ($content['styles'] ?? '');

        return array_intersect_key(self::carry($content, $from, $to), array_flip(BlockVersion::CONTENT));
    }

    /**
     * A tree from before a rename, its old slugs swapped for the types they became. A rename
     * rewrites what entities hold now, never their history: a version restored from before it
     * brought back a slug nobody had, publishing let it through, and the block vanished from the
     * site without a word. A slug that names a type today is left alone even if another type
     * once had it — what exists wins.
     *
     * @param  array<array-key, mixed>  $tree
     * @return array{0: array<array-key, mixed>, 1: array<string, string>} The tree, and old slug → new.
     */
    public static function forward(array $tree): array
    {
        $types = Content::types($tree);

        if ($types === []) {
            return [$tree, []];
        }

        /** @var array<string, mixed> $trails */
        $trails = Block::query()->pluck('former_slugs', 'slug')->all();
        $map = [];

        foreach ($types as $slug) {
            if (array_key_exists($slug, $trails)) {
                continue;
            }

            foreach ($trails as $current => $former) {
                $former = is_string($former) ? json_decode($former, true) : $former;

                if (is_array($former) && in_array($slug, $former, true)) {
                    $map[$slug] = (string) $current;

                    break;
                }
            }
        }

        foreach ($map as $from => $to) {
            $tree = self::retype($tree, $from, $to);
        }

        return [$tree, $map];
    }

    /**
     * @param  array<array-key, mixed>  $tree
     * @return array<array-key, mixed>
     */
    private static function retype(array $tree, string $from, string $to): array
    {
        foreach ($tree as $index => $node) {
            if (! Content::isNode($node)) {
                continue;
            }

            if ($node['type'] === $from) {
                $node['type'] = $to;
            }

            if (is_array($node['values'] ?? null)) {
                foreach ($node['values'] as $field => $value) {
                    if (Content::isNodeList($value)) {
                        $node['values'][$field] = self::retype($value, $from, $to);
                    }
                }
            }

            $tree[$index] = $node;
        }

        return $tree;
    }
}
