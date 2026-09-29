<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Parts;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Product;

/**
 * A satellite's share of the product form (§7.4 of the spec, §11 of the family's architecture).
 *
 * The form is one, and several modules write into it: stock, brand, labels, properties. Each
 * keeps its data in its own table and says here how to read it for a page of products and how
 * to write what the editor sent. The core opens the transaction, saves the product, hands each
 * part its share of the input, and writes one journal row with everybody's changes. Any part
 * that throws takes the whole save back with it.
 *
 * The tab or the field itself is a patch of the screen `catalog.product-form`, and its fields are
 * named `<key>.<field>` there — `stock.status`, `brand.id`. That prefix is how the core knows
 * whose a value is; a field on the screen that no part claims is a mistake it refuses loudly
 * rather than a value it drops without a word.
 */
interface ProductPart
{
    /** `[a-z0-9-]`, unique among the parts: the prefix of this part's fields on the screen. */
    public function key(): string;

    /** What the part holds, for the agent's resource (§12.2) and for anybody asking. */
    public function describe(): PartSchema;

    /**
     * Laravel rules on top of the screen's own, keyed by the field without the prefix.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * The values of the part for many products at once — a page of the list, an export.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array<string, mixed>> Product id → field (without the prefix) → value.
     */
    public function read(Collection $products): array;

    /**
     * Write what was sent — only the fields that travelled — inside the form's transaction.
     *
     * @param  array<string, mixed>  $input  Field (without the prefix) → value, already checked.
     * @return list<array{field: string, from: mixed, to: mixed, label?: string}> What really changed, for the journal.
     */
    public function write(Product $product, array $input): array;
}
