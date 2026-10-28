<?php

declare(strict_types=1);

namespace WebxUi\Users\Fields;

/**
 * How much the site asks for a field the visitor fills in (spec §10.5): the setting
 * `users.fields.<name>` of the site, so a site changes it in the panel and not in code.
 */
enum Requirement: string
{
    /** Not asked for: neither in the registration nor in the profile. */
    case Hidden = 'hidden';

    /** Asked for, may stay empty. */
    case Optional = 'optional';

    /** Asked for and may not stay empty — nor be emptied in the profile. */
    case Required = 'required';
}
