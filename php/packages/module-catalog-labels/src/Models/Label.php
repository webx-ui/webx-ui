<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Catalog\Dictionaries\Dictionary;
use WebxUi\Catalog\Models\Product;

/**
 * A label: top, sale, new — several on a product (§2.1 of the dictionaries spec). A badge on the
 * card when `is_badge`, a value of the filter when `is_visible`; a label with neither is a
 * service one ("for the newsletter") that only a template or an agent picks products by.
 *
 * Painted in a tone of the site, or in a colour of its own: `color` is then `#rrggbb` and
 * `text_color` the text on it — `#rrggbb` too, or null for black or white by contrast.
 *
 * @property bool $is_badge
 * @property string|null $text_color
 */
class Label extends Dictionary
{
    /** The screen a label is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'catalog.label-form';

    /** The link table: which labels are on which product. */
    public const LINKS = 'catalog_label_product';

    protected $table = 'catalog_labels';

    /** @var list<string> */
    protected $fillable = ['title', 'code', 'color', 'text_color', 'is_visible', 'is_badge', 'position'];

    /** @var array<string, string> */
    protected $casts = ['is_badge' => 'boolean'];

    /**
     * Read by whoever may open the catalogue — the product form files into them — and written by
     * whoever may edit it: the people who put products on sale put the «Sale» on them (decision 8).
     */
    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['catalog.view', 'catalog.manage'],
            manage: 'catalog.manage',
            noun: 'label',
            plural: 'labels',
            items: 'products',
            fields: '`color` is a tone of the site — '.implode(', ', self::TONES).' — or a colour of the label\'s own as '
                .'`#rrggbb`; `text_color` is the text on a colour of its own, `#rrggbb`, or null for black or white by '
                .'contrast (a tone ignores it). `code` is the label in addresses and templates, `is_badge` draws it on the '
                .'card, `is_visible` offers it in the filter.',
        );
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, self::LINKS, 'label_id', 'product_id');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->products();
    }

    /**
     * @return list<string>
     */
    public function categoryFields(): array
    {
        return [...parent::categoryFields(), 'text_color', 'is_badge'];
    }

    /**
     * @return list<string>
     */
    public function touchingFields(): array
    {
        return [...parent::touchingFields(), 'text_color', 'is_badge'];
    }

    public function customColors(): bool
    {
        return true;
    }

    public function writeCategoryValue(string $field, mixed $value): void
    {
        if ($field === 'text_color') {
            $this->setAttribute('text_color', self::normalColor($value));

            return;
        }

        parent::writeCategoryValue($field, $value);
    }

    /**
     * The two colours of a label painted in its own: `['background' => …, 'text' => …]`, the text
     * worked out by contrast when none was chosen. Null for a label in a tone.
     *
     * @return array{background: string, text: string}|null
     */
    public function ownColors(): ?array
    {
        if (! $this->hasOwnColor()) {
            return null;
        }

        $background = (string) $this->color;

        return ['background' => $background, 'text' => $this->text_color ?? self::contrastText($background)];
    }

    /**
     * A text colour only goes with a colour of the label's own: a tone takes both from the site,
     * so one left behind by a switch to a tone is dropped rather than kept for nobody.
     *
     * @throws ValidationException
     */
    protected function assertValid(): void
    {
        $text = $this->getAttribute('text_color');

        if (! $this->hasOwnColor()) {
            $this->setAttribute('text_color', null);
        } elseif ($text !== null && preg_match(self::HEX, (string) $text) !== 1) {
            throw ValidationException::withMessages(['text_color' => [(string) __('webx-catalog-labels::errors.text-color')]]);
        }

        parent::assertValid();
    }

    protected function colorMessage(): string
    {
        return (string) __('webx-catalog-labels::errors.color', ['tones' => implode(', ', self::TONES)]);
    }

    /**
     * @return Builder<Product>
     */
    public function affectedProducts(): Builder
    {
        return Product::withTrashed()->whereIn(
            'catalog_products.id',
            DB::table(self::LINKS)->select('product_id')->where('label_id', $this->getKey()),
        );
    }

    /** A label still on products: deleting it would take it off them without a word. */
    public function inUseMessage(int $count): string
    {
        return (string) __('webx-catalog-labels::errors.in-use', ['count' => $count]);
    }
}
