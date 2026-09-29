<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;
use WebxUi\Catalog\Parts\PartSchema;
use WebxUi\Catalog\Parts\ProductPart;

/**
 * A satellite's part as small as one can be: a note per product in a table of its own. Writing
 * the word `explode` makes it fail after it wrote, which is what a rollback has to undo.
 */
final class NotePart implements ProductPart
{
    public function key(): string
    {
        return 'notes';
    }

    public function describe(): PartSchema
    {
        return new PartSchema('Notes', 'notes', [new PartField('text', 'string', 'Note', ['max:20'])]);
    }

    public function rules(): array
    {
        return ['text' => ['nullable', 'string', 'max:20']];
    }

    public function read(Collection $products): array
    {
        return DB::table('test_notes')
            ->whereIn('product_id', $products->modelKeys())
            ->pluck('text', 'product_id')
            ->map(static fn (mixed $text): array => ['text' => $text])
            ->all();
    }

    public function write(Product $product, array $input): array
    {
        $before = DB::table('test_notes')->where('product_id', $product->id)->value('text');
        $after = $input['text'] ?? null;

        DB::table('test_notes')->updateOrInsert(['product_id' => $product->id], ['text' => $after]);

        if ($after === 'explode') {
            throw new RuntimeException('The part failed after writing.');
        }

        return $before === $after ? [] : [['field' => 'notes.text', 'from' => $before, 'to' => $after]];
    }
}
