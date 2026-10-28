<?php

declare(strict_types=1);

namespace WebxUi\Users\Fields;

/** Where a field of the site is asked for (spec §10.4). */
enum Placement: string
{
    /** The sign-up form and the "almost done" page after a provider (§9.5). */
    case Registration = 'registration';

    /** The account's profile page. */
    case Profile = 'profile';

    /** The visitor's card in the panel, and MCP with it. */
    case Panel = 'panel';

    /** The places a visitor fills in on the site, where the field needs a pair among the site's fields. */
    public function onSite(): bool
    {
        return $this !== self::Panel;
    }
}
