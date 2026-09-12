<?php

declare(strict_types=1);

namespace WebxUi\Auth\Events;

use WebxUi\Auth\Models\CmsUser;

class AdminLoggedIn
{
    public function __construct(
        public readonly CmsUser $user,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
    ) {}
}
