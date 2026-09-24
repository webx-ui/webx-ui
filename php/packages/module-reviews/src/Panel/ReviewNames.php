<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Panel;

use WebxUi\Localization\Locales;
use WebxUi\Reviews\Models\Review;

/**
 * What a review is called in the panel (§4.7): the name in the panel's language, else in the
 * default one, else its number — a row of the list is never blank.
 */
final class ReviewNames
{
    public static function of(Review $review, Locales $locales): string
    {
        $name = $review->wordsIn('name', $locales->current(), $locales->defaultCode());

        return $name !== '' ? $name : '#'.$review->getKey();
    }
}
