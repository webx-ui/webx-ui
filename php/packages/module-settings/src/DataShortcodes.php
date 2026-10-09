<?php

declare(strict_types=1);

namespace WebxUi\Settings;

use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Shortcodes\Shortcode;
use WebxUi\Admin\Shortcodes\Shortcodes;

/**
 * The shortcodes the panel defines: «Settings» → «Shortcodes», a list of names, each reading a
 * setting (`contacts.phone`) or holding its value right there — which of the two is the row's
 * `source`.
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

    /** A row that prints a setting, named by its `key`. */
    public const SOURCE_SETTING = 'setting';

    /** A row that prints its own `value`. */
    public const SOURCE_VALUE = 'value';

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

        // The source as stored, row for row: resolving fills a missing one with the form's
        // default, and a row saved before the switch existed would read as "its own value".
        $stored = $this->settings->raw()[self::KEY] ?? null;
        $stored = is_array($stored) ? array_values(array_filter($stored, is_array(...))) : [];
        $shortcodes = [];

        foreach (array_values($items) as $position => $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = strtolower(trim((string) ($item['name'] ?? '')));
            $key = trim((string) ($item['key'] ?? ''));

            if (preg_match(Shortcodes::NAME, $name) !== 1) {
                continue;
            }

            $reads = self::source($stored[$position] ?? $item) === self::SOURCE_SETTING;

            // The setting when the row reads one — and it is not this list, which would read itself.
            $value = $reads ? ($key !== '' && $key !== self::KEY ? $this->settings->get($key) : null) : ($item['value'] ?? null);
            $value = is_scalar($value) ? trim((string) $value) : '';

            $shortcodes[] = new Shortcode(
                $name,
                static fn (array $args): string => self::html($value, $args),
                static fn (array $args): string => self::plain($value, $args),
                $reads && $key !== '' ? $key : null,
                'settings',
            );
        }

        return $shortcodes;
    }

    /**
     * What a row prints: its `source`, or — for a row saved before there was one — the setting
     * when a key is filled in, which is what the two fields side by side used to mean.
     *
     * @param  array<array-key, mixed>  $item
     */
    public static function source(array $item): string
    {
        $source = $item['source'] ?? null;

        if ($source === self::SOURCE_SETTING || $source === self::SOURCE_VALUE) {
            return $source;
        }

        return trim((string) ($item['key'] ?? '')) !== '' ? self::SOURCE_SETTING : self::SOURCE_VALUE;
    }

    /**
     * The stored list with every row's `source` spelled out, for the panel: the switch has to
     * show what an old row does, and the form's default would say "its own value" for all of them.
     */
    public static function withSources(mixed $items): mixed
    {
        if (! is_array($items)) {
            return $items;
        }

        return array_map(
            static fn (mixed $item): mixed => is_array($item) ? ['source' => self::source($item), ...$item] : $item,
            $items,
        );
    }

    /**
     * Refuses a row that reads a setting the site does not have. The key is typed or picked
     * from a list, and a typo used to print nothing at all, wherever the shortcode stood.
     *
     * @param  array<string, mixed>  $values  What is about to be saved, keyed by setting.
     *
     * @throws ValidationException
     */
    public function check(array $values): void
    {
        $items = $values[self::KEY] ?? null;

        if (! is_array($items)) {
            return;
        }

        $keys = array_diff($this->settings->keys(), [self::KEY]);
        $errors = [];

        foreach (array_values($items) as $position => $item) {
            if (! is_array($item) || self::source($item) !== self::SOURCE_SETTING) {
                continue;
            }

            $key = trim((string) ($item['key'] ?? ''));

            if ($key === '') {
                $errors[self::KEY.'.'.$position.'.key'] = [(string) __('webx-settings::screen.shortcode-key-missing')];
            } elseif (! in_array($key, $keys, true)) {
                $errors[self::KEY.'.'.$position.'.key'] = [(string) __('webx-settings::screen.shortcode-key-unknown', ['key' => $key])];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
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
