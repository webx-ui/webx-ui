<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use LogicException;
use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Catalog\Exchange\DescribesCell;
use WebxUi\Catalog\Exchange\ExchangeColumn;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\RowError;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\Localization\Locales;

/**
 * The brand of a product in an exchange file (§7.2 of the exchange spec): `brand` into `brand.id`.
 *
 * A file names a brand the way a person would: by its slug, else by its name without regard to
 * case — `apple` and `Apple` are one brand — or by `#id`. The export writes the slug of the default
 * language, and the name or `#id` only for a brand without one, so whatever it wrote reads back as
 * the same brand. A name nobody has is created with `create_missing`, visible and with an address
 * made of the name, as the panel makes one.
 */
final class BrandExchangeColumn implements DescribesCell, ExchangeColumn
{
    public function key(): string
    {
        return BrandPart::KEY;
    }

    public function label(): string
    {
        return (string) __('webx-catalog-brands::product.brand');
    }

    public function field(): string
    {
        return BrandPart::KEY.'.id';
    }

    public function localized(): bool
    {
        return false;
    }

    public function cellFormat(): string
    {
        return 'The brand: its slug, else its name case aside, or #id. A missing one is created with create_missing.';
    }

    public function export(Collection $products, ?string $locale): array
    {
        $default = app(Locales::class)->defaultCode();
        $ids = $products->map(static fn (Product $product): int => (int) $product->id)->all();
        $of = Brands::of($ids);
        $names = null;
        $cells = [];

        foreach ($ids as $id) {
            $brand = $of[$id] ?? null;

            if ($brand === null) {
                $cells[$id] = '';

                continue;
            }

            $slug = $brand->getTranslation('slug', $default, false);

            if (is_string($slug) && $slug !== '') {
                $cells[$id] = $slug;

                continue;
            }

            // Without an address the name is all a person knows it by — unless it would not read back.
            $names ??= BrandNames::read($default);
            $cells[$id] = $names->unique($brand) ?? '#'.$brand->id;
        }

        return $cells;
    }

    public function parse(string $cell, ImportContext $context): mixed
    {
        $cell = trim($cell);
        $names = BrandNames::of($context);

        if (preg_match('/^#(\d+)$/', $cell, $match) === 1) {
            $id = (int) $match[1];

            return $names->has($id) ? $id : throw new RowError((string) __('webx-catalog-brands::exchange.unknown-id', ['id' => $id]));
        }

        return $names->find($cell) ?? $this->create($cell, $names, $context);
    }

    /**
     * @throws RowError
     */
    private function create(string $name, BrandNames $names, ImportContext $context): int
    {
        if (! $context->createMissing) {
            throw new RowError((string) __('webx-catalog-brands::exchange.unknown', ['name' => $name]));
        }

        $permission = Brand::categoryKind()->manage;

        if (! $context->can($permission)) {
            throw RowError::because('create-forbidden', ['permission' => $permission]);
        }

        try {
            // The panel's own path: the address is made of the name, and the registry may refuse it.
            $brand = app(CategoryForm::class)->create(Brand::class, [$context->defaultLocale => $name]);
        } catch (ValidationException $refused) {
            throw new RowError((string) collect($refused->errors())->flatten()->first());
        }

        if (! $brand instanceof Brand) {
            throw new LogicException('The shared category form made something other than the brand it was asked for.');
        }

        $context->made(BrandNames::BAG);
        $names->add($brand);

        return (int) $brand->getKey();
    }
}
