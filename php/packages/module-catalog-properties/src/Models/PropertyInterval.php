<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use WebxUi\Localization\HasTranslations;

/**
 * An interval a number is filtered by — «up to 1 kg», «1–2 kg» (§2 of the properties spec). Not a
 * value of a product: the product keeps its number, and the facet lays it out. `[min, max)`, so two
 * neighbours do not share the product that weighs exactly 2; a null end is open. Intervals may
 * overlap — the admin's choice — and each is counted honestly.
 *
 * @property int $id
 * @property int $property_id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property string|null $min
 * @property string|null $max
 * @property int $position
 */
class PropertyInterval extends Model
{
    use HasTranslations;

    protected $table = 'catalog_property_intervals';

    /** @var list<string> */
    protected $fillable = ['property_id', 'title', 'slug', 'min', 'max', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['property_id' => 'integer', 'position' => 'integer'];
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug'];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id')->withTrashed();
    }

    public function low(): ?float
    {
        return $this->min === null ? null : (float) $this->min;
    }

    public function high(): ?float
    {
        return $this->max === null ? null : (float) $this->max;
    }

    public function contains(float $number): bool
    {
        return ($this->low() === null || $number >= $this->low()) && ($this->high() === null || $number < $this->high());
    }

    public function displayName(?string $locale = null): string
    {
        $title = $this->getTranslation('title', $locale ?? app()->getLocale());

        return is_string($title) && trim($title) !== '' ? $title : '#'.$this->getKey();
    }
}
