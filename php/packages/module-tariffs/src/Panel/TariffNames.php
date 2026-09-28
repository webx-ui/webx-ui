<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Panel;

use WebxUi\Localization\Locales;
use WebxUi\Tariffs\Models\Tariff;

/**
 * What a tariff is called in the panel (§5.4): the name in the panel's language, else in the
 * default one, else its number — a row of the list is never blank.
 */
final class TariffNames
{
    public static function of(Tariff $tariff, Locales $locales): string
    {
        $name = $tariff->wordsIn('name', $locales->current(), $locales->defaultCode());

        return $name !== '' ? $name : '#'.$tariff->getKey();
    }
}
