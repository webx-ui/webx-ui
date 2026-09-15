<?php

declare(strict_types=1);

namespace WebxUi\Routing;

/**
 * What to do when the address a formatter produced is already somebody else's.
 *
 * A flat namespace makes this an everyday event rather than an edge case: `Str::slug()` of
 * "Ремень приводной" turns up ten times in one catalogue.
 */
enum OnConflict: string
{
    /**
     * Refuse the save, with the error on the slug field.
     *
     * For pages, rubrics and categories: an address there is made deliberately, and one that
     * silently became `-2` is a bug the editor finds months later in a search result.
     */
    case Fail = 'fail';

    /**
     * Take the next free `-2`, `-3`, and put it back into the entity's slug.
     *
     * For an import of ten thousand products: the alternative is a feed that stops on the first
     * duplicate with half the catalogue missing.
     */
    case Suffix = 'suffix';
}
