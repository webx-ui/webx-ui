<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\ContentEdit;
use WebxUi\Blocks\Exceptions\BlockNotPublishable;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\BlockInput;
use WebxUi\Blocks\Panel\Lints;
use WebxUi\Blocks\Panel\Publisher;
use WebxUi\Blocks\Panel\PublishFailed;
use WebxUi\Blocks\Panel\Usage;
use WebxUi\Blocks\Preview\Preview;
use WebxUi\Blocks\Rendering\Bundles;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * What an agent can do with block types and with the content built from them (§18).
 *
 * The same doors the panel uses — `BlockInput` checks the input, `Block::saveVersion()` writes
 * the version, `Publisher` gates publication — so that a type an agent wrote is a type the
 * panel would have accepted, with `mcp` as the source in its history. Two tools close the loop
 * the panel closes with its stage: `render`, which draws a type on values and says where it
 * broke, and `preview_url`, which hands over the page as it will be.
 */
final class BlockTools
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $slug = ['type' => 'string', 'description' => 'The block type, by slug.'];
        $entity = [
            'type' => 'string',
            'description' => 'Which kind of entity: '.$this->entityNames().'.',
        ];
        $id = ['type' => ['integer', 'string'], 'description' => 'The entity\'s id.'];
        $revision = [
            'type' => 'string',
            'description' => 'The revision blocks_get_content returned. Left out, the write goes in whatever happened since.',
        ];

        return [
            Tool::read(
                'list',
                'The block types of this site: what each is called, where it may go, which fields it has, '
                .'whether it is published and on how many pages it stands. Read blocks://guidelines before writing one.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'group' => ['type' => 'string', 'description' => 'Only the types of this group.'],
                ]],
            ),

            Tool::read(
                'get',
                'One block type in full: its settings, and the schema, template, styles, script and sample of '
                .'the version being edited — or of a given version number — with the warnings on that content.',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => [
                    'slug' => $slug,
                    'version' => ['type' => 'integer', 'description' => 'A version number from the history; the current one when omitted.'],
                ], 'required' => ['slug']],
            ),

            Tool::mutating(
                'create',
                'Make a new block type as a draft: settings plus the schema, template, styles, script and sample '
                .'of its first version. Nothing is published; render it, then a person publishes.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->create($arguments, $user),
                ['properties' => $this->typeProperties(), 'required' => ['slug', 'title']],
            ),

            Tool::mutating(
                'update',
                'Change a block type: any of its settings, and any of the five content fields — a field left out '
                .'keeps its value. Changed content is written as a new version of the draft; unchanged content writes none.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->update($arguments, $user),
                ['properties' => ['slug' => $slug] + $this->typeProperties(), 'required' => ['slug']],
            ),

            Tool::mutating(
                'publish',
                'Publish the draft of a block type, so the site prints it. Refused with the line when the template '
                .'fails on the sample or on any page the block already stands on; with dry_run the checks run and nothing moves.',
                fn (array $arguments): array => $this->publish($arguments),
                ['properties' => ['slug' => $slug], 'required' => ['slug']],
            ),

            Tool::read(
                'render',
                'Draw a block type on values — its sample when none are sent — and return the HTML with the styles '
                .'and the wrapped script, or the line the template failed on. The way to see what you wrote.',
                fn (array $arguments): array => $this->render($arguments),
                ['properties' => [
                    'slug' => $slug,
                    'values' => ['type' => 'object', 'description' => 'Field id → value. The sample when omitted; {} to see the block empty.'],
                    'version' => ['type' => 'integer', 'description' => 'A version number; the current one when omitted.'],
                ], 'required' => ['slug']],
            ),

            Tool::read(
                'get_content',
                'The blocks of an entity: the tree the site shows and the draft being prepared, each a list of '
                .'{ key, type, values } nodes where a value may itself be such a list. With outline it answers with '
                .'the map alone — key, type, nesting and a line of text each — and with key, one node in full. '
                .'Read the map first: the values of twenty blocks are not what you need to edit one.',
                fn (array $arguments): array => $this->getContent($arguments),
                ['properties' => [
                    'entity' => $entity,
                    'id' => $id,
                    'outline' => ['type' => 'boolean', 'description' => 'The map of the page instead of the trees.'],
                    'key' => ['type' => 'string', 'description' => 'One node, with whatever is nested inside it.'],
                ], 'required' => ['entity', 'id']],
            ),

            Tool::mutating(
                'set_content',
                'Replace the blocks of an entity\'s draft with a tree of { type, values } nodes; keys are kept when '
                .'sent and made when not. To change part of a page use blocks_edit_content instead — this one writes '
                .'the whole tree, so anything left out is gone. The site keeps showing what it showed until a person '
                .'publishes the entity.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->setContent($arguments, $user),
                ['properties' => [
                    'entity' => $entity,
                    'id' => $id,
                    'blocks' => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'The whole tree, top to bottom.'],
                    'revision' => $revision,
                ], 'required' => ['entity', 'id', 'blocks']],
            ),

            Tool::mutating(
                'edit_content',
                'Change the blocks of an entity a node at a time, by key: set merges values into one block, add puts '
                .'a new one where you say, move and remove rearrange, hide and show switch one block off and back '
                .'on without touching what is in it. Everything not named stays exactly as it is. '
                .'Send the revision blocks_get_content gave you and the edit is refused if the entity changed in '
                .'between, instead of quietly overwriting somebody.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->editContent($arguments, $user),
                ['properties' => [
                    'entity' => $entity,
                    'id' => $id,
                    'revision' => $revision,
                    'ops' => [
                        'type' => 'array',
                        'items' => ['type' => 'object'],
                        'description' => 'In order: { op: "set", key, values, locale? } · '
                            .'{ op: "add", type, values?, parent?, field?, before?, after? } · '
                            .'{ op: "move", key, parent?, field?, before?, after? } · { op: "remove", key } · '
                            .'{ op: "hide", key } · { op: "show", key }. '
                            .'locale writes one language of a localized field; parent omitted means the top level; '
                            .'field names the wx-blocks field when the parent has more than one. A hidden block '
                            .'stays in the content and is not drawn on the site, its nested blocks with it.',
                    ],
                ], 'required' => ['entity', 'id', 'ops']],
            ),

            Tool::read(
                'preview_url',
                'A signed link to the draft of an entity as the page it will be — the drafts of the block types '
                .'included. Good for an hour; open it to see the whole page rather than one block.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->previewUrl($arguments, $user),
                ['properties' => [
                    'entity' => $entity,
                    'id' => $id,
                    'minutes' => ['type' => 'integer', 'description' => 'How long the link lives; the site\'s default when omitted.'],
                ], 'required' => ['entity', 'id']],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = Block::query()->with(['draftVersion', 'publishedVersion'])->orderBy('sort')->orderBy('slug');
        $group = $arguments['group'] ?? null;

        if (is_string($group) && $group !== '') {
            $query->where('group', $group);
        }

        $counts = $this->usage()->counts();
        $blocks = [];

        foreach ($query->get() as $block) {
            $blocks[] = $this->summary($block, $counts);
        }

        return ['blocks' => $blocks, 'count' => count($blocks)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        $block = $this->block($arguments);
        $version = $this->version($block, $arguments['version'] ?? null);

        if ($version === null) {
            throw new ToolFailure("Block [{$block->slug}] has no version yet.");
        }

        $content = $version->content();

        return $this->summary($block, $this->usage()->counts()) + [
            'version' => [
                'number' => $version->number,
                'source' => $version->source,
                'comment' => $version->comment,
                'created_at' => $version->created_at?->toAtomString(),
            ],
            'content' => $content,
            'warnings' => Lints::check($block->slug, $content['template'], $content['styles']),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $this->ensureEditing();

        $input = $this->validate($arguments, BlockInput::rowRules(true) + BlockInput::contentRules());
        $values = BlockInput::values($input);
        $content = BlockInput::content($input);
        $slug = (string) $values['slug'];

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_create' => $slug,
                'warnings' => Lints::check($slug, (string) ($content['template'] ?? ''), (string) ($content['styles'] ?? '')),
            ];
        }

        $block = Block::query()->create($values);
        $block->saveVersion($content, BlockVersion::SOURCE_MCP, $this->authorId($user), BlockInput::comment($input['comment'] ?? null));

        return $this->get(['slug' => $slug]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $this->ensureEditing();

        $block = $this->block($arguments);
        $input = $this->validate($arguments, BlockInput::rowRules(false, $block->id) + BlockInput::contentRules());
        $values = BlockInput::values($input);
        $content = BlockInput::content($input);

        $changes = [];

        foreach ($values as $field => $value) {
            if ($block->getAttribute($field) != $value) {
                $changes[] = $field;
            }
        }

        $writesVersion = $content !== [] && $block->contentDiffers($content);
        $merged = array_merge($block->currentVersion()?->content() ?? [], $content);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'changes' => $changes,
                'would_write_version' => $writesVersion,
                'warnings' => Lints::check($block->slug, (string) ($merged['template'] ?? ''), (string) ($merged['styles'] ?? '')),
            ];
        }

        if ($changes !== []) {
            $block->fill($values)->save();
        }

        if ($writesVersion) {
            $block->saveVersion($content, BlockVersion::SOURCE_MCP, $this->authorId($user), BlockInput::comment($input['comment'] ?? null));
        }

        return $this->get(['slug' => $block->slug]) + ['changes' => $changes, 'wrote_version' => $writesVersion];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function publish(array $arguments): array
    {
        $this->ensureEditing();

        $block = $this->block($arguments);
        $publisher = $this->container->make(Publisher::class);

        try {
            if ($this->dryRun($arguments)) {
                $checked = $publisher->check($block);

                return [
                    'dry_run' => true,
                    'ok' => true,
                    'version' => $block->draftVersion?->number,
                    'checked_on_pages' => $checked,
                ];
            }

            $version = $publisher->publish($block);
        } catch (PublishFailed $failed) {
            $where = $failed->entity === null
                ? 'on the sample'
                : sprintf('on %s #%s%s', class_basename($failed->entity['model']), $failed->entity['id'], $failed->entity['title'] !== null ? " ({$failed->entity['title']})" : '');
            $line = $failed->failure->templateLine !== null ? " at template line {$failed->failure->templateLine}" : '';

            throw new ToolFailure("Not published: the template failed {$where}{$line}: {$failed->failure->reason}");
        } catch (BlocksException) {
            throw new ToolFailure("Block [{$block->slug}] has no draft to publish: what is on the site is the latest version.");
        }

        return ['published' => $version->number, 'slug' => $block->slug];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function render(array $arguments): array
    {
        $block = $this->block($arguments);
        $version = $this->version($block, $arguments['version'] ?? null);

        if ($version === null) {
            throw new ToolFailure("Block [{$block->slug}] has no version to render.");
        }

        $type = BlockType::fromModels($block, $version);
        $values = $arguments['values'] ?? null;
        $values = is_array($values) ? $values : $type->sample;

        $renderer = $this->container->make(Renderer::class);

        try {
            $renderer->check($type, $values);
        } catch (BlockNotPublishable $failure) {
            $line = $failure->templateLine !== null ? " on line {$failure->templateLine}" : '';

            throw new ToolFailure("The template of [{$block->slug}] v{$type->version} failed{$line}: {$failure->reason}");
        }

        return [
            'slug' => $block->slug,
            'version' => $type->version,
            'html' => $renderer->draw($type, $values),
            'styles' => $type->styles,
            'script' => Bundles::wrapScript($type),
            'warnings' => Lints::check($block->slug, $type->template, $type->styles),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getContent(array $arguments): array
    {
        $entity = $this->entity($arguments);
        $column = $this->column($entity);
        $live = $entity->getAttribute($column);
        $draft = method_exists($entity, 'draftValues') ? $entity->draftValues() : [];
        $draftTree = is_array($draft[$column] ?? null) ? $draft[$column] : null;
        $editing = $this->editing($entity);

        $head = [
            'entity' => $this->entities()->nameOf($entity),
            'id' => $entity->getKey(),
            'title' => $this->title($entity),
            'published' => method_exists($entity, 'isPublished') ? (bool) $entity->isPublished() : true,
            // What an edit would change, and the revision of exactly that — the draft when there
            // is one, otherwise what the site shows.
            'editing' => $draftTree === null ? $column : 'draft',
            'revision' => Content::revision($editing),
        ];

        $key = $arguments['key'] ?? null;

        if (is_string($key) && $key !== '') {
            $node = ContentEdit::find($editing, $key);

            if ($node === null) {
                throw new ToolFailure("No block on this entity has the key [{$key}]. Ask with outline to see the keys.");
            }

            return $head + ['node' => $node];
        }

        if (($arguments['outline'] ?? false) === true) {
            return $head + ['outline' => ContentEdit::outline($editing)];
        }

        return $head + [
            'live' => is_array($live) ? array_values($live) : [],
            'draft' => $draftTree === null ? null : array_values($draftTree),
            'types' => Content::types(array_merge(is_array($live) ? $live : [], $draftTree ?? [])),
        ];
    }

    /**
     * The tree an edit works on: the draft being prepared, or what the site shows when there is
     * no draft yet. Writing goes the same way round — `saveDraft` starts the draft from it.
     *
     * @return list<array<string, mixed>>
     */
    private function editing(Model $entity): array
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
     * Apply the operations to the tree and keep the result.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function editContent(array $arguments, ?Authenticatable $user): array
    {
        $entity = $this->entity($arguments);
        $ops = $arguments['ops'] ?? null;

        if (! is_array($ops) || ! array_is_list($ops) || $ops === []) {
            throw new ToolFailure('`ops` must be a non-empty list of operations.');
        }

        $tree = $this->editing($entity);
        $this->sameRevision($arguments, $tree);

        $localized = $this->localized(...);
        $applied = [];

        foreach ($ops as $index => $op) {
            if (! is_array($op)) {
                throw new ToolFailure('Operation '.($index + 1).' is not an object.');
            }

            try {
                $tree = $this->applyOp($tree, $op, $localized);
            } catch (BlocksException $failed) {
                throw new ToolFailure('Operation '.($index + 1).' ('.(string) ($op['op'] ?? '?').'): '.$failed->getMessage());
            }

            $applied[] = (string) ($op['op'] ?? '?');
        }

        return $this->writeContent($entity, $tree, $user, $this->dryRun($arguments), ['ops' => $applied]);
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @param  array<string, mixed>  $op
     * @param  callable(string, string): ?bool  $localized
     * @return list<array<string, mixed>>
     */
    private function applyOp(array $tree, array $op, callable $localized): array
    {
        $name = $op['op'] ?? null;
        $key = is_string($op['key'] ?? null) ? $op['key'] : '';
        $parent = is_string($op['parent'] ?? null) && $op['parent'] !== '' ? $op['parent'] : null;
        $field = is_string($op['field'] ?? null) && $op['field'] !== '' ? $op['field'] : null;
        $before = is_string($op['before'] ?? null) && $op['before'] !== '' ? $op['before'] : null;
        $after = is_string($op['after'] ?? null) && $op['after'] !== '' ? $op['after'] : null;

        return match ($name) {
            'set' => ContentEdit::set(
                $tree,
                $this->opKey($key),
                is_array($op['values'] ?? null) ? $op['values'] : throw new ToolFailure('`values` is required by set.'),
                is_string($op['locale'] ?? null) && $op['locale'] !== '' ? $op['locale'] : null,
                $localized,
            ),
            'add' => ContentEdit::insert(
                $tree,
                [
                    'type' => is_string($op['type'] ?? null) && $op['type'] !== ''
                        ? $op['type']
                        : throw new ToolFailure('`type` is required by add: the slug of a block type.'),
                    'values' => is_array($op['values'] ?? null) ? $op['values'] : [],
                ],
                $parent,
                $field,
                $before,
                $after,
            ),
            'move' => ContentEdit::move($tree, $this->opKey($key), $parent, $field, $before, $after),
            'remove' => ContentEdit::remove($tree, $this->opKey($key)),
            'hide' => ContentEdit::visibility($tree, $this->opKey($key), true),
            'show' => ContentEdit::visibility($tree, $this->opKey($key), false),
            default => throw new ToolFailure('Unknown operation ['.(is_string($name) ? $name : '?').']: set, add, move, remove, hide or show.'),
        };
    }

    private function opKey(string $key): string
    {
        return $key !== '' ? $key : throw new ToolFailure('`key` is required: the key of the block to change.');
    }

    /**
     * Whether a field of a block type holds a language map; null when nobody can say — the type
     * was removed, or the field is not in its schema any more.
     */
    private function localized(string $type, string $field): ?bool
    {
        $types = $this->container->make(BlockTypes::class);
        $block = $types->draft($type) ?? $types->find($type);

        if ($block === null || ! in_array($field, $block->fields(), true)) {
            return null;
        }

        return in_array($field, $block->localizedFields(), true);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<array<string, mixed>>  $tree
     */
    private function sameRevision(array $arguments, array $tree): void
    {
        $sent = $arguments['revision'] ?? null;

        if (! is_string($sent) || $sent === '') {
            return;
        }

        $current = Content::revision($tree);

        if ($sent !== $current) {
            throw new ToolFailure(
                "The entity changed since you read it: revision is [{$current}], you sent [{$sent}]. "
                .'Read it again with blocks_get_content and redo the edit on what is there now.'
            );
        }
    }

    /**
     * Normalise a tree, then keep it as the draft — the one way content is written here.
     *
     * @param  list<array<string, mixed>>  $tree
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function writeContent(Model $entity, array $tree, ?Authenticatable $user, bool $dryRun, array $extra = []): array
    {
        $known = Block::query()->pluck('slug')->all();
        $unknown = [];
        $added = 0;
        $tree = $this->normalise($tree, $known, $unknown, $added, 0);

        if ($unknown !== []) {
            throw new ToolFailure('Unknown block type(s): '.implode(', ', array_unique($unknown)).'. blocks_list says which exist.');
        }

        $column = $this->column($entity);
        $asDraft = method_exists($entity, 'saveDraft');
        $count = 0;
        Content::walk($tree, static function () use (&$count): void {
            $count++;
        });

        $report = $extra + [
            'nodes' => $count,
            'keys_made' => $added,
            'types' => Content::types($tree),
            'revision' => Content::revision($tree),
        ];

        if ($dryRun) {
            return ['dry_run' => true, 'would_write' => $asDraft ? 'draft' : $column] + $report;
        }

        if ($asDraft) {
            $draft = method_exists($entity, 'draftValues') ? $entity->draftValues() : [];
            $entity->saveDraft(array_merge($draft, [$column => $tree]), $this->authorId($user), EntityVersion::SOURCE_MCP);
        } else {
            $entity->setAttribute($column, $tree);
            $entity->save();
        }

        $result = ['written' => $asDraft ? 'draft' : $column] + $report;

        try {
            $result['preview_url'] = $this->container->make(Preview::class)->url($entity, $this->authorId($user));
        } catch (Throwable) {
            // An entity outside the address registry has no preview; the content is written all the same.
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function setContent(array $arguments, ?Authenticatable $user): array
    {
        $entity = $this->entity($arguments);
        $blocks = $arguments['blocks'] ?? null;

        if (! is_array($blocks) || ! array_is_list($blocks)) {
            throw new ToolFailure('`blocks` must be a list of { type, values } nodes.');
        }

        $this->sameRevision($arguments, $this->editing($entity));

        return $this->writeContent($entity, $blocks, $user, $this->dryRun($arguments));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function previewUrl(array $arguments, ?Authenticatable $user): array
    {
        $entity = $this->entity($arguments);
        $minutes = $arguments['minutes'] ?? null;
        $preview = $this->container->make(Preview::class);

        try {
            $url = $preview->url($entity, $this->authorId($user), is_int($minutes) && $minutes > 0 ? $minutes : null);
        } catch (Throwable $failure) {
            throw new ToolFailure("No preview for this entity: {$failure->getMessage()}");
        }

        return ['url' => $url, 'expires_in_minutes' => is_int($minutes) && $minutes > 0 ? $minutes : $preview->minutes()];
    }

    /**
     * The tree as it is stored: every node `{ key, type, values }`, a key made where none was
     * sent, nested lists treated the same. Unknown types and the depth are noted, not fixed.
     *
     * @param  list<mixed>  $nodes
     * @param  list<string>  $known
     * @param  list<string>  $unknown
     * @return list<array<string, mixed>>
     */
    private function normalise(array $nodes, array $known, array &$unknown, int &$added, int $depth): array
    {
        $maxDepth = (int) $this->config()->get('webx-blocks.max_depth', 5);

        if ($depth >= $maxDepth) {
            throw new ToolFailure("Blocks nest at most {$maxDepth} levels deep on this site.");
        }

        $tree = [];

        foreach ($nodes as $node) {
            if (! is_array($node) || ! is_string($node['type'] ?? null) || $node['type'] === '') {
                throw new ToolFailure('Every node needs a `type`: the slug of a block type.');
            }

            if (! in_array($node['type'], $known, true)) {
                $unknown[] = $node['type'];
            }

            $key = $node['key'] ?? null;

            if (! is_string($key) || $key === '' || preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $key) !== 1) {
                $key = substr(bin2hex(random_bytes(8)), 0, 12);
                $added++;
            }

            $values = is_array($node['values'] ?? null) ? $node['values'] : [];

            foreach ($values as $field => $value) {
                if ($this->isNodeList($value)) {
                    $values[$field] = $this->normalise(array_values($value), $known, $unknown, $added, $depth + 1);
                }
            }

            // Rebuilt rather than merged, so that a payload cannot smuggle keys of its own into
            // the content — which means every structural key has to be named here. Visibility
            // (§23) is one, and only when it is on.
            $tree[] = array_filter([
                'key' => $key,
                'type' => $node['type'],
                'hidden' => Content::isHidden($node) ? true : null,
                'values' => $values,
            ], static fn (mixed $value): bool => $value !== null);
        }

        return $tree;
    }

    private function isNodeList(mixed $value): bool
    {
        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! Content::isNode($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function summary(Block $block, array $counts): array
    {
        $current = $block->currentVersion();

        return [
            'slug' => $block->slug,
            'title' => $block->title,
            'description' => $block->description,
            'icon' => $block->icon,
            'group' => $block->group,
            'sort' => $block->sort,
            'allow' => $block->allow,
            'allowed_in' => $block->allowed_in,
            'max_per_entity' => $block->max_per_entity,
            'is_enabled' => $block->is_enabled,
            'draft' => $block->draftVersion?->number,
            'published' => $block->publishedVersion?->number,
            'fields' => $this->fields($current->schema ?? []),
            'usage' => $counts[$block->slug] ?? 0,
        ];
    }

    /**
     * The fields of a schema, through the layout nodes: what a template may use.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array{id: string, type: string, label: string|null}>
     */
    private function fields(array $nodes): array
    {
        $fields = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (is_string($node['id'] ?? null) && $node['id'] !== '' && isset($node['type']) && ! $this->isLayout((string) $node['type'])) {
                $fields[] = [
                    'id' => $node['id'],
                    'type' => (string) $node['type'],
                    'label' => is_string($node['label'] ?? null) ? $node['label'] : null,
                ];
            }

            if (is_array($node['children'] ?? null)) {
                $fields = [...$fields, ...$this->fields(array_values($node['children']))];
            }
        }

        return $fields;
    }

    private function isLayout(string $type): bool
    {
        return in_array($type, ['wx-card', 'wx-tabs', 'wx-tab', 'wx-row', 'wx-col', 'wx-divider', 'wx-text', 'wx-alert'], true);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function block(array $arguments): Block
    {
        $slug = $arguments['slug'] ?? null;

        if (! is_string($slug) || $slug === '') {
            throw new ToolFailure('`slug` is required: the block type to work on.');
        }

        $block = Block::query()->where('slug', $slug)->with(['draftVersion', 'publishedVersion'])->first();

        if (! $block instanceof Block) {
            throw new ToolFailure("No block type is called [{$slug}]. blocks_list says which exist.");
        }

        return $block;
    }

    private function version(Block $block, mixed $number): ?BlockVersion
    {
        if ($number === null) {
            return $block->currentVersion();
        }

        $version = $block->versions()->where('number', (int) $number)->first();

        if (! $version instanceof BlockVersion) {
            throw new ToolFailure("Block [{$block->slug}] has no version {$number}.");
        }

        return $version;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function entity(array $arguments): Model
    {
        $name = $arguments['entity'] ?? null;
        $id = $arguments['id'] ?? null;

        if (! is_string($name) || $name === '' || (! is_int($id) && ! is_string($id))) {
            throw new ToolFailure('`entity` and `id` are required: '.$this->entityNames().'.');
        }

        return $this->entities()->find($name, $id);
    }

    private function column(Model $entity): string
    {
        return method_exists($entity, 'blocksColumn') ? (string) $entity->blocksColumn() : 'blocks';
    }

    private function title(Model $entity): ?string
    {
        $title = $entity->getAttribute('title');

        if (is_array($title)) {
            foreach ($title as $text) {
                if (is_string($text) && $text !== '') {
                    return $text;
                }
            }

            return null;
        }

        return is_string($title) && $title !== '' ? $title : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $arguments, array $rules): array
    {
        try {
            $this->container->make(ValidatorFactory::class)
                ->make($arguments, $rules, BlockInput::messages())
                ->validate();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        }

        return $arguments;
    }

    private function ensureEditing(): void
    {
        if (! (bool) $this->config()->get('webx-blocks.editing', true)) {
            throw new ToolFailure('Editing block types is switched off on this site (webx-blocks.editing): types arrive by import here.');
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function authorId(?Authenticatable $user): ?int
    {
        $id = $user?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    private function entityNames(): string
    {
        $names = array_keys($this->entities()->names());

        return $names === []
            ? 'none yet (the site lists the models that hold blocks in webx-blocks.entities)'
            : implode(', ', $names);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function typeProperties(): array
    {
        $groups = $this->config()->get('webx-blocks.groups', []);
        $slugList = ['type' => ['array', 'null'], 'items' => ['type' => 'string']];

        return [
            'slug' => ['type' => 'string', 'description' => 'kebab-case, unique; also the CSS prefix .b-{slug}.'],
            'title' => ['type' => 'string', 'description' => 'What editors see in the picker.'],
            'description' => ['type' => ['string', 'null'], 'description' => 'One line under the title in the picker.'],
            'icon' => ['type' => ['string', 'null']],
            'group' => ['type' => 'string', 'enum' => is_array($groups) ? array_values($groups) : [], 'description' => 'The section of the picker.'],
            'sort' => ['type' => 'integer'],
            'allow' => $slugList + ['description' => 'Types allowed inside; null when the block is not a container.'],
            'allowed_in' => $slugList + ['description' => 'Where the block may go: type slugs, "root" for the page itself; null for anywhere.'],
            'max_per_entity' => ['type' => ['integer', 'null']],
            'is_enabled' => ['type' => 'boolean'],
            'schema' => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Screen nodes; see blocks://fields.'],
            'template' => ['type' => 'string', 'description' => 'Blade. The root element carries data-wx-block="{slug}".'],
            'styles' => ['type' => 'string', 'description' => 'CSS, every selector under .b-{slug}.'],
            'script' => ['type' => ['string', 'null'], 'description' => 'The body of async (el, values) => { … }; null for none.'],
            'sample' => ['type' => 'object', 'description' => 'A value for every field.'],
            'comment' => ['type' => 'string', 'description' => 'A line for the history of versions.'],
        ];
    }

    private function usage(): Usage
    {
        return $this->container->make(Usage::class);
    }

    private function entities(): Entities
    {
        return $this->container->make(Entities::class);
    }

    private function config(): Config
    {
        return $this->container->make(Config::class);
    }
}
