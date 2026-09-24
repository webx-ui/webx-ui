<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Panel;

use WebxUi\Recipes\Models\Recipe;

/**
 * What an editor read, as a short string, so a save can say whether somebody wrote in between.
 *
 * A hash of the recipe as it is being edited — the draft where there is one, the columns where
 * there is not — with the categories, the nutrients and the relations as the editor last left
 * them: they wait in the draft like the text, and a service added by somebody else is a change to
 * the recipe as much as a retitled step is. Not `position`: the list is dragged by other people
 * while this one is open, and a reorder is not an edit anybody could lose.
 */
final class Revision
{
    /** The columns of the text, in this order. */
    private const CONTENT = ['title', 'slug', 'lead', 'gallery', 'ingredients', 'method', 'nutrition', 'total_minutes', 'servings', 'extra'];

    public static function of(Recipe $recipe): string
    {
        $shown = $recipe->hasDraft() ? $recipe->withDraft() : $recipe;

        $content = [];

        foreach (self::CONTENT as $column) {
            $content[$column] = $shown->getAttribute($column);
        }

        $content['published_at'] = $recipe->published_at?->toAtomString();
        $content['categories'] = $recipe->draftedCategoryIds('categories');
        $content['nutrients'] = $recipe->draftedCategoryIds('nutrients');
        $content['services'] = $recipe->draftedRelatedIds('services');
        $content['related'] = $recipe->draftedRelatedIds('related');

        return substr(sha1(json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), 0, 12);
    }
}
