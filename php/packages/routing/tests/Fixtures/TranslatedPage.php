<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests\Fixtures;

use WebxUi\Routing\HasUrl;

/**
 * A page that only has an address in the languages somebody wrote a slug in — the rule
 * `webx-ui/module-pages` lives by, here to show that {@see HasUrl::hasUrlIn()}
 * is enough to express it.
 */
class TranslatedPage extends Page
{
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }
}
