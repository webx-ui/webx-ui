<?php

declare(strict_types=1);

namespace WebxUi\Geography\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Exception\MissingResourceException;
use WebxUi\Geography\Names;

/**
 * A country the site knows of. Rows are not seeded: one appears when an administrator turns the
 * country on, or when a carrier brings a locality in it (switched off until someone turns it on).
 *
 * @property string $code
 * @property array<string, string>|null $names
 * @property bool $active
 * @property int $position
 */
class GeoCountry extends Model
{
    protected $table = 'geo_countries';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'names', 'active', 'position'];

    protected $attributes = [
        'active' => false,
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'names' => 'array',
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * The site's own name for the country, else the one `symfony/intl` has in that language;
     * a code for private use (`XX` in the demo) has neither and answers with itself.
     */
    public function name(?string $locale = null): string
    {
        $own = Names::pick($this->names, $locale);

        return $own ?? self::intlName($this->code, $locale ?? app()->getLocale());
    }

    public static function intlName(string $code, string $locale): string
    {
        try {
            return Countries::getName($code, $locale);
        } catch (MissingResourceException) {
            return $code;
        }
    }

    /**
     * ISO 3166-1 alpha-2 as `symfony/intl` knows it, or one of the codes the standard leaves for
     * private use — a demo or a test is not tied to a real country that way.
     */
    public static function isValidCode(string $code): bool
    {
        if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
            return false;
        }

        return Countries::exists($code)
            || $code === 'AA'
            || $code === 'ZZ'
            || ($code[0] === 'Q' && $code[1] >= 'M')
            || $code[0] === 'X';
    }

    /** @return HasMany<GeoRegion, $this> */
    public function regions(): HasMany
    {
        return $this->hasMany(GeoRegion::class, 'country_code', 'code');
    }

    /** @return HasMany<GeoLocality, $this> */
    public function localities(): HasMany
    {
        return $this->hasMany(GeoLocality::class, 'country_code', 'code');
    }
}
