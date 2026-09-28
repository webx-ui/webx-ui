<?php

declare(strict_types=1);

namespace WebxUi\Banners\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use WebxUi\Banners\Places;
use WebxUi\Localization\HasTranslations;

/**
 * The row of a place (§3, §5.1 of the banners spec).
 *
 * Not every place has one: a declared place exists from the first day and gets its row with its
 * first banner ({@see Places::row()}). What a place is — declared or not, what it
 * is called, how it is laid out — is {@see Places}; this is only the part of it the
 * database keeps.
 *
 * @property int $id
 * @property string $key
 * @property array<string, string>|string|null $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Place extends Model
{
    use HasTranslations;

    /** What a key may be: lowercase letters, digits and dashes, a letter first. */
    public const KEY = '/^[a-z][a-z0-9-]{0,63}$/';

    protected $table = 'banner_places';

    /** @var list<string> */
    protected $fillable = ['key', 'title'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title'];
    }

    /**
     * The banners of the place, out of the bin; the bin is `->withTrashed()` on top.
     *
     * @return HasMany<Banner, $this>
     */
    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class, 'place_id');
    }
}
