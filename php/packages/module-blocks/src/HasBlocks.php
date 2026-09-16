<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use WebxUi\Blocks\Rendering\Renderer;

/**
 * An entity whose content is a tree of blocks.
 *
 * No table of its own: the tree is a JSON column on the entity (`blocks`), added by the
 * `$table->blocks()` macro along with the `draft` column the versions mechanism uses. The
 * trait is the whole integration on the model side — a page, an article, a product add it and
 * know nothing else about blocks.
 *
 *     class Page extends Model
 *     {
 *         use HasBlocks;
 *     }
 *
 *     $page->blocks;          // the tree, as an array
 *     $page->renderBlocks();  // the HTML
 *     $page->blockTypes();    // ['hero', 'section', 'text']
 *
 * @mixin Model
 */
trait HasBlocks
{
    public function initializeHasBlocks(): void
    {
        $this->mergeCasts([$this->blocksColumn() => 'array']);
    }

    /** Which column holds the tree. Override in a model that spells it differently. */
    public function blocksColumn(): string
    {
        return 'blocks';
    }

    /**
     * The tree as a list of nodes, whatever the column holds.
     *
     * @return list<array<string, mixed>>
     */
    public function blocksTree(): array
    {
        $tree = $this->getAttribute($this->blocksColumn());

        if (! is_array($tree)) {
            return [];
        }

        return array_values(array_filter($tree, static fn (mixed $node): bool => Content::isNode($node)));
    }

    public function renderBlocks(): HtmlString
    {
        return Container::getInstance()->make(Renderer::class)->render($this->blocksTree(), $this);
    }

    /**
     * The distinct block types this entity uses, nested ones included.
     *
     * @return list<string>
     */
    public function blockTypes(): array
    {
        return Content::types($this->blocksTree());
    }
}
