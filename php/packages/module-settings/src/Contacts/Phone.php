<?php

declare(strict_types=1);

namespace WebxUi\Settings\Contacts;

/**
 * One number of the site: shown as typed (`number`), dialled as E.164 (`e164`, `href` — the
 * `tel:` link, with an extension when it has one), with its words ("Sales") and the messengers
 * it is on.
 */
final class Phone
{
    /**
     * @param  list<Channel>  $messengers
     */
    public function __construct(
        public readonly string $number,
        public readonly string $e164,
        public readonly string $href,
        public readonly ?string $label,
        public readonly bool $primary,
        public readonly array $messengers,
    ) {}
}
