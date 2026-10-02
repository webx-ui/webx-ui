<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Audit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Contracts\AuditCheck;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;

/**
 * What the catalogue brings to the site audit (audit spec §7): what a shop forgets to fill in —
 * a published category with no text, a published product with no picture or no price. One class,
 * three checks: each is a query, and one finding per check with the first fifty records in its
 * table, because a catalogue has thousands and the point is the count and where to start.
 */
final readonly class CatalogChecks implements AuditCheck
{
    public const CATEGORY_DESCRIPTION = 'catalog.category_description';

    public const PRODUCT_IMAGE = 'catalog.product_image';

    public const PRODUCT_PRICE = 'catalog.product_price';

    /** id => severity */
    public const CHECKS = [
        self::CATEGORY_DESCRIPTION => Severity::NOTICE,
        self::PRODUCT_IMAGE => Severity::WARNING,
        self::PRODUCT_PRICE => Severity::WARNING,
    ];

    private const ROWS = 50;

    public function __construct(private string $id) {}

    public function textNamespace(): string
    {
        return 'webx-catalog';
    }

    public function id(): string
    {
        return $this->id;
    }

    public function group(): string
    {
        return 'catalog';
    }

    public function severity(): string
    {
        return self::CHECKS[$this->id];
    }

    public function needs(): array
    {
        return ['database'];
    }

    public function run(AuditContext $context): iterable
    {
        $query = match ($this->id) {
            self::CATEGORY_DESCRIPTION => Category::query()->where('is_published', true)
                ->where(static fn (Builder $empty) => $empty->whereNull('description')->orWhere('description', '')->orWhere('description', '{}')->orWhere('description', '[]')),
            self::PRODUCT_IMAGE => Product::query()->where('is_published', true)->whereDoesntHave('images'),
            default => Product::query()->where('is_published', true)->where(static fn (Builder $empty) => $empty->whereNull('price')->orWhere('price', '<=', 0)),
        };

        $count = (clone $query)->count();

        if ($count === 0) {
            return;
        }

        $editBase = $this->id === self::CATEGORY_DESCRIPTION ? '/catalog/categories/' : '/catalog/products/';

        yield new Finding($this->id, $this->severity(), null, [
            'summary' => ['key' => 'webx-catalog::audit.'.$this->id, 'params' => ['count' => $count]],
            'table' => [
                'columns' => [Finding::column('record'), Finding::column('edit', 'edit')],
                'rows' => $query->orderBy('id')->limit(self::ROWS)->get()->map(static fn (Model $record): array => [
                    'record' => $record instanceof Product ? $record->displayName() : self::name($record),
                    'edit' => $editBase.$record->getKey(),
                ])->values()->all(),
            ],
        ]);
    }

    private static function name(Model $category): string
    {
        $name = method_exists($category, 'getTranslation') ? $category->getTranslation('name') : null;

        return is_string($name) && trim($name) !== '' ? $name : '#'.$category->getKey();
    }
}
