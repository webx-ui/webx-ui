<?php

declare(strict_types=1);

namespace WebxUi\Localization\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Localization\LocaleCatalogue;

/**
 * One language the site is published in.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $native_name
 * @property string $direction
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort
 */
class Locale extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // A code arrives from a form, a seed or a console command, and all three spell it
        // differently often enough to matter.
        static::saving(function (Locale $locale): void {
            $locale->code = LocaleCatalogue::normalise($locale->code);
        });

        // Exactly one default, kept true by the only writer that can see both rows.
        static::saved(function (Locale $locale): void {
            if (! $locale->is_default) {
                return;
            }

            static::query()
                ->whereKeyNot($locale->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    /**
     * Fill in the name, the native name and the direction from the catalogue, so creating a
     * language is one field in practice.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function fromCode(string $code, array $attributes = []): self
    {
        $code = LocaleCatalogue::normalise($code);
        $described = LocaleCatalogue::describe($code);

        return new self([
            'code' => $code,
            'name' => $described['name'],
            'native_name' => $described['native'],
            'direction' => $described['direction'],
            ...$attributes,
        ]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('code');
    }

    /**
     * What the panel and the public site are given. Deliberately not the model: `id` and the
     * timestamps say nothing to a front end, and a payload that grows by accident is how a
     * column ends up on somebody's screen.
     *
     * @return array{code: string, name: string, nativeName: string, direction: string, default: bool}
     */
    public function toPayload(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'nativeName' => $this->native_name,
            'direction' => $this->direction,
            'default' => $this->is_default,
        ];
    }
}
