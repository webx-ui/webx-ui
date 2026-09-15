<?php

declare(strict_types=1);

namespace WebxUi\Seo\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Panel\SeoRules;
use WebxUi\Seo\Panel\UrlMatcher;

/**
 * An address that has moved.
 *
 * Not translatable: an address is an address. The counter is here so that a list of redirects
 * nobody has followed since the site was rebuilt can be found and thrown away, which is the
 * only way such a list ever gets shorter.
 *
 * @property int $id
 * @property string $match_type
 * @property string $pattern
 * @property string $target
 * @property int $status
 * @property bool $is_active
 * @property int $hits
 * @property Carbon|null $last_hit_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeoRedirect extends Model
{
    protected $table = 'seo_redirects';

    protected $fillable = ['match_type', 'pattern', 'target', 'status', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'is_active' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $forget = static function (): void {
            app(SeoRules::class)->forget();
        };

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * @param  Builder<SeoRedirect>  $query
     * @return Builder<SeoRedirect>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Where this redirect sends that address — null when it would send it to itself.
     *
     * A loop is not refused when it is saved: a mask redirect only becomes one for particular
     * addresses, and a rule that is right for a thousand of them should not be unwritable
     * because of the one. It is skipped here and marked in the panel instead.
     */
    public function destinationFor(string $url): ?string
    {
        $url = UrlNormaliser::normalise($url);
        $target = UrlMatcher::target($this->match_type, $this->pattern, $this->target, $url);

        return UrlNormaliser::normalise($target) === $url ? null : $target;
    }

    /** Is this redirect a loop for the address it was written for? What the panel labels. */
    public function isLoop(): bool
    {
        return $this->match_type === UrlMatcher::EXACT
            && UrlNormaliser::normalise($this->target) === UrlNormaliser::normalise($this->pattern);
    }
}
