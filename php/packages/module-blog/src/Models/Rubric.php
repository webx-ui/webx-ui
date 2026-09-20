<?php

declare(strict_types=1);

namespace WebxUi\Blog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Blog\Exceptions\BlogException;
use WebxUi\Localization\HasTranslations;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\HasSeo;

/**
 * A rubric: a section of the blog, flat, ordered by hand.
 *
 * Flat on purpose (§2.4). A tree of rubrics gets filled to one level out of three, and the
 * cross-cutting themes it was built for are what tags already do. One consequence of that
 * decision does the rest of the work: since an article can be in three rubrics at once, none of
 * them can be in its address (§2.5) — "which of the three" has no answer, and any answer would
 * be a hidden main rubric that moved the article when somebody reordered the checkboxes.
 *
 * No draft and no history either: what a rubric has instead is `is_visible`. Hidden, it drops
 * out of the menu and answers 404; its articles go on answering at their own addresses, because
 * they are not its property.
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $lead
 * @property int|null $cover_id
 * @property bool $is_visible
 * @property int $position
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Rubric extends Model
{
    use HasCover;
    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'lead', 'cover_id', 'is_visible', 'position'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug', 'lead'];
    }

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'position' => 'integer'];
    }

    /**
     * A rubric has an address in a language when it names a slug in it (§9), for the same
     * reason an article does.
     */
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /**
     * The introduction as a page prints it: a document, with every library picture pointed at
     * where it lives now.
     *
     * The column holds what the editor wrote, and what it wrote records a picture by its key
     * rather than by its address (CLAUDE.md §4) — a signed link expires within the hour and a
     * cropped picture keeps its key. So the addresses are worked out on every read, by the
     * same field type that cleaned the document on the way in. A panel with no `module-admin`
     * types registered at all gets the document as it was stored, which is the honest fallback:
     * the words are right and the pictures are wherever they were.
     */
    public function leadHtml(?string $locale = null): string
    {
        $stored = $this->getTranslation('lead', $locale ?? app()->getLocale());

        if (! is_string($stored) || trim($stored) === '') {
            return '';
        }

        $type = app(FieldTypes::class)->get('wx-rich-text');
        $resolved = $type === null ? $stored : $type->resolve($stored, [], $locale);

        return is_string($resolved) ? $resolved : $stored;
    }

    /**
     * The ones a reader can reach, in the order the panel dragged them into.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInMenuOrder(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Article, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_rubric')->withPivot('position');
    }

    /** How many articles would be left without this rubric — the number the refusal names. */
    public function articleCount(): int
    {
        return $this->articles()->count();
    }

    protected static function booted(): void
    {
        static::deleting(static function (self $rubric): void {
            if ($rubric->isForceDeleting()) {
                return;
            }

            // A soft-deleted rubric with live articles in it is a hole in the navigation that
            // nobody notices: the articles go on answering, the rubric they name is gone, and
            // the menu is quietly one item short (§6). Refused instead, with the number, so the
            // editor can move them or decide the rubric was the wrong idea.
            $count = $rubric->articleCount();

            if ($count > 0) {
                throw BlogException::rubricHasArticles($count);
            }
        });
    }
}
