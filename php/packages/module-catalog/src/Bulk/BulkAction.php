<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Bulk;

use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;

/**
 * One thing that can be done to many products at once (§11.4): publish, file under a category,
 * delete — and whatever a satellite registers, a label or a stock status.
 *
 * The runner chooses the products, cuts them into chunks, opens a transaction per chunk and a
 * savepoint per product, and puts everything written under one run of the journal. An action only
 * says what happens to one product: a product it refuses (a throw) is an error beside that
 * product's name, and its neighbours in the chunk go on.
 */
interface BulkAction
{
    /** `[a-z0-9-]`, unique among the actions: what `POST /bulk` and `catalog_bulk` name it by. */
    public function key(): string;

    /** The words on the button, in the panel's language. */
    public function label(): string;

    /** The permission the action is behind — `catalog.manage`, or `catalog.delete` for a delete. */
    public function permission(): string;

    /** Whether it acts on the products in «Deleted» rather than on the live ones — a restore. */
    public function trashed(): bool;

    /**
     * What the action asks for before it runs — a category, a label — told to the panel, which
     * draws a field for each, and to an agent. Empty for an action that asks nothing.
     *
     * @return list<PartField>
     */
    public function params(): array;

    /**
     * Laravel rules for the params, checked once before anything is chosen.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Do it to one product, inside its savepoint.
     *
     * What comes back goes into the journal as the product's `updated` row under the run; an
     * action whose change the model journals itself (a delete, a publication written with its own
     * event) returns nothing.
     *
     * @param  array<string, mixed>  $params  already checked
     * @return list<array{field: string, from: mixed, to: mixed, label?: string}>
     */
    public function apply(Product $product, array $params): array;
}
