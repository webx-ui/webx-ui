<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blog\Models\Tag;
use WebxUi\Localization\Locales;

/**
 * Tags, as the article form needs them: find one while typing, and make one that is not there
 * (§10).
 *
 * The screen that rakes them over — renaming, merging, the three states of indexing — is its
 * own thing and comes with its own filters. This is the other half of the story and the one
 * that comes first: tags are entered from the article, by the hundred, and a field that could
 * only pick from what already exists would mean leaving the article to go and make a word.
 */
final class TagController
{
    /** As many as a dropdown is worth scrolling; past that the answer is to type more. */
    private const LIMIT = 30;

    public function __construct(private readonly Locales $locales) {}

    public function index(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $locale = $this->locales->current();

        $query = Tag::query()->withCount('articles');

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            // The title in every language and the address, because a tag is looked for by the
            // word somebody remembers rather than by the language the panel is open in.
            $query->where(static function (Builder $nested) use ($like): void {
                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                    ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
            });
        }

        // Most used first: a field offering "belts" before "belt" is a field that stops the
        // third spelling of one word from being made (§2.8).
        $tags = $query->orderByDesc('articles_count')->orderBy('id')->limit(self::LIMIT)->get();

        return ApiResponse::data(
            $tags
                ->map(fn (Tag $tag): array => [
                    'id' => (int) $tag->getKey(),
                    'title' => $this->name($tag, $locale),
                    'slug' => (string) $tag->getTranslation('slug', $locale, fallback: false),
                    'articles_count' => (int) $tag->getAttribute('articles_count'),
                ])
                ->values()
                ->all(),
        );
    }

    /**
     * A new tag, made from the article being written.
     *
     * The address is made out of the title the same way an article's is, and the flag it starts
     * with is the one in the config: a tag page is out of the index until somebody decides
     * otherwise (§2.9), and that decision is made on the tags screen rather than in passing.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[\p{L}\p{N}]+(?:[-_][\p{L}\p{N}]+)*$/u'],
        ], ['slug.regex' => (string) __('webx-blog::errors.slug-shape')]);

        $title = trim((string) $validated['title']);
        $slug = trim((string) ($validated['slug'] ?? ''));

        $tag = new Tag([
            'title' => $title,
            'slug' => $slug !== '' ? $slug : Str::slug($title),
            'noindex' => (bool) config('webx-blog.tags.noindex', true),
        ]);
        $tag->save();

        $locale = $this->locales->current();

        return ApiResponse::data([
            'id' => (int) $tag->getKey(),
            'title' => $this->name($tag, $locale),
            'slug' => (string) $tag->getTranslation('slug', $locale, fallback: false),
            'articles_count' => 0,
        ], 201);
    }

    /**
     * A tag named in one language and not in another is still offered: its address is the name
     * it has everywhere, and an unnamed row nobody can pick is worse than one named after it.
     */
    private function name(Tag $tag, string $locale): string
    {
        $title = $tag->getTranslation('title', $locale);

        return is_string($title) && trim($title) !== ''
            ? $title
            : (string) $tag->getTranslation('slug', $locale);
    }
}
