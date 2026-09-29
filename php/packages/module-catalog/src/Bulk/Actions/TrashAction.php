<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Bulk\Actions;

use WebxUi\Catalog\Models\Product;

/**
 * Into «Deleted», or back out of it — behind `catalog.delete`, as the one-product doors are
 * (§11.5). The model journals both itself (`deleted`, `restored`); a product whose main category
 * is gone comes back without one and unpublished (§6.3).
 */
final class TrashAction extends CoreAction
{
    public function __construct(private readonly bool $delete) {}

    public function key(): string
    {
        return $this->delete ? 'delete' : 'restore';
    }

    public function permission(): string
    {
        return 'catalog.delete';
    }

    public function trashed(): bool
    {
        return ! $this->delete;
    }

    public function apply(Product $product, array $params): array
    {
        $this->delete ? $product->delete() : $product->restore();

        // A restore saves the row, and the model holds what that save changed for a row nobody is
        // going to write: `restored` already says it.
        $product->takeHistoryChanges();

        return [];
    }
}
