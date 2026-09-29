<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use Illuminate\Support\Facades\DB;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Exceptions\CatalogException;
use WebxUi\Catalog\Models\Category;

/**
 * A category put somewhere else in the tree: under that parent (null — the top level), before
 * that sibling (null — last). The addresses do not move with it: slugs are flat (decision 23).
 * What moves is the trail, and the journal notes the new parent. The panel's drag and an agent's
 * `catalog_categories_move` both come here.
 */
final class CategoryMover
{
    public function __construct(private readonly Catalog $catalog) {}

    public function move(Category $node, ?Category $parent, ?Category $before): void
    {
        if ($parent !== null && ($parent->is($node) || $parent->isDescendantOf($node))) {
            throw CatalogException::moveIntoItself();
        }

        if ($before !== null && $before->parent_id !== $parent?->getKey()) {
            throw CatalogException::unknownCategory('before_id');
        }

        $from = $node->parent_id === null ? null : Category::withTrashed()->find($node->parent_id);

        DB::transaction(function () use ($node, $parent, $before, $from): void {
            match (true) {
                $before !== null => $node->insertBefore($before),
                $parent !== null => $node->appendTo($parent),
                default => $node->saveAsRoot(),
            };

            if ($from?->getKey() !== $parent?->getKey()) {
                $node->recordHistory(HistoryEntry::UPDATED, [[
                    'field' => 'parent',
                    'from' => $from?->displayName(),
                    'to' => $parent?->displayName(),
                ]]);
            }

            // Visibility is inherited, so a branch moved under a hidden parent hides with it.
            $this->catalog->touchCategory($node);
        });
    }
}
