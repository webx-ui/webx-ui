<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
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
 *     $page->storeBlocks($sent);  // what a save keeps: values cast by their field types
 *
 * @mixin Model
 */
trait HasBlocks
{
    /**
     * A draft holding blocks is checked once more on its way to the site: the values against
     * their field types, the containers against what they take ({@see ContentValues::check()}).
     * Every save checks already; this is for a draft saved before those checks existed, or by
     * something that wrote the column itself.
     */
    public static function bootHasBlocks(): void
    {
        static::registerModelEvent('publishing', static function (Model $model): void {
            if (! method_exists($model, 'draftValues') || ! method_exists($model, 'blocksColumn') || ! method_exists($model, 'blocksRoot')) {
                return;
            }

            $column = $model->blocksColumn();
            $draft = $model->draftValues();

            if (is_array($draft[$column] ?? null)) {
                Container::getInstance()->make(ContentValues::class)->check($draft[$column], $column, $model->blocksRoot());
            }
        });
    }

    /**
     * What the top level of this entity is called in a type's `allowed_in`: `root` for a page and
     * anything like one; a region of the layout says `region:<name>`.
     */
    public function blocksRoot(): string
    {
        return 'root';
    }

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
     * A tree on its way in, every value cast by the field type its block's schema names — what
     * a save writes rather than what the editor sent ({@see ContentValues}).
     *
     * It is here rather than in a mutator because a tree does not reach this entity through
     * its own column: the panel saves a draft, which is one JSON payload with the blocks
     * inside it, and no cast of this model ever sees them. The module that owns the screen
     * calls this with the value of its `wx-blocks` field; the agent's tools do the same step
     * on their way through `BlockTools`.
     *
     * Checked first, and refused with a {@see ValidationException} keyed `blocks.<key>.<field>`
     * when a value breaks its field's rules or a block stands where it may not.
     *
     * @param  iterable<array-key, mixed>|null  $tree
     * @return list<mixed>
     */
    public function storeBlocks(?iterable $tree): array
    {
        // What the entity holds until now — the draft, or the column when there is none — so that a
        // value it already had is not held to the checks only a new value has to pass.
        $column = $this->blocksColumn();
        $draft = method_exists($this, 'draftValues') ? $this->draftValues() : [];
        $before = is_array($draft[$column] ?? null) ? $draft[$column] : $this->blocksTree();

        return Container::getInstance()->make(ContentValues::class)->store($tree, $column, $this->blocksRoot(), $before);
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
