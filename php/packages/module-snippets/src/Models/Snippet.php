<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\History\RecordsHistory;

/**
 * The code of an outside service and where it goes (§3 of the snippets spec).
 *
 * No draft and no versions (decision 4): a save is what the site prints, at once, and `enabled`
 * is the whole of switching it off. The journal is where an old code lives — written whole,
 * before and after, so somebody can paste it back by hand.
 *
 * @property int $id
 * @property string $name
 * @property string $place
 * @property string $code
 * @property string $consent
 * @property list<string>|null $only_paths
 * @property list<string>|null $except_paths
 * @property list<string>|null $locales
 * @property bool $on_stage
 * @property bool $enabled
 * @property int $position
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Snippet extends Model
{
    use RecordsHistory;
    use SoftDeletes;

    /** The type its rows have in the journal. */
    public const TYPE = 'snippets';

    /** The screen a snippet is edited on. */
    public const SCREEN = 'snippets.form';

    /**
     * The categories of the consent widget it may wait for (decision 1): the widget's own names,
     * `statistics` shown as "Analytics". `necessary` is printed at once.
     */
    public const CATEGORIES = ['necessary', 'statistics', 'marketing', 'media'];

    /** Bytes of UTF-8, not characters: the limit is about the weight of the page (§3.3). */
    public const MAX_BYTES = 65536;

    protected $table = 'snippets_codes';

    /** @var list<string> */
    protected $fillable = ['name', 'place', 'code', 'consent', 'only_paths', 'except_paths', 'locales', 'on_stage', 'enabled', 'position'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'consent' => 'necessary',
        'on_stage' => false,
        'enabled' => false,
    ];

    /** @var array<string, string> */
    protected $casts = [
        'only_paths' => 'array',
        'except_paths' => 'array',
        'locales' => 'array',
        'on_stage' => 'boolean',
        'enabled' => 'boolean',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(static function (Snippet $snippet): void {
            // At the end of its place: the only spot a new code takes without moving one
            // somebody put where it is.
            if (! array_key_exists('position', $snippet->getAttributes())) {
                $snippet->position = (int) static::query()->withTrashed()->where('place', $snippet->place)->max('position') + 1;
            }
        });

        static::updating(static function (Snippet $snippet): void {
            // Moved by its form rather than dragged: to the end of the place it went to.
            if ($snippet->isDirty('place') && ! $snippet->isDirty('position')) {
                $snippet->position = (int) static::query()->withTrashed()->where('place', $snippet->place)->max('position') + 1;
            }
        });
    }

    /**
     * The code itself is written whole into the journal, however long: there are no versions to
     * bring an old one back from.
     *
     * @return list<string>
     */
    public function historyKeepsFull(): array
    {
        return ['code'];
    }

    /** Whether the code waits for the visitor to agree to its category. */
    public function waitsForConsent(): bool
    {
        return $this->consent !== 'necessary';
    }
}
