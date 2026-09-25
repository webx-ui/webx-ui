<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Mcp;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Blocks\Models\Block;
use WebxUi\Mcp\McpResource;

/**
 * What an agent reads before it writes (§18): the house rules of a block, the catalogue of
 * what exists so that it reuses rather than duplicates, the node types a schema may use, and
 * what this particular site provides.
 */
final class BlockResources
{
    private const GUIDELINES = __DIR__.'/../../resources/mcp/guidelines.md';

    /** The node types every screen has, with the one line an agent needs about each. */
    private const NODES = [
        'wx-input' => ['kind' => 'field', 'note' => 'One line of text. props: placeholder, maxlength.'],
        'wx-textarea' => ['kind' => 'field', 'note' => 'Several lines of plain text. props: rows, placeholder.'],
        'wx-rich-text' => ['kind' => 'field', 'note' => 'A formatted document, stored as HTML — headings, lists, tables, links, pictures from the library. The template prints it with {!! !!}. props: placeholder, minHeight, tools.'],
        'wx-input-number' => ['kind' => 'field', 'note' => 'A number. props: min, max, step.'],
        'wx-switch' => ['kind' => 'field', 'note' => 'A boolean.'],
        'wx-checkbox' => ['kind' => 'field', 'note' => 'A boolean with a label beside the box.'],
        'wx-select' => ['kind' => 'field', 'note' => 'One of props.options: [{ value, label }].'],
        'wx-radio-group' => ['kind' => 'field', 'note' => 'One of props.options, all shown.'],
        'wx-date-picker' => ['kind' => 'field', 'note' => 'A date, stored as YYYY-MM-DD.'],
        'wx-color-picker' => ['kind' => 'field', 'note' => 'A colour, stored as a CSS colour string.'],
        'wx-checkbox-group' => ['kind' => 'field', 'note' => 'Several of props.options, as a list of their values. props: options, min, max, inline.'],
        'wx-segmented' => ['kind' => 'field', 'note' => 'One of a few props.options, drawn as a row of buttons — for two to five short choices.'],
        'wx-slider' => ['kind' => 'field', 'note' => 'A number on a track. With props.range it is a pair, [from, to]. props: min (0), max (100), step, range, marks.'],
        'wx-rate' => ['kind' => 'field', 'note' => 'Stars, a number from 0 to props.max (5). props: max, allowHalf.'],
        'wx-time-picker' => ['kind' => 'field', 'note' => 'A time of day, stored as HH:mm (HH:mm:ss with props.seconds).'],
        'wx-date-time-picker' => ['kind' => 'field', 'note' => 'A moment, stored as ISO 8601 with its offset (2026-09-25T08:06:00+00:00) in the site\'s timezone.'],
        'wx-date-range-picker' => ['kind' => 'field', 'note' => 'Two dates, stored as [start, end], each YYYY-MM-DD.'],
        'wx-tags-input' => ['kind' => 'field', 'note' => 'A list of short strings. props: suggestions, allowCreate (false keeps it to the suggestions), max, duplicates.'],
        'wx-autocomplete' => ['kind' => 'field', 'note' => 'One line of text with props.options ([{ value }]) offered while typing; anything may be typed.'],
        'wx-icon-picker' => ['kind' => 'field', 'note' => 'The name of an icon from the panel\'s set, as a string.'],
        'wx-code-editor' => ['kind' => 'field', 'note' => 'Source text — JSON, HTML, CSS and the like — kept as a string. props: language, lineNumbers, minHeight.'],
        'wx-cascader' => ['kind' => 'field', 'note' => 'A place in the tree of props.options ([{ value, label, children }]), stored as the path of values from the root; with props.emitPath: false, the last value alone. Leaves only unless props.checkStrictly.'],
        'wx-tree-select' => ['kind' => 'field', 'note' => 'The key of a node of props.nodes ([{ id, label, children }]); a list of keys with props.multiple.'],
        'wx-transfer' => ['kind' => 'field', 'note' => 'Several of props.items ([{ value, label }]), moved from one list into another; stored as a list of their values.'],
        'wx-link' => ['kind' => 'field', 'note' => 'Somewhere to go: a page of this site, an address, or nowhere. Stored as { target, entity_type, entity_id, url, hash, new_tab, rel } — the entity, never its address. The template gets url worked out when the block is printed, with the anchor already on the end of it, plus label, available, new_tab and rel. props: allowNone, attributes.'],
        'wx-repeater' => ['kind' => 'field', 'note' => 'A list of records; children are the fields of one record. Rows start folded to `#N · <itemLabel>`. props: itemLabel, min, max, addLabel, collapsed.'],
        'wx-media' => ['kind' => 'field', 'note' => 'A file from the media library; the value is { path, alt, title }, and the template also gets url, worked out when the block is printed. props: accept.'],
        'wx-gallery' => ['kind' => 'field', 'note' => 'Pictures from the media library, in the order they were dragged into; a list of wx-media values, each resolved with url, thumb, name, size, width and height. Use this rather than a wx-repeater around a wx-media. props: max, min, columns, aspect, captions.'],
        'wx-file' => ['kind' => 'field', 'note' => 'One file from the media library, drawn as a card rather than a picture — for a document. The same value and the same resolved keys as wx-media. props: accept, captions.'],
        'wx-files' => ['kind' => 'field', 'note' => 'Files from the media library, in the order they were dragged into — the downloads hanging off a page. A list of wx-media values, resolved like a gallery. props: accept, max, min, captions.'],
        'wx-collection' => ['kind' => 'field', 'note' => 'Records another module keeps — the questions of the FAQ, say — picked by category rather than one by one; props.source names the module. Stored as { categories, limit, filter, markup }: the choice, never the records; no categories is all of them, markup null is the default. The template gets it worked out: items (each with id, anchor, categories and whatever the source adds — question and answer for faq), groups ({ id, title, items }) for a filter, and filter. Loop over $x[\'items\'] and print HTML with {!! !!}.'],
        'wx-data' => ['kind' => 'field', 'note' => 'Components only: a value the calling template passes, not one an editor types — :card="$card". props.shape names its form; blocks_get says what it holds.'],
        'wx-slot' => ['kind' => 'field', 'note' => 'Components only: a named slot, <x-slot:id>…</x-slot:id> in the tag. Print {{ $id }}; empty when not passed. $slot needs no node.'],
        'wx-blocks' => ['kind' => 'field', 'note' => 'Nested blocks — makes the type a container. props: allow (type slugs), max. Print with @blocks(\'id\').'],
        'wx-card' => ['kind' => 'layout', 'note' => 'Groups children under props.title.'],
        'wx-tabs' => ['kind' => 'layout', 'note' => 'Holds wx-tab children.'],
        'wx-tab' => ['kind' => 'layout', 'note' => 'One tab; props.label.'],
        'wx-row' => ['kind' => 'layout', 'note' => 'Children side by side, in wx-col nodes.'],
        'wx-col' => ['kind' => 'layout', 'note' => 'One column of a row; props.span out of 24.'],
        'wx-divider' => ['kind' => 'layout', 'note' => 'A line, with props.label.'],
        'wx-heading' => ['kind' => 'display', 'note' => 'A heading over the fields that follow; label is its text. props: level, size.'],
        'wx-text' => ['kind' => 'display', 'note' => 'A paragraph of help text in props.text.'],
        'wx-alert' => ['kind' => 'display', 'note' => 'A callout; props.title, props.type.'],
    ];

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'blocks://guidelines',
                'Block guidelines',
                'How a block type is written here: schema, template, styles, script, sample, and the loop to follow. Read first.',
                static fn (): string => (string) file_get_contents(self::GUIDELINES),
                'text/markdown',
            ),

            new McpResource(
                'blocks://schema',
                'How a block schema is written',
                'What a schema is, what a node is, and every node type a schema may hold, with a worked example. The same page the panel shows behind the ? beside the schema editor.',
                static fn (): string => (string) trans('webx-blocks::help.schema'),
                'text/markdown',
            ),

            new McpResource(
                'blocks://catalog',
                'Block catalog',
                'Every block type of this site with its fields and sample values — what already exists, to reuse before making more.',
                fn (): array => $this->catalog(),
            ),

            new McpResource(
                'blocks://fields',
                'Block field types',
                'The node types a block schema may use, with what each holds and its props.',
                fn (): array => $this->fields(),
            ),

            new McpResource(
                'blocks://site',
                'Blocks on this site',
                'What this site provides to blocks: picker groups, what webx.provide() offers to scripts, the nesting limit, the entities that hold blocks.',
                fn (): array => $this->site(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $types = [];

        foreach (Block::query()->with(['draftVersion', 'publishedVersion'])->orderBy('position')->orderBy('slug')->get() as $block) {
            $version = $block->currentVersion();
            $content = $version?->content();

            $types[] = [
                'slug' => $block->slug,
                'title' => $block->title,
                'description' => $block->description,
                'group' => $block->group,
                'allow' => $block->allow,
                'allowed_in' => $block->allowed_in,
                'is_enabled' => $block->is_enabled,
                'published' => $block->publishedVersion !== null,
                'schema' => $content['schema'] ?? [],
                'sample' => $content['sample'] ?? [],
            ];
        }

        return ['types' => $types];
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(): array
    {
        $registered = $this->container->make(FieldTypes::class)->names();
        $nodes = [];

        foreach (self::NODES as $type => $about) {
            // Field types come and go with modules: `wx-media` is only here with the media module.
            if ($about['kind'] === 'field' && $type !== 'wx-blocks' && ! in_array($type, $registered, true)) {
                continue;
            }

            $nodes[$type] = $about;
        }

        foreach ($registered as $type) {
            $nodes[$type] ??= ['kind' => 'field', 'note' => 'Registered by the site; ask its owners what it holds.'];
        }

        return [
            'node' => '{ "id": "title", "type": "wx-input", "label": "Title", "props": {}, "children": [] } — id is the variable in the template and the key in the values.',
            'types' => $nodes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function site(): array
    {
        $config = $this->container->make(Config::class);
        $groups = $config->get('webx-blocks.groups', []);
        $provides = $config->get('webx-blocks.provides', []);

        return [
            'groups' => is_array($groups) ? array_values(array_map('strval', $groups)) : [],
            'provides' => is_array($provides) ? array_values(array_map('strval', $provides)) : [],
            'max_depth' => (int) $config->get('webx-blocks.max_depth', 5),
            'editing' => (bool) $config->get('webx-blocks.editing', true),
            'entities' => array_keys($this->container->make(Entities::class)->names()),
            'preview_minutes' => (int) $config->get('webx-blocks.preview.ttl', 60),
        ];
    }
}
