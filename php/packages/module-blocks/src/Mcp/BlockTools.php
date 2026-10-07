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
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\BlockComponents;
use WebxUi\Blocks\BlockLabel;
use WebxUi\Blocks\BlockShapes;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\ContentEdit;
use WebxUi\Blocks\ContentValues;
use WebxUi\Blocks\Exceptions\BlockNotPublishable;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Exceptions\RegionRefused;
use WebxUi\Blocks\Http\Resources\BlockResource;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Panel\Authors;
use WebxUi\Blocks\Panel\BlockInput;
use WebxUi\Blocks\Panel\Customiser;
use WebxUi\Blocks\Panel\DropsTranslations;
use WebxUi\Blocks\Panel\Graph;
use WebxUi\Blocks\Panel\Lints;
use WebxUi\Blocks\Panel\Publisher;
use WebxUi\Blocks\Panel\PublishFailed;
use WebxUi\Blocks\Panel\RegionForm;
use WebxUi\Blocks\Panel\RegionWriter;
use WebxUi\Blocks\Panel\Renamer;
use WebxUi\Blocks\Panel\Usage;
use WebxUi\Blocks\Preview\Preview;
use WebxUi\Blocks\Regions;
use WebxUi\Blocks\Rendering\Bundles;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Rendering\ViewCalls;
use WebxUi\Blocks\Schema;
use WebxUi\Blocks\StrayValues;
use WebxUi\Localization\Locales;
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
        $id = ['type' => ['integer', 'string'], 'description' => 'The entity\'s id; for a region, its name (header, footer).'];
        $region = ['type' => 'string', 'description' => 'The region, by name: '.$this->regionNames().'.'];

        // The content tools reach the regions of the layout too, which are behind their own
        // permission (§8 of the regions spec): the tool lets in either, the handler asks for the
        // one the entity needs.
        $reads = ['blocks.view', 'blocks.manage', 'blocks.regions'];
        $writes = ['blocks.manage', 'blocks.regions'];
        $revision = [
            'type' => 'string',
            'description' => 'The revision blocks_get_content returned. Left out, the write goes in whatever happened since.',
        ];

        $tools = [
            Tool::read(
                'list',
                'The block types of this site, one short row each: slug, title, whether it is a block editors add or a '
                .'component templates call by tag, its group, the draft and published version, on how many pages it '
                .'stands (usage) and which types call it (used_by). full: true adds every setting and the fields; '
                .'blocks_get has one type in full. declared lists the places modules call a component from, and '
                .'whether the site has customised each. Read blocks://guidelines before writing one.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'group' => ['type' => 'string', 'description' => 'Only the types of this group.'],
                    'full' => ['type' => 'boolean', 'description' => 'Every setting and the fields of every type, not the short row.'],
                ]],
            ),

            Tool::read(
                'get',
                'One block type in full: its settings, and the schema, template, styles, script and sample of '
                .'the version being edited — or of a given version number — with the warnings on that content, '
                .'the types its template calls (uses), the ones that call it (used_by) and the fields of every '
                .'data shape its input names (shape).',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => [
                    'slug' => $slug,
                    'version' => ['type' => 'integer', 'description' => 'A version number from the history; the current one when omitted.'],
                ], 'required' => ['slug']],
            ),

            Tool::mutating(
                'create',
                'Make a new block type as a draft: settings plus the schema, template, styles, script and sample '
                .'of its first version. kind "component" makes one templates call by tag instead of one editors add. '
                .'A slug from blocks_list declared, sent without a template, customises that place: the draft starts '
                .'from what the site prints there now. Nothing is published; render it, then a person publishes.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->create($arguments, $user),
                ['properties' => $this->typeProperties(), 'required' => ['slug']],
            ),

            Tool::mutating(
                'update',
                'Change a block type: any of its settings, and any of the five content fields — a field left out '
                .'keeps its value. Changed content is written as a new version of the draft; unchanged content writes none. '
                .'rename_to gives it a new slug and rewrites every page, region and type that names it — refused while '
                .'another template calls it by tag. Answers with a short summary and the warnings (lints of the template, '
                .'schema and styles; what publishing would convert when a field\'s localized changes); full: true for the whole type.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->update($arguments, $user),
                ['properties' => [
                    'slug' => $slug,
                    'rename_to' => ['type' => 'string', 'description' => 'A new slug for the type: kebab-case, unique. Pages keep their blocks; the template\'s data-wx-block and the .b-{slug} prefix are rewritten too.'],
                    'full' => ['type' => 'boolean', 'description' => 'Answer with the whole type, as blocks_get does, instead of a summary.'],
                ] + $this->typeProperties(), 'required' => ['slug']],
            ),

            Tool::mutating(
                'publish',
                'Publish the draft of a block type, so the site prints it. Refused with the line when the template '
                .'fails on the sample or on any page the block already stands on — and, for a type other types call, '
                .'when it breaks one of them, naming the type and the page. A draft that switches a field\'s localized '
                .'converts the values already on pages to the new shape; one that takes localized off over text in '
                .'several languages is refused, naming the pages, unless drop_translations is true. With dry_run the '
                .'checks run and nothing moves.',
                fn (array $arguments): array => $this->publish($arguments),
                ['properties' => [
                    'slug' => $slug,
                    'drop_translations' => ['type' => 'boolean', 'description' => 'Agree to keep only the main language where a field stops being localized.'],
                ], 'required' => ['slug']],
            ),

            Tool::mutating(
                'delete',
                'Delete a block type with its whole history. Refused while it stands on any page or region, or while '
                .'another type\'s template calls it — blocks_usage says where. Refused too while a view of the site '
                .'calls it by tag, unless force is sent: the view would print nothing there. Deleting a customised '
                .'module component brings the module\'s own partial back: that is how a customisation is undone.',
                fn (array $arguments): array => $this->delete($arguments),
                ['properties' => [
                    'slug' => $slug,
                    'force' => ['type' => 'boolean', 'description' => 'Delete although views of the site call it by tag.'],
                ], 'required' => ['slug']],
            ),

            Tool::read(
                'versions',
                'The history of a block type: every version, newest first — number, source (panel, mcp, import), '
                .'author, comment, date — and which one is the draft and which is published. blocks_get with version '
                .'reads one; blocks_version_restore brings one back.',
                fn (array $arguments): array => $this->versions($arguments),
                ['properties' => ['slug' => $slug], 'required' => ['slug']],
            ),

            Tool::mutating(
                'version_restore',
                'Bring an old version of a block type back as a new draft — its schema, template, styles, script and '
                .'sample as they were. Nothing is published: publish it as any draft.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->versionRestore($arguments, $user),
                ['properties' => [
                    'slug' => $slug,
                    'number' => ['type' => 'integer', 'description' => 'A version number, as blocks_versions lists it.'],
                ], 'required' => ['slug', 'number']],
            ),

            Tool::read(
                'usage',
                'Where a block type stands: every page, service, region or other entity holding it — entity and id '
                .'as blocks_get_content takes them, title, address, whether it is in what the site shows, in the draft '
                .'or both — the types whose templates call it, and the views of the site that call it by tag (views).',
                fn (array $arguments): array => $this->usageOf($arguments),
                ['properties' => ['slug' => $slug], 'required' => ['slug']],
            ),

            Tool::read(
                'render',
                'Draw a block type on values — its sample when none are sent — and return the HTML with the styles '
                .'and the wrapped script, or the line the template failed on. For a component the values are its '
                .'input: what the tag passes. The way to see what you wrote.',
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
                fn (array $arguments, ?Authenticatable $user = null): array => $this->getContent($arguments, $user),
                ['properties' => [
                    'entity' => $entity,
                    'id' => $id,
                    'outline' => ['type' => 'boolean', 'description' => 'The map of the page instead of the trees.'],
                    'key' => ['type' => 'string', 'description' => 'One node, with whatever is nested inside it.'],
                ], 'required' => ['entity', 'id']],
                permission: $reads,
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
                permission: $writes,
            ),

            Tool::mutating(
                'edit_content',
                'Change the blocks of an entity a node at a time, by key: set merges values into one block, unset takes values out of it, add puts '
                .'a new one where you say, move and remove rearrange, duplicate copies a block with everything inside it '
                .'right after it, hide and show switch one block off and back on without touching what is in it. '
                .'Everything not named stays exactly as it is. Every value is checked by its field\'s rules (bounds, '
                .'options, dates, colours, links — http(s), mailto, tel, relative paths and #anchors only — library files) '
                .'and every block against where it stands (a container\'s allow and max, a type\'s allowed_in and '
                .'max_per_entity); a refusal names the block\'s key and the field. '
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
                        'description' => 'In order: { op: "set", key, values, locale? } · { op: "unset", key, fields } · { op: "duplicate", key } · '
                            .'{ op: "add", type, values?, parent?, field?, before?, after? } · '
                            .'{ op: "move", key, parent?, field?, before?, after? } · { op: "remove", key } · '
                            .'{ op: "hide", key } · { op: "show", key }. unset takes the named values out of a block — '
                            .'the keys, where set with null keeps the key; use it for values of fields the type does not have. '
                            .'locale writes one language of a localized field; parent omitted means the top level; '
                            .'field names the wx-blocks field when the parent has more than one. A hidden block '
                            .'stays in the content and is not drawn on the site, its nested blocks with it.',
                    ],
                ], 'required' => ['entity', 'id', 'ops']],
                permission: $writes,
            ),

            Tool::read(
                'preview_url',
                'A signed link to the draft of an entity as the page it will be — the drafts of the block types '
                .'included. Good for an hour; open it to see the whole page rather than one block. For a region '
                .'it is a page of the site with the region\'s draft on it: the front page, or the path in at.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->previewUrl($arguments, $user),
                ['properties' => [
                    'entity' => $entity,
                    'id' => $id,
                    'minutes' => ['type' => 'integer', 'description' => 'How long the link lives; the site\'s default when omitted.'],
                    'at' => ['type' => 'string', 'description' => 'Regions only: the path of the page to draw the region on, such as /about. The front page when omitted.'],
                ], 'required' => ['entity', 'id']],
                permission: $reads,
            ),
        ];

        // Regions are the site's to declare. Seven tools about a header and a footer the layout
        // does not have were seven ways for an agent to go looking for them.
        if ($this->container->make(Regions::class)->declared() === []) {
            return $tools;
        }

        return [
            ...$tools,

            Tool::read(
                'regions',
                'The regions of the layout — the header, the footer — whose content is blocks: name, title, which '
                .'types each takes, whether its blocks are on the site or the markup from code is (fallback, the view '
                .'the layout prints while the region is empty or unpublished), how many blocks it holds and a preview '
                .'link. Edit one with blocks_get_content / blocks_edit_content, entity "region", id its name.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->regions($user),
                permission: 'blocks.regions',
            ),

            Tool::mutating(
                'region_publish',
                'Publish the draft of a region, so the site prints its blocks in place of the markup from code. '
                .'Refused, naming the block and the line, when any block of the draft fails to render — on the site '
                .'a failing block would make the whole region fall back. With dry_run the check runs and nothing moves.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->regionPublish($arguments, $user),
                ['properties' => ['name' => $region], 'required' => ['name']],
                permission: 'blocks.regions',
            ),

            Tool::mutating(
                'region_unpublish',
                'Take a region off the site: the layout prints the markup from code again. The blocks and the draft '
                .'stay, for the next publication.',
                fn (array $arguments): array => $this->regionUnpublish($arguments),
                ['properties' => ['name' => $region], 'required' => ['name']],
                permission: 'blocks.regions',
            ),

            Tool::mutating(
                'region_discard',
                'Throw away the draft of a region: the editor goes back to what the site shows (or to nothing, for a '
                .'region never published). dry_run says whether there is a draft.',
                fn (array $arguments): array => $this->regionDiscard($arguments),
                ['properties' => ['name' => $region], 'required' => ['name']],
                permission: 'blocks.regions',
            ),

            Tool::read(
                'region_versions',
                'The publications of a region, newest first: number, date, author, source, comment. '
                .'blocks_region_restore brings one back into the draft.',
                fn (array $arguments): array => $this->regionVersions($arguments),
                ['properties' => ['name' => $region], 'required' => ['name']],
                permission: 'blocks.regions',
            ),

            Tool::mutating(
                'region_restore',
                'Put an old publication of a region back into its draft. Publish it with blocks_region_publish.',
                fn (array $arguments): array => $this->regionRestore($arguments),
                ['properties' => [
                    'name' => $region,
                    'number' => ['type' => 'integer', 'description' => 'A version number, as blocks_region_versions lists it.'],
                ], 'required' => ['name', 'number']],
                permission: 'blocks.regions',
            ),

            Tool::mutating(
                'region_adopt',
                'Start a region from the markup the layout prints there now: a block type site-{name} is made from '
                .'that view and published, and one block of it is put into the region\'s draft. Writes a block type, '
                .'so it needs blocks.manage too. Refused when the region has no fallback view or the slug is taken.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->regionAdopt($arguments, $user),
                ['properties' => ['name' => $region], 'required' => ['name']],
                permission: 'blocks.regions',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = Block::query()->with(['draftVersion', 'publishedVersion'])->orderBy('position')->orderBy('slug');
        $group = $arguments['group'] ?? null;

        if (is_string($group) && $group !== '') {
            $query->where('group', $group);
        }

        $counts = $this->usage()->counts();
        $parents = $this->container->make(Graph::class)->parents();
        $blocks = [];
        $slugs = [];

        // Short by default: every type with every field was twenty-odd thousand characters on a
        // real site, read in full to find one slug.
        $full = ($arguments['full'] ?? false) === true;

        foreach ($query->get() as $block) {
            $blocks[] = $full ? $this->summary($block, $counts, $parents) : [
                'slug' => $block->slug,
                'title' => $block->title,
                'kind' => $block->kind,
                'group' => $block->group,
                'is_enabled' => $block->is_enabled,
                'draft' => $block->draftVersion?->number,
                'published' => $block->publishedVersion?->number,
                'usage' => $counts[$block->slug] ?? 0,
                'used_by' => $parents[$block->slug] ?? [],
            ];
            $slugs[$block->slug] = true;
        }

        // The places modules call a component from. Until one is customised the module's own
        // partial prints there; blocks_create with that slug starts a component from it.
        $declared = array_map(
            static fn (array $place): array => BlockResource::declaration($place, isset($slugs[$place['slug']])),
            $this->container->make(BlockComponents::class)->all(),
        );

        return ['blocks' => $blocks, 'count' => count($blocks), 'declared' => $declared];
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
        $declared = $this->container->make(BlockComponents::class)->get($block->slug);
        $type = BlockType::fromModels($block, $version);
        $strays = array_values(array_diff(array_map(strval(...), array_keys($type->sample)), $type->fields()));
        $warnings = Lints::check($block->slug, $content['template'], $content['styles'], $content['schema']);

        // A sample value no field holds is drawn by nothing and copied into every new block.
        if ($strays !== []) {
            $warnings[] = [
                'file' => 'sample',
                'code' => 'sample-unknown-field',
                'line' => null,
                'message' => 'The sample has values for fields the schema does not have: ['.implode('], [', $strays).']. Take them out of the sample.',
            ];
        }

        return $this->summary($block, $this->usage()->counts(), $this->container->make(Graph::class)->parents()) + [
            'uses' => $version->calls(),
            // What `$card['…']` holds, for every `wx-data` input that names a shape.
            'shape' => (object) $this->container->make(BlockShapes::class)->describe($content['schema']),
            'declared' => $declared === null ? null : BlockResource::declaration($declared, true),
            'version' => [
                'number' => $version->number,
                'source' => $version->source,
                'comment' => $version->comment,
                'created_at' => $version->created_at?->toAtomString(),
            ],
            'content' => $content,
            'warnings' => $warnings,
        ];
    }

    /**
     * The panel's Delete: refused while the type stands anywhere or is called by another type.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $this->ensureEditing();

        $block = $this->block($arguments);
        $parents = $this->container->make(Graph::class)->usedBy($block->slug);

        if ($parents !== []) {
            throw new ToolFailure('Not deleted: other types call it — '.implode(', ', array_map(static fn (array $parent): string => $parent['slug'], $parents)).'. Change their templates first.');
        }

        $where = $this->usage()->of($block->slug);

        if ($where !== []) {
            throw new ToolFailure('Not deleted: it stands on '.count($where).' page(s) or region(s). blocks_usage lists them; take it out of them first.');
        }

        // A view of the site that calls the type by tag is a use the tables know nothing about:
        // deleted, the tag prints nothing and the page loses that part without a word.
        $views = $this->container->make(ViewCalls::class)->of($block->slug);
        $forced = ($arguments['force'] ?? false) === true;

        if ($views !== [] && ! $forced) {
            throw new ToolFailure(
                'Not deleted: views of the site call it by tag — '.implode(', ', array_map(ViewCalls::describe(...), $views))
                .'. Take the tag out of them first, or send force: true and those places print nothing.'
            );
        }

        $declared = $this->container->make(BlockComponents::class)->get($block->slug);

        if ($this->dryRun($arguments)) {
            $answer = ['dry_run' => true, 'would_delete' => $block->slug, 'versions' => $block->versions()->count(), 'restores_module_view' => $declared === null ? null : $declared['fallback']];

            return $views === [] ? $answer : $answer + ['views_left_calling' => $views];
        }

        $block->delete();
        $answer = ['deleted' => $block->slug, 'restored_module_view' => $declared === null ? null : $declared['fallback']];

        return $views === [] ? $answer : $answer + ['views_left_calling' => $views];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function versions(array $arguments): array
    {
        $block = $this->block($arguments);
        $versions = $block->versions()->get();
        $authors = Authors::names($versions->map(static fn (BlockVersion $version): ?int => $version->author_id));

        return [
            'slug' => $block->slug,
            'draft' => $block->draftVersion?->number,
            'published' => $block->publishedVersion?->number,
            'versions' => $versions->map(static fn (BlockVersion $version): array => [
                'number' => $version->number,
                'source' => $version->source,
                'author' => $version->author_id === null ? null : ($authors[$version->author_id] ?? null),
                'comment' => $version->comment,
                'created_at' => $version->created_at?->toAtomString(),
                'is_draft' => $version->id === $block->draft_version_id,
                'is_published' => $version->id === $block->published_version_id,
            ])->values()->all(),
        ];
    }

    /**
     * The panel's «Restore»: an old version becomes a new draft.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function versionRestore(array $arguments, ?Authenticatable $user): array
    {
        $this->ensureEditing();

        $block = $this->block($arguments);
        $number = $arguments['number'] ?? null;

        if (! is_int($number) && ! (is_string($number) && ctype_digit($number))) {
            throw new ToolFailure('`number` is required: a version number from blocks_versions.');
        }

        $version = $this->version($block, (int) $number);

        if (! $version instanceof BlockVersion) {
            throw new ToolFailure("Block [{$block->slug}] has no version {$number}.");
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_restore' => $version->number, 'as_draft' => (int) $block->versions()->max('number') + 1];
        }

        $draft = $block->saveVersion(
            $version->content(),
            BlockVersion::SOURCE_MCP,
            $this->authorId($user),
            (string) __('webx-blocks::page.restored-from', ['number' => $version->number]),
        );

        return ['restored' => $version->number, 'draft' => $draft->number, 'slug' => $block->slug];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function usageOf(array $arguments): array
    {
        $block = $this->block($arguments);
        $entities = $this->entities();
        $where = [];

        foreach ($this->usage()->of($block->slug) as $entry) {
            $name = $entities->nameOfClass($entry['model']);

            $where[] = [
                'entity' => $name,
                'id' => $entry['region'] ?? $entry['id'],
                'title' => $entry['title'],
                'url' => $entry['url'],
                'published' => $entry['published'],
                'in' => $entry['live'] && $entry['draft'] ? 'live and draft' : ($entry['live'] ? 'live' : 'draft'),
            ];
        }

        return [
            'slug' => $block->slug,
            'count' => count($where),
            'entities' => $where,
            'used_by' => $this->container->make(Graph::class)->usedBy($block->slug),
            // The site's own views: a component called from a listing or a layout stands there
            // without being in any table, and blocks_delete would otherwise not know.
            'views' => $this->container->make(ViewCalls::class)->of($block->slug),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $this->ensureEditing();

        $customiser = $this->container->make(Customiser::class);
        $declaredSlug = is_string($arguments['slug'] ?? null) ? $arguments['slug'] : '';

        // A declared place without a template of the agent's own is "Customise": the component
        // starts from what the site prints there now, with the module's input and sample.
        if ($customiser->declared($declaredSlug) && ! is_string($arguments['template'] ?? null)) {
            if (Block::query()->where('slug', $declaredSlug)->exists()) {
                throw new ToolFailure("[{$declaredSlug}] is customised already: change it with blocks_update.");
            }

            if ($this->dryRun($arguments)) {
                return ['dry_run' => true, 'would_customise' => $declaredSlug];
            }

            $customiser->customise($declaredSlug, BlockVersion::SOURCE_MCP, $this->authorId($user));

            return $this->get(['slug' => $declaredSlug]) + ['customised' => true];
        }

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

        $refusal = BlockInput::kindRefusal($block, $values['kind'] ?? null, $this->usage()->counts());

        if ($refusal !== null) {
            throw new ToolFailure('Not accepted — kind: '.$refusal);
        }

        $changes = [];

        foreach ($values as $field => $value) {
            if ($block->getAttribute($field) != $value) {
                $changes[] = $field;
            }
        }

        $writesVersion = $content !== [] && $block->contentDiffers($content);
        $current = $block->currentVersion()?->content() ?? [];

        // The content fields that differ, beside the settings: `changes: []` next to
        // `wrote_version: true` read as an update that did nothing.
        foreach ($content as $field => $value) {
            if (($current[$field] ?? null) != $value) {
                $changes[] = (string) $field;
            }
        }

        $merged = array_merge($current, $content);
        $renameTo = is_string($arguments['rename_to'] ?? null) && $arguments['rename_to'] !== '' && $arguments['rename_to'] !== $block->slug
            ? $arguments['rename_to']
            : null;
        $renamer = $this->container->make(Renamer::class);

        if ($renameTo !== null) {
            $this->validate(['slug' => $renameTo], ['slug' => BlockInput::rowRules(false, $block->id)['slug']]);

            $refused = $renamer->refusal($block);

            if ($refused !== null) {
                throw new ToolFailure('Not renamed: '.$refused);
            }
        }

        $warnings = Lints::check($block->slug, (string) ($merged['template'] ?? ''), (string) ($merged['styles'] ?? ''), is_array($merged['schema'] ?? null) ? $merged['schema'] : []);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'changes' => $changes,
                'would_write_version' => $writesVersion,
                'would_rename' => $renameTo === null ? null : ['to' => $renameTo, 'pages' => count($this->usage()->of($block->slug))],
                'warnings' => $warnings,
            ];
        }

        $renamed = null;
        // Counted rather than predicted: a rename writes versions of its own (the template's
        // `data-wx-block`, the `.b-{slug}` prefix), and `wrote_version: false` was said after two.
        $numberBefore = (int) $block->versions()->max('number');

        if ($renameTo !== null) {
            $from = $block->slug;
            $renamed = ['from' => $from, 'to' => $renameTo] + $renamer->rename($block, $renameTo, BlockVersion::SOURCE_MCP, $this->authorId($user));
            $block = $this->block(['slug' => $renameTo]);
            unset($values['slug']);

            // Content sent beside rename_to was written against the old slug.
            $content = Renamer::carry($content, $from, $renameTo);
            $writesVersion = $content !== [] && $block->contentDiffers($content);
        }

        if ($changes !== []) {
            $block->fill($values)->save();
        }

        if ($writesVersion) {
            $block->saveVersion($content, BlockVersion::SOURCE_MCP, $this->authorId($user), BlockInput::comment($input['comment'] ?? null));
        }

        $versionsWritten = max(0, (int) $block->versions()->max('number') - $numberBefore);
        $writesVersion = $versionsWritten > 0;

        if (($arguments['full'] ?? false) === true) {
            return $this->get(['slug' => $block->slug]) + ['changes' => $changes, 'wrote_version' => $writesVersion, 'versions_written' => $versionsWritten, 'renamed' => $renamed];
        }

        $block->refresh()->load(['draftVersion', 'publishedVersion']);
        $language = $this->languageWarning($block);

        // A summary: the whole type again is a page of schema and template the agent has just
        // sent, on every call. blocks_get, or full: true, when it is wanted.
        return array_filter([
            'slug' => $block->slug,
            'draft' => $block->draftVersion?->number,
            'published' => $block->publishedVersion?->number,
            'changes' => $changes,
            'wrote_version' => $writesVersion,
            'versions_written' => $versionsWritten,
            'renamed' => $renamed,
            'warnings' => $language === null ? $warnings : [...$warnings, $language],
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * What publishing the draft would do to content already written, as a warning — or null when
     * it changes no field's `localized`.
     *
     * @return array{file: string, code: string, line: null, message: string}|null
     */
    private function languageWarning(Block $block): ?array
    {
        $changes = $this->container->make(Publisher::class)->languageChanges($block);

        if ($changes['flips'] === []) {
            return null;
        }

        $fields = array_map(
            static fn (array $flip): string => ($flip['child'] === null ? $flip['field'] : "{$flip['field']}.*.{$flip['child']}").($flip['localized'] ? ' → localized' : ' → one language'),
            $changes['flips'],
        );

        return [
            'file' => 'schema',
            'code' => 'localized-changes',
            'line' => null,
            'message' => (string) __('webx-blocks::page.localized-changes', ['fields' => implode(', ', $fields), 'count' => count($changes['entities'])]),
        ];
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
                $language = $publisher->languageChanges($block);

                return [
                    'dry_run' => true,
                    'ok' => true,
                    'version' => $block->draftVersion?->number,
                    'checked_on_pages' => $checked,
                    'would_convert' => $language['flips'] === [] ? null : $language,
                ];
            }

            $version = $publisher->publish($block, ($arguments['drop_translations'] ?? false) === true);
        } catch (PublishFailed $failed) {
            // A component that breaks another type, the module's declared place or a cycle: the
            // sentence names the parent and the page, which is what the agent has to go and look at.
            if ($failed->parent !== null || $failed->declared !== null || $failed->cycle !== null || $failed->marker) {
                $line = $failed->failure->templateLine !== null ? " (template line {$failed->failure->templateLine})" : '';

                throw new ToolFailure('Not published: '.$failed->describe().$line);
            }

            $where = $failed->entity === null
                ? 'on the sample'
                : sprintf('on %s #%s%s', class_basename($failed->entity['model']), $failed->entity['id'], $failed->entity['title'] !== null ? " ({$failed->entity['title']})" : '');
            $line = $failed->failure->templateLine !== null ? " at template line {$failed->failure->templateLine}" : '';

            throw new ToolFailure("Not published: the template failed {$where}{$line}: {$failed->failure->reason}");
        } catch (DropsTranslations $drops) {
            throw new ToolFailure('Not published: '.$drops->getMessage().' (drop_translations: true agrees.)');
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
        $sent = is_array($arguments['values'] ?? null);
        $values = $sent ? $arguments['values'] : $type->sample;

        $renderer = $this->container->make(Renderer::class);

        try {
            $renderer->check($type, $values);
        } catch (BlockNotPublishable $failure) {
            $line = $failure->templateLine !== null ? " on line {$failure->templateLine}" : '';

            throw new ToolFailure("The template of [{$block->slug}] v{$type->version} failed{$line}: {$failure->reason}");
        }

        // The preview's markers are for the panel's stage, which finds a block by them; here they
        // only said `sample` around values the agent had just sent.
        $html = (string) preg_replace('/^<!--wx:[A-Za-z0-9_.:-]*-->(.*)<!--\/wx:[A-Za-z0-9_.:-]*-->$/s', '$1', $renderer->draw($type, $values));

        return [
            'slug' => $block->slug,
            'version' => $type->version,
            'values_from' => $sent ? 'values' : 'sample',
            'html' => $html,
            'styles' => $type->styles,
            'script' => Bundles::wrapScript($type),
            'warnings' => [
                ...Lints::check($block->slug, $type->template, $type->styles),
                ...$this->valueWarnings($type, $values),
            ],
        ];
    }

    /**
     * What a write would refuse in these values, as warnings: a render draws whatever it is
     * given, and a link sent as `{ url }` without its `target` drew nothing and said nothing.
     *
     * @param  array<string, mixed>  $values
     * @return list<array{file: string, code: string, line: null, message: string}>
     */
    private function valueWarnings(BlockType $type, array $values): array
    {
        $warnings = [];
        $strays = array_values(array_diff(array_map(strval(...), array_keys($values)), $type->fields()));

        if ($strays !== []) {
            $warnings[] = [
                'file' => 'values',
                'code' => 'unknown-field',
                'line' => null,
                'message' => "{$type->slug} has no field [".implode('], [', $strays).']; those values are not drawn and a write refuses them.',
            ];
        }

        $problems = $this->container->make(ContentValues::class)->problems([['key' => 'render', 'type' => $type->slug, 'values' => $values]]);

        foreach ($problems as $problem) {
            // Where the block may stand is not this block's values: a render stands nowhere.
            if ($problem['field'] === null) {
                continue;
            }

            $warnings[] = ['file' => 'values', 'code' => 'value-refused', 'line' => null, 'message' => ContentValues::describe($problem)];
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getContent(array $arguments, ?Authenticatable $user = null): array
    {
        $entity = $this->entity($arguments);
        $this->authorise($entity, $user, false);
        $column = $this->column($entity);
        $live = $entity->getAttribute($column);
        $draft = method_exists($entity, 'draftValues') ? $entity->draftValues() : [];
        $draftTree = is_array($draft[$column] ?? null) ? $draft[$column] : null;
        $editing = $this->editing($entity);

        $head = [
            'entity' => $this->entities()->nameOf($entity),
            'id' => $entity instanceof Region ? $entity->name : $entity->getKey(),
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
            $labels = $this->container->make(BlockLabel::class);

            return $head + ['outline' => ContentEdit::outline($editing, label: $labels->of(...))];
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
        $this->authorise($entity, $user, true);
        $ops = $arguments['ops'] ?? null;

        if (! is_array($ops) || ! array_is_list($ops) || $ops === []) {
            throw new ToolFailure('`ops` must be a non-empty list of operations.');
        }

        $tree = $this->editing($entity);
        $this->sameRevision($arguments, $tree);
        $keysBefore = self::keys($tree);

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

        // The keys the ops made — a duplicate's copy and everything inside it, an added block — on
        // top of the ones the write itself fills in; `keys_made: 0` was said after a duplicate.
        $made = count(array_diff(self::keys($tree), $keysBefore));

        return $this->writeContent($entity, $tree, $user, $this->dryRun($arguments), ['ops' => $applied, 'keys_made' => $made]);
    }

    /**
     * Every key in a tree, nested ones included.
     *
     * @param  list<array<string, mixed>>  $tree
     * @return list<string>
     */
    private static function keys(array $tree): array
    {
        $keys = [];

        Content::walk($tree, static function (array $node) use (&$keys): void {
            if (is_string($node['key'] ?? null) && $node['key'] !== '') {
                $keys[] = $node['key'];
            }
        });

        return $keys;
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

        // A value for a field the type does not have is refused at the door: this is how stray
        // values got into pages, and once in, nothing but a prune takes them out.
        if ($name === 'set' && is_array($op['values'] ?? null) && ($node = ContentEdit::find($tree, $key)) !== null) {
            $this->refuseStray((string) $node['type'], $op['values'], is_array($node['values'] ?? null) ? $node['values'] : [], self::held($tree));
        }

        if ($name === 'add' && is_string($op['type'] ?? null) && is_array($op['values'] ?? null)) {
            $this->refuseStray($op['type'], $op['values'], [], self::held($tree));
        }

        $containers = $this->containers(...);

        return match ($name) {
            'set' => ContentEdit::set(
                $tree,
                $this->opKey($key),
                is_array($op['values'] ?? null) ? $op['values'] : throw new ToolFailure('`values` is required by set.'),
                is_string($op['locale'] ?? null) && $op['locale'] !== '' ? $op['locale'] : null,
                $localized,
                $this->container->make(Locales::class)->defaultCode(),
            ),
            'duplicate' => ContentEdit::duplicate($tree, $this->opKey($key), static fn (): string => substr(bin2hex(random_bytes(8)), 0, 12)),
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
                $containers,
            ),
            'move' => ContentEdit::move($tree, $this->opKey($key), $parent, $field, $before, $after, $containers),
            'remove' => ContentEdit::remove($tree, $this->opKey($key)),
            'unset' => ContentEdit::unset(
                $tree,
                $this->opKey($key),
                is_array($op['fields'] ?? null) && $op['fields'] !== [] && array_is_list($op['fields'])
                    ? array_map(strval(...), $op['fields'])
                    : throw new ToolFailure('`fields` is required by unset: the names of the values to take out.'),
            ),
            'hide' => ContentEdit::visibility($tree, $this->opKey($key), true),
            'show' => ContentEdit::visibility($tree, $this->opKey($key), false),
            default => throw new ToolFailure('Unknown operation ['.(is_string($name) ? $name : '?').']: set, unset, add, move, duplicate, remove, hide or show.'),
        };
    }

    /**
     * The `wx-blocks` fields of a block type, by id — empty for a type that holds no blocks, null
     * for one nobody knows. What says whether "inside this block" is a place at all.
     *
     * @return list<string>|null
     */
    private function containers(string $type): ?array
    {
        $types = $this->container->make(BlockTypes::class);
        $block = $types->find($type) ?? $types->draft($type);

        return $block?->nestedFields();
    }

    /**
     * Refuse values for fields a block type does not define — the block's own and those of every
     * block nested in it. A field the block already holds may keep its value or be emptied; a
     * value of a field the type no longer has goes with `unset`.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $current
     * @param  array<string, array<string, mixed>>  $held  Key → the values of every node the entity holds now.
     *
     * @throws BlocksException
     */
    private function refuseStray(string $type, array $values, array $current, array $held): void
    {
        $strays = $this->container->make(StrayValues::class);
        $unknown = $strays->unknown($type, $values, $current);

        if ($unknown !== []) {
            throw new BlocksException(
                "{$type} has no field [".implode('], [', $unknown).']. Its fields: '.implode(', ', $strays->fieldsOf($type)).'.'
            );
        }

        foreach ($values as $value) {
            if (! Content::isNodeList($value)) {
                continue;
            }

            foreach ($value as $node) {
                if (Content::isNode($node)) {
                    $key = is_string($node['key'] ?? null) ? $node['key'] : null;
                    $this->refuseStray((string) $node['type'], is_array($node['values'] ?? null) ? $node['values'] : [], $key === null ? [] : ($held[$key] ?? []), $held);
                }
            }
        }
    }

    /**
     * Every node of a tree by key, with its values: what a write may hand back as it was.
     *
     * @param  list<mixed>  $tree
     * @return array<string, array<string, mixed>>
     */
    private static function held(array $tree): array
    {
        $held = [];

        foreach ($tree as $node) {
            if (! Content::isNode($node)) {
                continue;
            }

            $values = is_array($node['values'] ?? null) ? $node['values'] : [];

            if (is_string($node['key'] ?? null)) {
                $held[$node['key']] = $values;
            }

            foreach ($values as $value) {
                if (Content::isNodeList($value)) {
                    $held += self::held(array_values($value));
                }
            }
        }

        return $held;
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
     * Normalise a tree, cast its values, then keep it as the draft — the one way content is
     * written here.
     *
     * @param  list<array<string, mixed>>  $tree
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function writeContent(Model $entity, array $tree, ?Authenticatable $user, bool $dryRun, array $extra = []): array
    {
        $known = Block::query()->pluck('slug')->all();
        $unknown = [];
        $added = (int) ($extra['keys_made'] ?? 0);
        unset($extra['keys_made']);
        $tree = $this->normalise($tree, $known, $unknown, $added, 0);

        if ($unknown !== []) {
            throw new ToolFailure('Unknown block type(s): '.implode(', ', array_unique($unknown)).'. blocks_list says which exist.');
        }

        // Every value as the field type its schema names keeps it, which is the same step the
        // panel's save takes: what an agent writes and what an editor writes have to arrive in
        // the row as the same thing, or the allowlist a type runs its HTML through is a door
        // with one side.
        //
        // Checked first, by the same rules, so that a dry run says what a write would refuse: a
        // value past its field's bounds, an option nobody offered, a link to `javascript:`, a block
        // where its container does not take it.
        $column = $this->column($entity);
        $root = method_exists($entity, 'blocksRoot') ? (string) $entity->blocksRoot() : 'root';
        $values = $this->container->make(ContentValues::class);
        $before = $this->editing($entity);
        $problems = $values->problems($tree, $root, $before);

        if ($problems !== []) {
            throw new ToolFailure('Not accepted — '.implode('; ', array_map(ContentValues::describe(...), $problems)));
        }

        // A region takes what its declaration and the types' `allowed_in` let it take — the same
        // gate the panel's save goes through.
        if ($entity instanceof Region) {
            try {
                $this->container->make(RegionWriter::class)->admissible($entity->name, $tree);
            } catch (RegionRefused $refused) {
                throw new ToolFailure('Not accepted — '.$refused->sentence());
            }
        }

        $tree = $values->store($tree, $column, $root, $before);

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
            // The revision is what the entity is now — the one to send with the real write. The
            // tree it would become has one too, under its own name: sent back, it was refused as
            // "the entity changed".
            return [
                'dry_run' => true,
                'would_write' => $asDraft ? 'draft' : $column,
                'revision' => Content::revision($before),
                'would_be_revision' => $report['revision'],
            ] + $report;
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
            $preview = $this->container->make(Preview::class);
            $result['preview_url'] = $entity instanceof Region
                ? $preview->regionUrl($entity->name, $this->authorId($user))
                : $preview->url($entity, $this->authorId($user));
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
        $this->authorise($entity, $user, true);
        $blocks = $arguments['blocks'] ?? null;

        if (! is_array($blocks) || ! array_is_list($blocks)) {
            throw new ToolFailure('`blocks` must be a list of { type, values } nodes.');
        }

        $editing = $this->editing($entity);
        $this->sameRevision($arguments, $editing);

        try {
            $held = self::held($editing);

            foreach ($blocks as $node) {
                if (Content::isNode($node)) {
                    $key = is_string($node['key'] ?? null) ? $node['key'] : null;
                    $this->refuseStray((string) $node['type'], is_array($node['values'] ?? null) ? $node['values'] : [], $key === null ? [] : ($held[$key] ?? []), $held);
                }
            }
        } catch (BlocksException $refused) {
            throw new ToolFailure($refused->getMessage());
        }

        return $this->writeContent($entity, $blocks, $user, $this->dryRun($arguments));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function previewUrl(array $arguments, ?Authenticatable $user): array
    {
        $entity = $this->entity($arguments);
        $this->authorise($entity, $user, false);
        $minutes = $arguments['minutes'] ?? null;
        $preview = $this->container->make(Preview::class);

        $at = is_string($arguments['at'] ?? null) ? $arguments['at'] : null;

        try {
            $url = $entity instanceof Region
                ? $preview->regionUrl($entity->name, $this->authorId($user), is_int($minutes) && $minutes > 0 ? $minutes : null, $at)
                : $preview->url($entity, $this->authorId($user), is_int($minutes) && $minutes > 0 ? $minutes : null);
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
     * @param  array<string, list<array{id: int, slug: string, title: string}>>  $parents
     * @return array<string, mixed>
     */
    private function summary(Block $block, array $counts, array $parents): array
    {
        $current = $block->currentVersion();

        return [
            'slug' => $block->slug,
            'kind' => $block->kind,
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
            // The published types whose template calls this one: what a change to it can break.
            'used_by' => $parents[$block->slug] ?? [],
        ];
    }

    /**
     * The fields of a schema, through the layout nodes and not into a field: what a template may
     * use as a variable. A repeater's children are its items' keys, listed under it — offered as
     * top-level fields they read as variables a template does not have.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function fields(array $nodes): array
    {
        $types = $this->container->make(FieldTypes::class);
        $fields = [];

        foreach (Schema::valueFields($nodes, $types) as $id => $node) {
            $field = [
                'id' => (string) $id,
                'type' => (string) ($node['type'] ?? ''),
                'label' => is_string($node['label'] ?? null) ? $node['label'] : null,
            ];

            if (($node['localized'] ?? false) === true) {
                $field['localized'] = true;
            }

            if (($node['type'] ?? null) === 'wx-repeater') {
                $field['items'] = $this->fields(Tree::children($node));
            }

            $fields[] = $field;
        }

        return $fields;
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
        if ($entity instanceof Region) {
            return $this->container->make(Regions::class)->title($entity->name);
        }

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
            'kind' => ['type' => 'string', 'enum' => Block::KINDS, 'description' => 'block (default): editors add it to pages. component: templates call it with <x-webx-block type="{slug}">, and the picker leaves it out.'],
            'title' => ['type' => 'string', 'description' => 'What editors see in the picker. Required unless customising a declared place.'],
            'description' => ['type' => ['string', 'null'], 'description' => 'One line under the title in the picker.'],
            'icon' => ['type' => ['string', 'null']],
            'group' => ['type' => 'string', 'enum' => is_array($groups) ? array_values($groups) : [], 'description' => 'The section of the picker.'],
            'sort' => ['type' => 'integer'],
            'allow' => $slugList + ['description' => 'Types allowed inside; null when the block is not a container.'],
            'allowed_in' => $slugList + ['description' => 'Where the block may go: type slugs, "root" for the page itself, "region:header" for the top of that region of the layout; null for anywhere.'],
            'max_per_entity' => ['type' => ['integer', 'null']],
            'is_enabled' => ['type' => 'boolean'],
            'schema' => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Screen nodes; see blocks://fields.'],
            'template' => ['type' => 'string', 'description' => 'Blade. The root element carries data-wx-block="{slug}".'],
            'styles' => ['type' => 'string', 'description' => 'CSS, every selector under .b-{slug}.'],
            'script' => ['type' => ['string', 'null'], 'description' => 'The body of async (el, values) => { … }; null for none. values is what the root prints in data-wx-values — {} unless the template writes data-wx-values="{{ json_encode([...]) }}" with what the script needs.'],
            'sample' => ['type' => 'object', 'description' => 'A value for every field.'],
            'comment' => ['type' => 'string', 'description' => 'A line for the history of versions.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function regions(?Authenticatable $user): array
    {
        $form = $this->container->make(RegionForm::class);
        $preview = $this->container->make(Preview::class);
        $regions = [];

        foreach (array_keys($this->container->make(Regions::class)->declared()) as $name) {
            $row = $form->row($name);

            $regions[] = [
                'name' => $row['name'],
                'title' => $row['title'],
                'description' => $row['description'],
                'allow' => $row['allow'],
                'max' => $row['max'],
                // What the site prints there now, in one word: the blocks, or the markup from code.
                'state' => $row['published'] ? ($row['has_draft'] ? 'published, with a draft' : 'published') : ($row['id'] === null ? 'never saved' : ($row['has_draft'] ? 'draft' : 'unpublished')),
                'published' => $row['published'],
                'has_draft' => $row['has_draft'],
                'fallback' => $row['fallback'],
                'blocks' => $row['count'],
                'preview_url' => $preview->regionUrl($name, $this->authorId($user)),
            ];
        }

        return ['regions' => $regions, 'count' => count($regions)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function regionPublish(array $arguments, ?Authenticatable $user): array
    {
        $region = $this->declaredRegion($arguments);
        $writer = $this->container->make(RegionWriter::class);
        $row = $this->container->make(Regions::class)->find($region->name);

        if ($row === null) {
            throw new ToolFailure("Region [{$region->name}] was never saved: write its blocks with blocks_set_content or blocks_edit_content first.");
        }

        try {
            if ($this->dryRun($arguments)) {
                $failures = $writer->check($row);

                if ($failures !== []) {
                    throw new ToolFailure('Would not publish: '.implode(' ', $failures));
                }

                $fallback = $this->container->make(Regions::class)->fallbackOf($region->name);

                return [
                    'dry_run' => true,
                    'ok' => true,
                    'blocks' => Regions::visible($row->editingTree()),
                    'would' => $row->isPublished()
                        ? 'replace the published blocks of the region'
                        : 'replace the markup from code'.($fallback === null ? '' : " ({$fallback})").' on every page of the site',
                ];
            }

            $published = $writer->publish($region->name, $this->authorId($user), EntityVersion::SOURCE_MCP);
        } catch (RegionRefused $refused) {
            throw new ToolFailure('Not published: '.$refused->sentence());
        }

        return [
            'published' => true,
            'name' => $published->name,
            'version' => $published->publishedVersions()->value('number'),
            'blocks' => Regions::visible($published->blocksTree()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function regionUnpublish(array $arguments): array
    {
        $region = $this->declaredRegion($arguments);
        $row = $this->container->make(Regions::class)->find($region->name);
        $was = $row instanceof Region && $row->isPublished();

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would' => $was ? 'print the markup from code in place of the region\'s blocks' : 'nothing: the region is not on the site'];
        }

        $this->container->make(RegionWriter::class)->unpublish($region->name);

        return ['unpublished' => $was, 'name' => $region->name];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function regionDiscard(array $arguments): array
    {
        $region = $this->declaredRegion($arguments);
        $row = $this->container->make(Regions::class)->find($region->name);
        $has = $row instanceof Region && $row->hasDraft();

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'has_draft' => $has];
        }

        $this->container->make(RegionWriter::class)->discard($region->name);

        return ['discarded' => $has, 'name' => $region->name];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function regionVersions(array $arguments): array
    {
        $region = $this->declaredRegion($arguments);
        $row = $this->container->make(Regions::class)->find($region->name);

        if (! $row instanceof Region) {
            return ['name' => $region->name, 'versions' => []];
        }

        $versions = $row->publishedVersions()->get();
        $authors = Authors::names($versions->map(static fn (EntityVersion $version): ?int => $version->author_id));

        return [
            'name' => $region->name,
            'versions' => $versions->map(static fn (EntityVersion $version): array => [
                'number' => $version->number,
                'created_at' => $version->created_at?->toAtomString(),
                'author' => $version->author_id === null ? null : ($authors[$version->author_id] ?? null),
                'source' => $version->source,
                'comment' => $version->comment,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function regionRestore(array $arguments): array
    {
        $region = $this->declaredRegion($arguments);
        $number = $arguments['number'] ?? null;

        if (! is_int($number) && ! (is_string($number) && ctype_digit($number))) {
            throw new ToolFailure('`number` is required: a version number from blocks_region_versions.');
        }

        if ($this->dryRun($arguments)) {
            $row = $this->container->make(Regions::class)->find($region->name);
            $exists = $row instanceof Region && $row->publishedVersions()->where('number', (int) $number)->exists();

            return ['dry_run' => true, 'would_restore' => $exists ? (int) $number : null];
        }

        try {
            $restored = $this->container->make(RegionWriter::class)->restore($region->name, (int) $number);
        } catch (RegionRefused $refused) {
            throw new ToolFailure('Not restored: '.$refused->sentence());
        }

        return ['restored' => (int) $number, 'into' => 'draft', 'name' => $restored->name, 'revision' => Content::revision($restored->editingTree())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function regionAdopt(array $arguments, ?Authenticatable $user): array
    {
        $region = $this->declaredRegion($arguments);

        if ($user instanceof HasPermissions && ! $user->hasPermission('blocks.manage')) {
            throw new ToolFailure('Adopting writes a block type: it needs [blocks.manage] too.');
        }

        $this->ensureEditing();

        $regions = $this->container->make(Regions::class);
        $fallback = $regions->fallbackOf($region->name);
        $slug = RegionForm::adoptedSlug($region->name);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'fallback' => $fallback, 'would_create' => $slug, 'taken' => Block::query()->where('slug', $slug)->exists()];
        }

        try {
            [, $block] = $this->container->make(RegionWriter::class)->adopt($region->name, $this->authorId($user), BlockVersion::SOURCE_MCP);
        } catch (RegionRefused $refused) {
            throw new ToolFailure('Not adopted: '.$refused->sentence());
        }

        return ['adopted' => $region->name, 'block' => $block->slug, 'from' => $fallback, 'into' => 'draft'];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function declaredRegion(array $arguments): Region
    {
        $name = $arguments['name'] ?? null;

        if (! is_string($name) || $name === '') {
            throw new ToolFailure('`name` is required: the region, as blocks_regions lists it.');
        }

        return $this->entities()->region($name);
    }

    /**
     * The one permission an entity needs on top of the tool's: a region is `blocks.regions`, and
     * everything else is the section's own pair. Nobody to ask — a local run with no user — is
     * not refused, the same rule as the tools themselves.
     */
    private function authorise(Model $entity, ?Authenticatable $user, bool $write): void
    {
        if (! $user instanceof HasPermissions) {
            return;
        }

        $needs = $entity instanceof Region
            ? ['blocks.regions']
            : ($write ? ['blocks.manage'] : ['blocks.view', 'blocks.manage']);

        foreach ($needs as $permission) {
            if ($user->hasPermission($permission)) {
                return;
            }
        }

        throw new ToolFailure('Your administrator account may not do this: it needs ['.implode('] or [', $needs).'].');
    }

    private function regionNames(): string
    {
        $names = array_keys($this->container->make(Regions::class)->declared());

        return $names === [] ? 'none declared (webx-blocks.regions)' : implode(', ', $names);
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
