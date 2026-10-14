<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blog\Links\Titles;
use WebxUi\Blog\Models\Author;
use WebxUi\Localization\Locales;

/**
 * A profile as the list, the head of its form and an agent see it (§19.5): who it is, whose
 * account it is, whether its page is open, where it is and how much they wrote.
 *
 * `open` is the page actually answering — the profile asked for one and the site allows them —
 * while `is_public` is only the profile's half of that; the list shows the first, the form edits
 * the second.
 */
final class AuthorRow
{
    /**
     * @return array<string, mixed>
     */
    public static function of(Author $author, Locales $locales): array
    {
        $locale = $locales->current();
        $user = $author->user;

        return [
            'id' => (int) $author->getKey(),
            'name' => Titles::of($author, $locale),
            'user' => $user instanceof CmsUser ? ['id' => (int) $user->getKey(), 'name' => (string) $user->name] : null,
            'is_public' => $author->is_public,
            'open' => $author->isOpen(),
            'url' => $author->hasUrlIn($locale) ? $author->url($locale) : null,
            'photo' => $author->photoUrl(),
            'articles_count' => (int) ($author->getAttribute('articles_count') ?? $author->articles()->count()),
        ];
    }
}
