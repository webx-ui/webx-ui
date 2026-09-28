<?php

declare(strict_types=1);

namespace WebxUi\Banners\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Localization\HasTranslations;

/**
 * A banner (§3, §5.1 of the banners spec): a picture, one for phones, a video, a title, a text and
 * up to three buttons, in one place.
 *
 * No page, no draft, no schedule (decisions 5 and 13): `enabled` is the whole of "not on the site",
 * and a new banner starts without it. Seen only in the languages its words are written in
 * (decision 12) — a banner with no words at all is seen everywhere.
 *
 * `enabled` rather than `visible` or `hidden`: those two are properties of Eloquent, not
 * attributes (CLAUDE.md §4).
 *
 * @property int $id
 * @property int $place_id
 * @property array<string, mixed>|null $image
 * @property array<string, mixed>|null $image_mobile
 * @property array<string, mixed>|null $video
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $text
 * @property list<array<string, mixed>>|null $buttons
 * @property bool $enabled
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Place|null $place
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Banner extends Model
{
    use HasExtra;
    use HasTranslations;
    use SoftDeletes;

    /** The screen a banner is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'banners.form';

    /** The words of a banner — what decides the languages it is seen in (decision 12). */
    public const WORDS = ['title', 'text'];

    /** @var list<string> */
    protected $fillable = ['place_id', 'image', 'image_mobile', 'video', 'title', 'text', 'buttons', 'enabled', 'position'];

    /** @var array<string, string> */
    protected $casts = [
        'place_id' => 'integer',
        'enabled' => 'boolean',
        'position' => 'integer',
        'image' => 'array',
        'image_mobile' => 'array',
        'video' => 'array',
        'buttons' => 'array',
    ];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return self::WORDS;
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /**
     * @return BelongsTo<Place, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    /**
     * What a reader may be shown, as SQL can tell: turned on and out of the bin. The languages are
     * {@see writtenIn()}.
     *
     * @param  Builder<Banner>  $query
     * @return Builder<Banner>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('enabled'), true);
    }

    /**
     * Put the banner at the end of a place: where a new one goes, and where one moved from another
     * place lands (decision 14) — the one spot nobody else's order has to make room for. The bin
     * counts, so a banner brought back does not share a position with one added while it was away.
     */
    public function moveToEndOf(int $place): void
    {
        $this->place_id = $place;
        $this->position = (int) static::query()->withTrashed()->where('place_id', $place)->max('position') + 1;
    }

    /** One of the words in this language, and only this one; '' when it is not written there. */
    public function wordsIn(string $field, string $locale): string
    {
        $words = $this->getTranslation($field, $locale, fallback: false);

        return is_string($words) ? trim($words) : '';
    }

    /**
     * Whether the banner is seen in this language (decision 12): it has no words at all, or its
     * title or its text is written in it. No fallback language — words in another language on a
     * picture are worse than no picture.
     */
    public function writtenIn(string $locale): bool
    {
        $any = false;

        foreach (self::WORDS as $field) {
            if ($this->wordsIn($field, $locale) !== '') {
                return true;
            }

            $any = $any || $this->languages($field) !== [];
        }

        return ! $any;
    }

    /**
     * The languages the words are written in — for the panel and for an agent.
     *
     * @return list<string>
     */
    public function wordLanguages(): array
    {
        $codes = [];

        foreach (self::WORDS as $field) {
            $codes = [...$codes, ...$this->languages($field)];
        }

        return array_values(array_unique($codes));
    }

    /** The library key of one of the media, when there is one. */
    public function mediaPath(string $field): ?string
    {
        $value = $this->getAttribute($field);
        $path = is_array($value) ? ($value['path'] ?? null) : null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * The buttons as they are stored, rows that are not a button skipped.
     *
     * @return list<array<string, mixed>>
     */
    public function buttonRows(): array
    {
        $rows = [];

        foreach (is_array($this->buttons) ? $this->buttons : [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function languages(string $field): array
    {
        $codes = [];

        foreach ($this->getTranslations($field) as $code => $words) {
            if (is_string($words) && trim($words) !== '') {
                $codes[] = (string) $code;
            }
        }

        return $codes;
    }
}
