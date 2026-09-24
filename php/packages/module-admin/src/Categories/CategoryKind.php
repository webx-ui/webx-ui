<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * What one module's categories are, for the code that is the same in every module.
 *
 * Returned by the category model itself ({@see IsCategory::categoryKind()}), so a route, a
 * controller, a link source and a set of tools all read one description rather than each being
 * handed its half: the blog says once that its categories are rubrics, that their screen is
 * `blog.category-form` and that `blog.taxonomy.manage` edits them.
 *
 * The words are the module's. "Rubric" is what the blog calls a category, and an agent reading
 * `rubrics_list` is told about rubrics and articles rather than about categories and items.
 */
final readonly class CategoryKind
{
    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $view  Any of these reads the list — the item form needs it too.
     * @param  string  $manage  Everything that writes.
     * @param  (Closure(): string)|null  $prefix  Where the module's addresses start; null for categories without an address.
     * @param  string  $noun  One category, in the module's word: "rubric".
     * @param  string  $plural  The key of the list in answers: "rubrics".
     * @param  string  $items  What is filed under a category, plural: "articles".
     */
    public function __construct(
        public string $model,
        public string $screen,
        public array $view,
        public string $manage,
        public ?Closure $prefix = null,
        public string $noun = 'category',
        public string $plural = 'categories',
        public string $items = 'items',
    ) {}

    /** The key the number of items travels under: `articles_count`. */
    public function countKey(): string
    {
        return $this->items.'_count';
    }

    public function prefix(): ?string
    {
        return $this->prefix === null ? null : ($this->prefix)();
    }
}
