<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Editing\EditedRecords;
use WebxUi\Admin\SiteBar\EntityLink;
use WebxUi\Admin\SiteBar\SiteBar;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\BlockLabel;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\ContentEdit;
use WebxUi\Blocks\ContentValues;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\StrayValues;
use WebxUi\Localization\Locales;

/**
 * The «Blocks» tab of the panel on the site (§8–§9 of WEBX_UI_SITE_BAR.md): the record's blocks
 * as a map, one block's fields edited into the record's draft, and the block drawn again for the
 * page to put in place of the old one.
 *
 * The same door the agent's `blocks_edit_content` goes through (§18.1 of the blocks spec): the
 * operations of {@see ContentEdit}, the revision the record's editor holds, the checks of
 * {@see ContentValues} and `saveDraft()`. Only `set` here — adding, moving and
 * removing blocks are the constructor's, in the panel (§1 of the bar's spec).
 */
final class SiteBarBlocks
{
    public function __construct(
        private readonly Container $container,
        private readonly SiteBar $bar,
        private readonly EditedRecords $records,
        private readonly BlockTypes $types,
        private readonly StrayValues $strays,
        private readonly Locales $locales,
    ) {}

    /**
     * Whether the tab is there: a record that holds blocks, and an administrator who may edit
     * it — its section's right — and read blocks. Writing goes into the draft the record's own
     * editor writes, so the section's right is the one that counts; `blocks.view` is what the
     * constructor in the panel asks of the same person.
     */
    public static function available(HasPermissions $user, ?Model $entity, ?EntityLink $link): bool
    {
        return $entity instanceof Model
            && $link instanceof EntityLink
            && method_exists($entity, 'blocksTree')
            && $user->hasPermission($link->permission)
            && ($user->hasPermission('blocks.view') || $user->hasPermission('blocks.manage'));
    }

    /**
     * The record, for an administrator the tab is for — 404 for one the bar cannot name, 403 for
     * one they may not edit.
     */
    public function find(string $type, string $id, mixed $user): Model
    {
        $found = $this->bar->record($type, $id);

        if ($found === null || ! method_exists($found[0], 'blocksTree')) {
            throw new NotFoundHttpException;
        }

        [$entity, $link] = $found;

        if (! $user instanceof HasPermissions || ! self::available($user, $entity, $link)) {
            throw new AccessDeniedHttpException;
        }

        return $entity;
    }

    /**
     * What the tab reads: the tree an edit works on — the draft when there is one, what the site
     * shows otherwise — its map, and the revision to send back.
     *
     * @return array<string, mixed>
     */
    public function read(Model $entity): array
    {
        $tree = $this->editing($entity);
        $labels = $this->container->make(BlockLabel::class);

        return [
            'revision' => $this->revision($entity, $tree),
            'editing' => $this->hasDraftTree($entity) ? 'draft' : 'live',
            'tree' => $tree,
            'outline' => ContentEdit::outline($tree, label: $labels->of(...)),
        ];
    }

    /**
     * The tree an edit works on: the draft being prepared, or what the site shows when there is
     * no draft yet. Writing goes the same way round — `saveDraft` starts the draft from it.
     *
     * @return list<array<string, mixed>>
     */
    public function editing(Model $entity): array
    {
        $column = $this->column($entity);
        $draft = method_exists($entity, 'draftValues') ? $entity->draftValues() : [];

        if (is_array($draft[$column] ?? null)) {
            return array_values($draft[$column]);
        }

        $live = $entity->getAttribute($column);

        return is_array($live) ? array_values($live) : [];
    }

    /**
     * The revision the record's editor holds — a page's, which covers its other fields too —
     * or the hash of the tree for a record no editor registered. One revision a record, the
     * same the agent's tools answer: whoever read it can write with it anywhere.
     *
     * @param  list<array<string, mixed>>  $tree
     */
    public function revision(Model $entity, array $tree): string
    {
        return $this->records->revisionOf($entity) ?? Content::revision($tree);
    }

    /**
     * Apply the operations and keep the result as the record's draft, from the panel.
     *
     * @param  list<mixed>  $ops
     *
     * @throws ValidationException under `ops` for an operation that cannot be applied, under
     *                             `blocks.<key>.<field>` for a value its field refuses
     */
    public function edit(Model $entity, array $ops, ?string $locale, ?int $authorId): void
    {
        $tree = $this->editing($entity);
        $primary = $this->locales->defaultCode();

        foreach ($ops as $index => $op) {
            $tree = $this->apply($tree, $op, $locale, $primary, $index);
        }

        $column = $this->column($entity);

        // Checked and cast by the field types, as every other save of content is.
        $stored = method_exists($entity, 'storeBlocks') ? $entity->storeBlocks($tree) : $tree;
        $draft = method_exists($entity, 'draftValues') ? $entity->draftValues() : [];

        if (! method_exists($entity, 'saveDraft')) {
            throw new NotFoundHttpException;
        }

        $entity->saveDraft(array_merge($draft, [$column => $stored]), $authorId, EntityVersion::SOURCE_PANEL);
    }

