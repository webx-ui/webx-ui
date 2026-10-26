<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Catalog\Models\Product;
use WebxUi\Configurator\Panel\ConfiguratorsModule;

/**
 * The products of the catalogue, for the field «Catalogue product» of an option in the editor of a
 * configurator (§22.4): found by name, article number or barcode, kept by the article number —
 * which is what moves between stands with a schema, where an id would not.
 *
 * Only products with an article number are offered: an option names the product by it. Asked with
 * the permission of the configurators rather than of the catalogue — whoever builds a configurator
 * picks what it shows, and a name and a picture are all a row gives away.
 */
final class ProductTarget extends RelationTarget
{
    public const string KEY = 'configurator-product';

    public function __construct()
    {
        parent::__construct(
            key: self::KEY,
            model: Product::class,
            permission: ConfiguratorsModule::ID.'.manage',
            label: 'webx-configurator::product.target',
        );
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Product::query()->whereNotNull('sku')->where('sku', '!=', '')->with('images');

        return $query;
    }

    /**
     * The row with the article number under its own name: the field keeps it.
     *
     * @return array{id: int, title: string, subtitle: string|null, thumb: string|null, visible: bool, trashed: bool, sku?: string}
     */
    public function row(Model $record, string $locale): array
    {
        $row = parent::row($record, $locale);

        if ($record instanceof Product) {
            $row['sku'] = (string) $record->sku;
        }

        return $row;
    }

    protected function subtitle(Model $record, string $locale): ?string
    {
        return $record instanceof Product ? $record->sku : null;
    }

    protected function thumb(Model $record): ?string
    {
        return $record instanceof Product ? $record->mainImage()?->thumbUrl(96, 96, 'cover') : null;
    }

    /**
     * By name, article number, barcode or id, as the catalogue's list searches — the article number
     * asked for exactly comes first, which is how the field finds the product it keeps.
     *
     * @param  Builder<Model>  $query
     */
    protected function search(Builder $query, string $term, string $locale): void
    {
        $query->scopes(['matching' => [$term]]);
        $query->orderByRaw($query->getModel()->qualifyColumn('sku').' = ? desc', [$term]);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function order(Builder $query): Builder
    {
        return $query->orderByDesc($query->getModel()->qualifyColumn('id'));
    }
}
