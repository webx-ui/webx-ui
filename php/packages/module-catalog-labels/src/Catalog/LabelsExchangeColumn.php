<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Catalog\Dictionaries\Dictionary;
use WebxUi\Catalog\Exchange\ExchangeColumn;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\Lookup;
use WebxUi\Catalog\Exchange\RowError;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogLabels\Models\Label;

/**
 * The labels of a product in an exchange file (§7.2 of the exchange spec): `labels`, codes through
 * `;` — `sale;new` — into `labels.ids`.
 *
 * A code is what a label is known by everywhere (an address, a template), so it is also what a
 * file carries: the export writes codes and the import reads them, case aside. A label in the bin
 * is still on its products, and still found by its code. An unknown code is created with
 * `create_missing` — named by its code, which a person renames in the panel afterwards.
 */
final class LabelsExchangeColumn implements ExchangeColumn
{
    private const BAG = 'labels';

    public function key(): string
    {
        return LabelsPart::KEY;
    }

    public function label(): string
    {
        return (string) __('webx-catalog-labels::product.labels');
    }

    public function field(): string
    {
        return LabelsPart::KEY.'.ids';
    }

    public function localized(): bool
    {
        return false;
    }

    public function export(Collection $products, ?string $locale): array
    {
        $ids = $products->map(static fn (Product $product): int => (int) $product->id)->all();
        $on = Labels::on($ids, withTrashed: true);
        $cells = [];

        foreach ($ids as $id) {
            $cells[$id] = implode(';', array_map(static fn (Label $label): string => $label->code, $on[$id] ?? []));
        }

        return $cells;
    }

    public function parse(string $cell, ImportContext $context): mixed
    {
        $book = Lookup::of($context, self::BAG, static fn (): array => Label::withTrashed()->pluck('id', 'code')
            ->mapWithKeys(static fn (mixed $id, mixed $code): array => [mb_strtolower((string) $code) => (int) $id])
            ->all());
        $ids = [];

        foreach (explode(';', $cell) as $code) {
            $code = mb_strtolower(trim($code));

            if ($code !== '') {
                $ids[] = $book->find($code) ?? $this->create($code, $book, $context);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @throws RowError
     */
    private function create(string $code, Lookup $book, ImportContext $context): int
    {
        if (! $context->createMissing) {
            throw new RowError((string) __('webx-catalog-labels::exchange.unknown', ['code' => $code]));
        }

        $permission = Label::categoryKind()->manage;

        if (! $context->can($permission)) {
            throw RowError::because('create-forbidden', ['permission' => $permission]);
        }

        if (preg_match(Dictionary::CODE, $code) !== 1 || strlen($code) > Dictionary::CODE_LENGTH) {
            throw new RowError((string) __('webx-catalog-labels::exchange.bad-code', ['code' => $code, 'max' => Dictionary::CODE_LENGTH]));
        }

        try {
            // The shared path of the panel and the agent; the code is made from the slug it is given.
            $label = app(CategoryForm::class)->create(Label::class, $code, $code);
        } catch (ValidationException $refused) {
            throw new RowError((string) collect($refused->errors())->flatten()->first());
        }

        $context->made(self::BAG);
        $book->add($code, (int) $label->getKey());

        return (int) $label->getKey();
    }
}
