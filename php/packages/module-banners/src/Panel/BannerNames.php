<?php

declare(strict_types=1);

namespace WebxUi\Banners\Panel;

use WebxUi\Banners\Models\Banner;
use WebxUi\Localization\Locales;

/**
 * What a banner is called in the panel (§5.6): its title in the panel's language, else in the
 * default one, else its number — a row of the list is never blank.
 */
final class BannerNames
{
    public static function of(Banner $banner, Locales $locales): string
    {
        foreach (array_unique([$locales->current(), $locales->defaultCode()]) as $code) {
            $title = $banner->wordsIn('title', $code);

            if ($title !== '') {
                return $title;
            }
        }

        return '#'.$banner->getKey();
    }
}
