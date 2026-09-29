<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Bulk\Actions;

use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Models\Product;

/**
 * Publish or take off, as the list's `···` does for one product. A product without a main
 * category cannot be published (decision 3), and says so beside its name; the rest of the chunk
 * is published anyway.
 */
final class PublicationAction extends CoreAction
{
    public function __construct(private readonly bool $publish) {}

    public function key(): string
    {
        return $this->publish ? 'publish' : 'unpublish';
    }

    public function apply(Product $product, array $params): array
    {
        if ($product->is_published === $this->publish) {
            return [];
        }

        $product->is_published = $this->publish;
        $product->save();

        // The journal's own event rather than an `updated` with a flag in it, as the form writes it.
        $product->recordHistory(
            $this->publish ? HistoryEntry::PUBLISHED : HistoryEntry::UNPUBLISHED,
            $product->takeHistoryChanges(),
        );

        return [];
    }
}