    /**
     * One block of the draft, drawn the way the page draws it for the bar: published types, the
     * values of the draft, the markers around it. In the page's language rather than the panel's —
     * a block that reads records, the next events, finds them in the page's.
     */
    public function html(Model $entity, string $key, ?string $locale): string
    {
        $draft = method_exists($entity, 'withDraft') ? $entity->withDraft() : $entity;
        $tree = $this->editing($entity);
        $node = ContentEdit::find($tree, $key);

        if ($node === null) {
            return '';
        }

        $depth = 0;

        foreach (ContentEdit::outline($tree) as $line) {
            if (($line['key'] ?? null) === $key) {
                $depth = (int) $line['depth'];

                break;
            }
        }

        /** @var Application $app */
        $app = $this->container->make(Application::class);
        $was = $app->getLocale();
        $app->setLocale($this->locales->content($locale));

        try {
            return $this->container->make(Renderer::class)->block($node, $draft, $depth);
        } finally {
            $app->setLocale($was);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    private function apply(array $tree, mixed $op, ?string $locale, string $primary, int $index): array
    {
        $field = 'ops.'.$index;

        if (! is_array($op) || ($op['op'] ?? null) !== 'set' || ! is_string($op['key'] ?? null) || ! is_array($op['values'] ?? null)) {
            throw ValidationException::withMessages([$field => (string) __('webx-blocks::site-bar.bad-op')]);
        }

        $node = ContentEdit::find($tree, $op['key']);

        if ($node === null) {
            throw ValidationException::withMessages([$field => (string) __('webx-blocks::site-bar.gone')]);
        }

        $type = (string) $node['type'];

        // A field the type does not have is refused at the door, as the agent's is.
        // So is a list of blocks inside it: what stands in a container is the constructor's to
        // change, and a whole list sent with a field would replace blocks the form never showed.
        $nested = ($this->types->find($type) ?? $this->types->draft($type))?->nestedFields() ?? [];

        if ($this->strays->unknown($type, $op['values'], is_array($node['values'] ?? null) ? $node['values'] : []) !== []
            || array_intersect(array_map('strval', array_keys($op['values'])), $nested) !== []) {
            throw ValidationException::withMessages([$field => (string) __('webx-blocks::site-bar.bad-op')]);
        }

        try {
            if (is_string($op['locale'] ?? null) && $op['locale'] !== '') {
                return ContentEdit::set($tree, $op['key'], $op['values'], $op['locale'], $this->localized(...), $primary);
            }

            // The page's language goes to the fields that hold a language map and were sent one
            // value: that value is the page's, and the other languages stay as they are. A map
            // is written whole, as the constructor writes it; a plain field takes its value.
            [$own, $whole] = [[], []];

            foreach ($op['values'] as $name => $value) {
                $one = $locale !== null && $this->localized($type, (string) $name) === true && ! $this->isMap($value);
                $one ? $own[$name] = $value : $whole[$name] = $value;
            }

            $tree = $whole === [] ? $tree : ContentEdit::set($tree, $op['key'], $whole, null, $this->localized(...), $primary);

            return $own === [] ? $tree : ContentEdit::set($tree, $op['key'], $own, $locale, $this->localized(...), $primary);
        } catch (BlocksException $failed) {
            throw ValidationException::withMessages([$field => $failed->getMessage()]);
        }
    }

    private function isMap(mixed $value): bool
    {
        return is_array($value) && $value !== [] && ! array_is_list($value);
    }

    /**
     * Whether a field of a block type holds a language map; null when nobody can say. With it, a
     * language is written into its map and the other languages stay; without it, a word in one
     * language would take the place of all of them.
     */
    private function localized(string $type, string $field): ?bool
    {
        $block = $this->types->draft($type) ?? $this->types->find($type);

        if ($block === null || ! in_array($field, $block->fields(), true)) {
            return null;
        }

        return in_array($field, $block->localizedFields(), true);
    }

    private function hasDraftTree(Model $entity): bool
    {
        $draft = method_exists($entity, 'draftValues') ? $entity->draftValues() : [];

        return is_array($draft[$this->column($entity)] ?? null);
    }

    private function column(Model $entity): string
    {
        return method_exists($entity, 'blocksColumn') ? (string) $entity->blocksColumn() : 'blocks';
    }
}
