<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A marketplace's list of categories in one language (§6.2 of the feeds spec). `locale` is the
 * language of the file, `en-US` and not `en`: a site without a file of its own reads the English
 * list, and there is no reason to hold a copy of it per language.
 *
 * @property int $id
 * @property string $key
 * @property string $locale
 * @property string|null $version
 * @property string|null $source
 * @property Carbon|null $imported_at
 * @property string|null $status
 * @property string|null $error
 * @property Carbon|null $queued_at
 */
class Taxonomy extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public $timestamps = false;

    protected $table = 'catalog_feed_taxonomies';

    /** @var list<string> */
    protected $fillable = ['key', 'locale', 'version', 'source', 'imported_at', 'status', 'error', 'queued_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['imported_at' => 'datetime', 'queued_at' => 'datetime'];
    }

    /**
     * @return HasMany<TaxonomyItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TaxonomyItem::class, 'taxonomy_id');
    }

    public function going(): bool
    {
        return in_array($this->status, [self::QUEUED, self::RUNNING], true);
    }
}
