<?php

declare(strict_types=1);

namespace WebxUi\Blog\Links;

use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Models\Tag;

/**
 * What to call a record in a picker when its title is missing in this language.
 *
 * All three of the blog's sources need the same three answers — the title, then the slug, then
 * the number — and a picker that showed an empty row for a record written in another language
 * would be a row nobody can click on the right part of.
 *
 * The three models by name rather than "anything translatable": what has a title and a slug is a
 * fact about these three, and a parameter that said `Model` would be a promise this cannot keep.
 */
final class Titles
{
    public static function of(Article|Rubric|Tag $entity, string $locale): string
    {
        foreach (['title', 'slug'] as $attribute) {
            $candidate = $entity->getTranslation($attribute, $locale);

            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$entity->getKey();
    }
}
