<?php

declare(strict_types=1);

namespace WebxUi\Faq\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use WebxUi\Admin\Categories\HasCategories;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Localization\HasTranslations;
use WebxUi\Localization\Locales;

/**
 * A question and its answer (§4.2 of the FAQ spec).
 *
 * No page of its own and no main category (decision 3): it reaches the site inside a block, and
 * the categories are only what a block picks it by and filters it with. No draft either (decision
 * 12) — a question is edited in a minute, and `published` is the whole of its life.
 *
 * @property int $id
 * @property array<string, string>|string|null $question
 * @property array<string, string>|string|null $answer
 * @property string|null $anchor
 * @property bool $published
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Question extends Model
{
    use HasCategories;
    use HasExtra;
    use HasTranslations;
    use SoftDeletes;

    /** The screen a question is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'faq.form';

    /** Room for the `-12` a collision adds, inside the column's 96. */
    private const ANCHOR_LENGTH = 80;

    protected $table = 'faq_questions';

    /** @var list<string> */
    protected $fillable = ['question', 'answer', 'published', 'position'];

    /** @var array<string, string> */
    protected $casts = [
        'published' => 'boolean',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(static function (Question $question): void {
            // At the end of the list: the only place a new question can go without moving one
            // somebody else put where it is.
            if (! array_key_exists('position', $question->getAttributes())) {
                $question->position = (int) static::query()->withTrashed()->max('position') + 1;
            }

            if (($question->anchor ?? '') === '') {
                $question->anchor = $question->freeAnchor(Str::slug((string) $question->getTranslation(
                    'question',
                    app(Locales::class)->defaultCode(),
                )));
            }
        });

        static::created(static function (Question $question): void {
            // A question with no words in the default language has nothing to make an anchor of,
            // and its number is only known now.
            if (($question->anchor ?? '') === '') {
                $question->anchor = $question->freeAnchor('q-'.$question->getKey());
                $question->saveQuietly();
            }
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['question', 'answer'];
    }

    /**
     * @return BelongsToMany<FaqCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        /** @var BelongsToMany<FaqCategory, $this> $relation */
        $relation = $this->belongsToCategories(FaqCategory::class, 'faq_category_question', 'question_id', 'category_id');

        return $relation;
    }

    public function categoryRelation(): string
    {
        return 'categories';
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /**
     * Whether a reader of a page in this language sees the question (decision 9): published, out
     * of the bin, and written in it — the question and the answer both. No other language stands
     * in: a FAQ with half its answers in another language is worse than a short one.
     */
    public function visibleIn(string $locale): bool
    {
        return $this->published && ! $this->trashed() && $this->writtenIn($locale);
    }

    /** Whether both the question and the answer are written in this language. */
    public function writtenIn(string $locale): bool
    {
        return $this->written('question', $locale) && $this->written('answer', $locale);
    }

    /**
     * The answer as a page prints it: the document the editor wrote, with every library picture
     * pointed at where it lives now — the document holds keys, not addresses.
     */
    public function answerHtml(string $locale): string
    {
        $stored = $this->getTranslation('answer', $locale, fallback: false);

        if (! is_string($stored) || trim($stored) === '') {
            return '';
        }

        $type = app(FieldTypes::class)->get('wx-rich-text');
        $resolved = $type === null ? $stored : $type->resolve($stored, [], $locale);

        return is_string($resolved) ? $resolved : $stored;
    }

    /** The question in this language, and only this one. */
    public function questionText(string $locale): string
    {
        $text = $this->getTranslation('question', $locale, fallback: false);

        return is_string($text) ? trim($text) : '';
    }

    /**
     * The categories the question is in, as ids — from what was loaded when it was.
     *
     * @return list<int>
     */
    public function categoryIds(): array
    {
        /** @var list<int> $ids */
        $ids = $this->categories->map(static fn (FaqCategory $category): int => (int) $category->getKey())->values()->all();

        return $ids;
    }

    /**
     * Words rather than markup: an editor that was emptied leaves `<p></p>` behind in an older
     * row, and an answer that is a paragraph of nothing is not an answer.
     */
    private function written(string $field, string $locale): bool
    {
        $value = $this->getTranslation($field, $locale, fallback: false);

        if (! is_string($value)) {
            return false;
        }

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)) !== ''
            || ($field === 'answer' && str_contains($value, '<img'));
    }

    /**
     * The wanted anchor, or the first `-2`, `-3` after it nobody has — the bin included: a
     * question brought back keeps its anchor, so it still holds it while it is away.
     */
    private function freeAnchor(string $wanted): string
    {
        $wanted = trim(Str::limit($wanted, self::ANCHOR_LENGTH, ''), '-');

        if ($wanted === '') {
            return '';
        }

        $taken = static::query()->withTrashed()
            ->where(static fn ($query) => $query->where('anchor', $wanted)->orWhere('anchor', 'like', $wanted.'-%'))
            ->pluck('anchor')
            ->all();

        $candidate = $wanted;

        for ($n = 2; in_array($candidate, $taken, true); $n++) {
            $candidate = $wanted.'-'.$n;
        }

        return $candidate;
    }
}
