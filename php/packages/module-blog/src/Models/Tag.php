<?php

declare(strict_types=1);

namespace WebxUi\Blog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use WebxUi\Localization\HasTranslations;
use WebxUi\Routing\HasUrl;

/**
 * A tag: one word about an article, made from the article form and sorted out later (§2.8).
 *
 * No SEO card, no draft, no history, no bin. A tag is a word — what an editor does to one is
 * rename it, merge it into another, open it to the index or delete it, and every one of those
 * is a thing done to a word rather than to a document.
 *
 * `noindex` is on by default, and what turns it off is either this flag or a rule in `seo_urls`
 * written for the tag's address (§12). The flag is not touched by the rule: there are two ways
 * to open a tag, both are deliberate, and both stay visible in the panel.
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property bool $noindex
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Tag extends Model
{
    use HasTranslations;
    use HasUrl;

    /** In the index, because the tag says so. */
    public const INDEXING_OPEN = 'open';

    /** In the index, because somebody wrote a rule for this address (§12). */
    public const INDEXING_RULE = 'rule';

    /** Out of it. */
    public const INDEXING_NOINDEX = 'noindex';

    /** What a tag page says about itself when it is out of the index. */
    public const ROBOTS_NOINDEX = 'noindex,follow';

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'noindex'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug'];
    }

    protected function casts(): array
    {
        return ['noindex' => 'boolean'];
    }

    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /**
     * @return BelongsToMany<Article, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_tag');
    }

    /**
     * Tags nothing is filed under — the half of the screen that exists to be emptied.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEmpty(Builder $query): Builder
    {
        return $query->whereDoesntHave('articles');
    }
}
