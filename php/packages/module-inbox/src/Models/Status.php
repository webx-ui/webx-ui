<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use WebxUi\Inbox\Exceptions\StatusInUse;
use WebxUi\Localization\HasTranslations;

/**
 * What state a submission is in.
 *
 * Rows rather than an enum, because every panel ends up wanting its own words. The panel reads
 * the flags and not the keys, so a status renamed from "New" to "Не разобрано" keeps being the
 * one a new submission gets.
 *
 * @property int $id
 * @property string $key
 * @property mixed $title
 * @property string $color
 * @property bool $is_default
 * @property bool $is_spam
 * @property bool $is_closed
 * @property int $position
 */
class Status extends Model
{
    use HasTranslations;

    protected $table = 'inbox_statuses';

    protected $guarded = [];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_spam' => 'boolean',
            'is_closed' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'status_id');
    }

    /**
     * What a submission arriving gets. The lowest position is the fallback: a database whose
     * default flag has been turned off everywhere still has to answer this question, and
     * refusing to take a submission over it would be the wrong way round.
     */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->orderBy('position')->first()
            ?? static::query()->orderBy('position')->first();
    }

    public static function spam(): ?self
    {
        return static::query()->where('is_spam', true)->orderBy('position')->first();
    }

    protected static function booted(): void
    {
        // Exactly one default (§3). Written with the query builder rather than by saving the
        // others, so that clearing the flag does not raise this same event on each of them.
        static::saved(function (Status $status): void {
            if (! $status->is_default) {
                return;
            }

            static::query()
                ->whereKeyNot($status->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });

        // A status with submissions is not deleted: they would have nothing to be in. The
        // restricted foreign key says the same, in the language the database has for it.
        static::deleting(function (Status $status): void {
            if ($status->submissions()->exists()) {
                throw new StatusInUse($status->key);
            }
        });
    }
}
