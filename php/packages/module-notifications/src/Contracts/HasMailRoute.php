<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Contracts;

use WebxUi\Admin\Mail\MailRoute;

/**
 * A provider whose messages leave by mail, and can say which way: the site's mailer or a server
 * of the channel's. What lets the panel and the agent show where a channel's letters go, and the
 * log tell a letter a trap took from one that was delivered.
 */
interface HasMailRoute
{
    /**
     * @param  array<string, mixed>  $options  the channel's open settings
     */
    public function route(array $options): MailRoute;
}
