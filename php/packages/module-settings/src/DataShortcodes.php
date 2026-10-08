<?php

declare(strict_types=1);

namespace WebxUi\Settings;

use WebxUi\Admin\Shortcodes\Shortcode;
use WebxUi\Admin\Shortcodes\Shortcodes;

/**
 * The shortcodes the panel defines: «Settings» → «Shortcodes», a list of names, each reading a
 * setting (`contacts.phone`) or holding its value right there.
 *
 * So that a phone number, an e-mail or an address is typed once, in one place, and content says
 * `[phone]`. A new one needs no developer — that is the difference from a shortcode a site
 * registers in code ({@see Shortcodes::register()}), which wins when both use a name.
 *
 * What a value looks like decides what it prints: a phone number is a `tel:` link, an e-mail a
 * `mailto:` link, anything else the text, line breaks kept. In plain text it is always the value.
 * Arguments: `link=no` prints the value without the link; `format=intl` prints a phone number as
 * `+` and its digits, `format=digits` as the digits alone.
 */
final class DataShortcodes
{
    /** The setting the list is kept in. */
    public const KEY = 'shortcodes.data';

    public function __construct(private readonly Settings $settings) {}

    /**
     * @return list<Shortcode>
     */
    public function all(): array
    {
        $items = $this->settings->get(self::KEY);

        if (! is_array($items)) {
            return [];
        }

        $shortcodes = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = strtolower(trim((string) ($item['name'] ?? '')));
            $key = trim((string) ($item['key'] ?? ''));

            if (preg_match(Shortcodes::NAME, $name) !== 1) {
                continue;
            }

            // The setting when one is named — and it is not this list, which would read itself.
            $value = $key !== '' && $key !== self::KEY ? $this->settings->get($key) : ($item['value'] ?? null);
            $value = is_scalar($value) ? trim((string) $value) : '';

            $shortcodes[] = new Shortcode(
                $name,
                static fn (array $args): string => self::html($value, $args),
                static fn (array $args): string => self::plain($value, $args),
                $key !== '' ? $key : null,
                'settings',
            );
        }

        return $shortcodes;
    }

    /** `phone`, `email` or `text`: what the value looks like, and so how it is printed. */
    public static function kind(string $value): string
    {
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            return 'email';
        }

        // Written every which way across countries — spaces, dots, dashes, brackets, a plus — so
        // only the characters are held, and a number's worth of digits.
        $digits = strlen((string) preg_replace('/\D/', '', $value));

        return preg_match('/^\+?[\d\s().\-\/]+$/', $value) === 1 && $digits >= 6 && $digits <= 15 ? 'phone' : 'text';
    }

    /**
     * @param  array<string, string>  $args
     */
    public static function html(string $value, array $args = []): string
    {
        $kind = self::kind($value);
        $shown = e(self::plain($value, $args));
        $link = ! in_array(strtolower($args['link'] ?? 'yes'), ['no', 'false', '0', 'off'], true);

        return match (true) {
            $kind === 'email' && $link => '<a href="mailto:'.e($value).'">'.$shown.'</a>',
            $kind === 'phone' && $link => '<a href="tel:'.e(self::dial($value)).'">'.$shown.'</a>',
            default => nl2br($shown, false),
        };
    }

    /**
     * @param  array<string, string>  $args
     */
    public static function plain(string $value, array $args = []): string
    {
        if (self::kind($value) !== 'phone') {
            return $value;
        }

        return match ($args['format'] ?? null) {
            'intl' => self::dial($value),
            'digits' => (string) preg_replace('/\D/', '', $value),
            default => $value,
        };
    }

    /** What a phone dials: the leading plus, if there is one, and the digits. */
    private static function dial(string $value): string
    {
        return (str_starts_with(ltrim($value), '+') ? '+' : '').preg_replace('/\D/', '', $value);
    }
}
