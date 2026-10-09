<?php

declare(strict_types=1);

namespace WebxUi\Settings\Contacts;

/**
 * A way to reach the site somewhere else: a messenger (`telegram`, a chat with a number or a
 * bot) or a social network (`instagram`). `kind` names the icon and the brand; `label` is the
 * row's own words, when it has them, on the current language.
 */
final class Channel
{
    /** The messengers a phone number can be on, and the link that opens a chat with it. */
    public const array ON_A_NUMBER = ['whatsapp', 'telegram', 'viber', 'signal'];

    public function __construct(
        public readonly string $kind,
        public readonly string $url,
        public readonly ?string $label = null,
    ) {}

    /** A chat with the number in a messenger — or null for one that cannot open a number. */
    public static function forNumber(string $kind, PhoneNumber $number): ?self
    {
        $digits = $number->digits();

        $url = match ($kind) {
            'whatsapp' => "https://wa.me/{$digits}",
            'telegram' => "https://t.me/+{$digits}",
            'viber' => "viber://chat?number=%2B{$digits}",
            'signal' => "https://signal.me/#p/+{$digits}",
            default => null,
        };

        return $url === null ? null : new self($kind, $url);
    }
}
