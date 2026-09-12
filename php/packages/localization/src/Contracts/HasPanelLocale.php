<?php

declare(strict_types=1);

namespace WebxUi\Localization\Contracts;

/**
 * Somebody who reads the panel in a language of their own choosing.
 *
 * The language of the interface is a property of the person, not of the site: a site published
 * only in Ukrainian can still be maintained by somebody who wants English menus. Which is why
 * this is asked of the signed-in user rather than read from a setting.
 */
interface HasPanelLocale
{
    /** A BCP-47 code, or null to be given whatever the site considers its panel default. */
    public function panelLocale(): ?string;
}
