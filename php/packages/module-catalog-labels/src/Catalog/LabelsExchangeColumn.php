<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Catalog\Dictionaries\Dictionary;
use WebxUi\Catalog\Exchange\DescribesCell;
use WebxUi\Catalog\Exchange\ExchangeColumn;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\Lookup;
use WebxUi\Catalog\Exchange\RowError;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Slug;

/**
 * The labels of a product in an exchange file (§7.2 of the exchange spec): `labels`, codes through
 * `;` — `sale;new` — into `labels.ids`.
 *
 * A code is what a label is known by everywhere (an address, a template), so it is also what a
 * file carries: the export writes codes and the import reads them. A code is brought to its shape
 * before it is looked up, as the panel brings a typed one (#125): `Sale Price` and `распродажа`
 * find `sale-price` and `rasprodazha`. A label in the bin is still on its products, and still
 * found by its code. An unknown code is created with `create_missing` — named by the code as the
 * file wrote it, which a person renames in the panel afterwards.
 */
final class LabelsExchangeColumn implements DescribesCell, ExchangeColumn
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

    public function cellFormat(): string
    {
        return 'Label codes through ";" (sale;new), brought to a code as the panel brings one: "Sale Price" is sale-price. A missing code is created with create_missing, named by what the file wrote.';
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

        foreach (explode(';', $cell) as $typed) {
            $typed = trim($typed);

            if ($typed === '') {
                continue;
            }

            $code = $this->codeOf($typed);

            if ($code === '') {
                throw new RowError((string) __('webx-catalog-labels::exchange.bad-code', ['code' => $typed]));
            }

            $ids[] = $book->find($code) ?? $this->create($code, $typed, $book, $context);
        }

        return array_values(array_unique($ids));
    }

    /**
     * What the label model keeps a typed code as (`Dictionary::codeOf()`), so a file finds the label
     * the panel saved. The language is asked for only when it matters: it is a read of `locales`.
     */
    private function codeOf(string $typed): string
    {
        $locale = Slug::isLatin($typed) ? 'en' : app(Locales::class)->defaultCode();

        return Slug::code($typed, $locale, Dictionary::CODE_LENGTH);
    }

    /**
     * @throws RowError
     */
    private function create(string $code, string $typed, Lookup $book, ImportContext $context): int
    {
        if (! $context->createMissing) {
            throw new RowError((string) __('webx-catalog-labels::exchange.unknown', ['code' => $code]));
        }

        $permission = Label::categoryKind()->manage;

        if (! $context->can($permission)) {
            throw RowError::because('create-forbidden', ['permission' => $permission]);
        }

        try {
            // The shared path of the panel and the agent; the code is made from the slug it is given.
            $label = app(CategoryForm::class)->create(Label::class, $typed, $code);
        } catch (ValidationException $refused) {
            throw new RowError((string) collect($refused->errors())->flatten()->first());
        }

        $context->made(self::BAG);
        $book->add($code, (int) $label->getKey());

        return (int) $label->getKey();
    }
}
