<?php

declare(strict_types=1);

namespace WebxUi\Team\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Relations\HasRelations;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Localization\HasTranslations;

/**
 * A person of the team (§5.1 of the team spec): a photo, a name, a job title, a short text and
 * where else to find them.
 *
 * No page of their own and no categories (decisions 1 and 2): a person reaches the site inside a
 * block or through `team()`. No draft either (decision 10) — `published` is the whole of their
 * life. Nobody is hidden over a language (decision 8): a name and a job title fall back to the
 * default language, and a text that is not written in one is simply not printed there.
 *
 * A target of relations for other modules (decision 12) — "the reviews of this doctor" later
 * needs no change here — and an owner of them: the services the person provides.
 *
 * @property int $id
 * @property array<string, string>|string|null $name
 * @property array<string, string>|string|null $job_title
 * @property array<string, string>|string|null $text
 * @property array<string, mixed>|null $photo
 * @property list<array{network: string, url: string}>|null $socials
 * @property bool $published
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Member extends Model
{
    use HasExtra;
    use HasRelations;
    use HasTranslations;
    use SoftDeletes;

    /** The screen a person is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'team.form';

    /** The key other modules relate to a person by. */
    public const TYPE = 'team-member';

    /** The role of the services a person provides — the `name` of the field that edits it. */
    public const SERVICES = 'services';

    protected $table = 'team_members';

    /** @var list<string> */
    protected $fillable = ['name', 'job_title', 'text', 'photo', 'socials', 'published', 'position'];

    /** @var array<string, string> */
    protected $casts = [
        'published' => 'boolean',
        'position' => 'integer',
        'photo' => 'array',
        'socials' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(static function (Member $member): void {
            // At the end of the list: the only place a new person can go without moving one
            // somebody else put where they are.
            if (! array_key_exists('position', $member->getAttributes())) {
                $member->position = (int) static::query()->withTrashed()->max('position') + 1;
            }
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['name', 'job_title', 'text'];
    }

    public function relationKey(): string
    {
        return self::TYPE;
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /**
     * What a reader may be shown (decision 8): published and out of the bin — in every language.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('published'), true);
    }

    /** Whether the site shows the person — what a relation to them asks. */
    public function isVisible(?string $locale = null): bool
    {
        return $this->published && ! $this->trashed();
    }

    /**
     * A name or a job title in this language, else in the default one (decision 8): a person's
     * name is mostly the same in every language.
     */
    public function wordsIn(string $field, string $locale, string $default): string
    {
        foreach (array_unique([$locale, $default]) as $code) {
            $words = $this->getTranslation($field, $code, fallback: false);

            if (is_string($words) && trim($words) !== '') {
                return trim($words);
            }
        }

        return '';
    }

    /**
     * The text in this language, and only this one: a paragraph in another language is worse on
     * a page than none, and the person is shown either way.
     */
    public function textIn(string $locale): string
    {
        $text = $this->getTranslation('text', $locale, fallback: false);

        return is_string($text) ? trim($text) : '';
    }

    /** Whether the text is written in this language. */
    public function writtenIn(string $locale): bool
    {
        return $this->textIn($locale) !== '';
    }

    /**
     * What stands in for a photo: the first letters of the first two words, `AP` for Anna
     * Petrova — for the cards and for the rows of the panel's list alike. Here rather than in a
     * template, because a template that has to cut a string by characters rather than bytes is
     * one `substr()` away from half a Cyrillic letter.
     */
    public static function initials(string $name): string
    {
        $words = preg_split('/[\s\-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials;
    }

    /** The library key of the photo, when there is one. */
    public function photoPath(): ?string
    {
        $path = is_array($this->photo) ? ($this->photo['path'] ?? null) : null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * The social links as they are stored, rows that are not a link skipped.
     *
     * @return list<array{network: string, url: string}>
     */
    public function socialLinks(): array
    {
        $links = [];

        foreach (is_array($this->socials) ? $this->socials : [] as $row) {
            if (is_array($row) && is_string($row['network'] ?? null) && is_string($row['url'] ?? null)) {
                $links[] = ['network' => $row['network'], 'url' => $row['url']];
            }
        }

        return $links;
    }
}
