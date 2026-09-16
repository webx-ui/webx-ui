<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Resources;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\Lints;
use WebxUi\Blocks\Rendering\Renderer;

/**
 * A block type as the section shows it: the row, the two pointers as version summaries, the
 * content the editor works on, a thumbnail drawn on the sample, the lints of that content,
 * and how many pages hold it.
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
     */
    public function __construct(
        Block $block,
        private readonly array $usage = [],
        private readonly array $authors = [],
        private readonly bool $withContent = true,
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
            'thumbnail' => $this->thumbnail($block, $published ?? $draft),
            'created_at' => $block->created_at?->toAtomString(),
            'updated_at' => $block->updated_at?->toAtomString(),
        ];

        if ($this->withContent) {
            $content = $current?->content() ?? ['schema' => [], 'template' => '', 'styles' => '', 'script' => null, 'sample' => []];

            $payload['content'] = $content;
            $payload['warnings'] = Lints::check($block->slug, $content['template'], $content['styles']);
        }

        return $payload;
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
