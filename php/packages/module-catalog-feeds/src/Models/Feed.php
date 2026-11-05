<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\CatalogFeeds\Delivery\FeedFiles;
use WebxUi\CatalogFeeds\Formats\FeedFormats;

/**
 * A feed (§3 of the feeds spec): a template, what goes into it, and when it is built.
 *
 * The key opens the file to anybody who has it, so it stays out of `toArray()` and of the journal
 * (§13); it is written once, at creation, and only a reissue changes it.
 *
 * @property int $id
 * @property string $name
 * @property string|null $preset
 * @property string $format
 * @property string|null $driver
 * @property string $locale
 * @property string $currency
 * @property string|null $head
 * @property string|null $item
 * @property string|null $foot
 * @property string $separator
 * @property array<string, mixed> $filter
 * @property array<string, mixed> $stock_map
 * @property string|null $taxonomy
 * @property array<string, mixed> $custom_labels
 * @property array<string, mixed> $sources
 * @property array<string, mixed> $options
 * @property string $schedule
 * @property string|null $schedule_at
 * @property bool $rebuild_on_change
 * @property string $access_key
 * @property Carbon $key_issued_at
 * @property bool $is_active
 * @property int|null $current_build_id
 * @property string|null $built_generation
 * @property string|null $seen_generation
 * @property Carbon|null $dirty_since
 * @property Carbon|null $first_dirty_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FeedBuild|null $lastBuild
 * @property-read FeedBuild|null $currentBuild
 */
class Feed extends Model
{
    use RecordsHistory;

    /** The form of the panel (§11). */
    public const SCREEN = 'catalog.feed-form';

    /** The type the journal knows it by. */
    public const TYPE = 'catalog.feed';

    /** The format decides the escaping and the check of the file; each has its file extension. */
    public const FORMATS = ['xml' => 'xml', 'json' => 'json', 'csv' => 'csv', 'text' => 'txt'];

    /** What a file of each format is served as. */
    public const TYPES = ['xml' => 'application/xml', 'json' => 'application/json', 'csv' => 'text/csv', 'text' => 'text/plain'];

    public const SCHEDULES = ['manual', 'hourly', 'every_6_hours', 'daily'];

    /** How long a schedule's period is, in minutes; `manual` has none. */
    public const PERIODS = ['hourly' => 60, 'every_6_hours' => 360, 'daily' => 1440];

    /** The fields the journal names in words: the settings, not the service columns. */
    public const HISTORY = [
        'name', 'format', 'locale', 'currency', 'head', 'item', 'foot', 'separator', 'filter',
        'stock_map', 'taxonomy', 'custom_labels', 'sources', 'options', 'schedule', 'schedule_at',
        'rebuild_on_change', 'is_active',
    ];

    protected $table = 'catalog_feeds';

    /** @var list<string> */
    protected $fillable = [
        'name', 'preset', 'format', 'locale', 'currency', 'head', 'item', 'foot', 'separator',
        'filter', 'stock_map', 'taxonomy', 'custom_labels', 'sources', 'options', 'schedule',
        'schedule_at', 'rebuild_on_change', 'is_active',
    ];

    /** @var list<string> */
    protected $hidden = ['access_key'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'separator' => "\n",
        'filter' => '{}',
        'stock_map' => '{}',
        'custom_labels' => '{}',
        'sources' => '{"mpn":{"type":"field"}}',
        'options' => '{}',
        'schedule' => 'manual',
        'rebuild_on_change' => false,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(static function (Feed $feed): void {
            $feed->access_key = self::newKey();
            $feed->key_issued_at = Carbon::now();
        });

        static::deleted(static function (Feed $feed): void {
            Container::getInstance()->make(FeedFiles::class)->forget((int) $feed->id);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filter' => 'array',
            'stock_map' => 'array',
            'custom_labels' => 'array',
            'sources' => 'array',
            'options' => 'array',
            'rebuild_on_change' => 'boolean',
            'is_active' => 'boolean',
            'key_issued_at' => 'datetime',
            'dirty_since' => 'datetime',
            'first_dirty_at' => 'datetime',
        ];
    }

    public static function newKey(): string
    {
        return Str::random(40);
    }

    /** What stands between two products when nothing else was chosen (§3.1). */
    public static function separatorOf(string $format): string
    {
        return $format === 'json' ? ',' : "\n";
    }

    /** What every price of the site is in; empty where nobody set it. */
    public static function siteCurrency(): string
    {
        return strtoupper(trim((string) config('webx-catalog.price.currency')));
    }

    /** The whole address with its key: for whoever holds `catalog.manage`, and nobody else (§11). */
    public function address(): string
    {
        return url(config('webx-catalog-feeds.path').'/'.$this->access_key.'.'.$this->extension());
    }

    /**
     * Of the file and of its address. A class that is gone leaves the extension of its last file:
     * that file goes on being served, at the address a marketplace holds (§10).
     */
    public function extension(): string
    {
        $own = self::FORMATS[$this->format];

        if ($this->driver === null) {
            return $own;
        }

        $format = Container::getInstance()->make(FeedFormats::class)->find($this->driver);

        if ($format !== null) {
            return $format->extension();
        }

        $served = pathinfo((string) $this->currentBuild?->path, PATHINFO_EXTENSION);

        return $served === '' ? $own : $served;
    }

    /**
     * @return HasMany<FeedBuild, $this>
     */
    public function builds(): HasMany
    {
        return $this->hasMany(FeedBuild::class, 'feed_id');
    }

    /**
     * What the list shows: the last build, whatever came of it.
     *
     * @return HasOne<FeedBuild, $this>
     */
    public function lastBuild(): HasOne
    {
        return $this->hasOne(FeedBuild::class, 'feed_id')->latestOfMany();
    }

    /**
     * The build being served, which a failed later one leaves in place (§7.4).
     *
     * @return BelongsTo<FeedBuild, $this>
     */
    public function currentBuild(): BelongsTo
    {
        return $this->belongsTo(FeedBuild::class, 'current_build_id');
    }
}
