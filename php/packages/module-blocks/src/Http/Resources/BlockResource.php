<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Resources;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Blocks\BlockComponents;
use WebxUi\Blocks\BlockShapes;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\Graph;
use WebxUi\Blocks\Panel\Lints;
use WebxUi\Blocks\Rendering\Renderer;

/**
 * A block type as the section shows it: the row, the two pointers as version summaries, the
 * content the editor works on, a thumbnail drawn on the sample, the lints of that content,
 * how many pages hold it, and — for the call graph of the components spec (§3.10) — its kind,
 * the types its template calls and the published types that call it.
 *
 * The content is the draft's when there is one and the published version's otherwise — what
 * the editor opens. The thumbnail is the published look when there is one: the list says
 * what the site shows, and a draft in progress does not change that until it is published.
 *
 * @mixin Block
 */
final class BlockResource extends JsonResource
{
    /**
     * @param  array<string, int>  $usage  Slug → number of pages, for every type at once.
     * @param  array<int, string>  $authors  Author id → name, for every version at once.
     * @param  array<string, list<array{id: int, slug: string, title: string}>>|null  $parents  Slug → the
     *                                                                                          published types that call it, for every type at once; read here when not given.
     */
    public function __construct(
        Block $block,
        private readonly array $usage = [],
        private readonly array $authors = [],
        private readonly bool $withContent = true,
        private readonly ?array $parents = null,
    ) {
        parent::__construct($block);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Block $block */
        $block = $this->resource;

        $draft = $block->draftVersion;
        $published = $block->publishedVersion;
        $current = $draft ?? $published;

        $payload = [
            'id' => $block->id,
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
            'draft' => $draft === null ? null : BlockVersionResource::summary($draft, $this->authors),
            'published' => $published === null ? null : BlockVersionResource::summary($published, $this->authors),
            'usage_count' => $this->usage[$block->slug] ?? 0,
            'uses' => $current?->calls() ?? [],
            'used_by' => ($this->parents ?? Container::getInstance()->make(Graph::class)->parents())[$block->slug] ?? [],
            'thumbnail' => $this->thumbnail($block, $published ?? $draft),
            'created_at' => $block->created_at?->toAtomString(),
            'updated_at' => $block->updated_at?->toAtomString(),
        ];

        if ($this->withContent) {
            $content = $current?->content() ?? ['schema' => [], 'template' => '', 'styles' => '', 'script' => null, 'sample' => []];

            $payload['content'] = $content;
            $payload['warnings'] = Lints::check($block->slug, $content['template'], $content['styles']);

            // What the editor of a component shows under the template: the module's place, when
            // one declared it, and the fields of every data shape its input names.
            $declared = Container::getInstance()->make(BlockComponents::class)->get($block->slug);

            $payload['declared'] = $declared === null ? null : self::declaration($declared, true);
            $payload['shape'] = (object) Container::getInstance()->make(BlockShapes::class)->describe($content['schema']);
        }

        return $payload;
    }

    /**
     * A declared place as the section lists it (§3.10): no schema — the card shows what the
     * module called it, and "Customise" brings the schema along.
     *
     * @param  array{slug: string, module: string, fallback: string, title: string, description: string|null, schema: list<array<string, mixed>>}  $declared
     * @return array{slug: string, module: string, title: string, description: string|null, fallback: string, customised: bool}
     */
    public static function declaration(array $declared, bool $customised): array
    {
        return [
            'slug' => $declared['slug'],
            'module' => $declared['module'],
            'title' => (string) BlockComponents::words($declared['title']),
            'description' => BlockComponents::words($declared['description']),
            'fallback' => $declared['fallback'],
            'customised' => $customised,
        ];
    }

    /**
     * The block on its sample, ready for an iframe: the HTML with the markers and the styles
     * beside it. Null for a type that has no version at all — nothing to draw.
     *
     * @return array{html: string, styles: string}|null
     */
    private function thumbnail(Block $block, ?BlockVersion $version): ?array
    {
        if ($version === null) {
            return null;
        }

        $type = BlockType::fromModels($block, $version);

        return [
            'html' => Container::getInstance()->make(Renderer::class)->draw($type, $type->sample),
            'styles' => $type->styles,
        ];
    }
}
