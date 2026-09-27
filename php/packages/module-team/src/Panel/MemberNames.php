<?php

declare(strict_types=1);

namespace WebxUi\Team\Panel;

use WebxUi\Localization\Locales;
use WebxUi\Team\Models\Member;

/**
 * What a person is called in the panel (§5.7): the name in the panel's language, else in the
 * default one, else their number — a row of the list is never blank.
 */
final class MemberNames
{
    public static function of(Member $member, Locales $locales): string
    {
        $name = $member->wordsIn('name', $locales->current(), $locales->defaultCode());

        return $name !== '' ? $name : '#'.$member->getKey();
    }
}
