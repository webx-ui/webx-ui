<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Panel;

/**
 * The settings of a form (§5), and what is allowed to be in them.
 *
 * A white list rather than "save whatever arrived": `options` is a JSON column the panel
 * writes wholesale, and a column like that is the easiest place in an application to hide
 * something nobody meant to store.
 *
 * The keys have dots in them and the dots are literal — `thank-you.heading` is one key, the
 * way a field name is in `module-settings`. That is why nothing here goes through `Arr::get`
 * or through a validation rule named after the key: both would read the dot as a path into a
 * `thank-you` array that does not exist, and the value would vanish without an error
 * (CLAUDE.md §4).
 */
final class FormOptions
{
    /** Words, in every language the site publishes in. */
    public const TRANSLATED = ['thank-you.heading', 'thank-you.text', 'design.submit-text'];

    /** Plain strings. */
    public const TEXT = ['redirect', 'email_field'];

    public const CAPTCHAS = ['off', 'recaptcha', 'turnstile'];

    /**
     * What a saved form may hold, taken out of what arrived.
     *
     * Everything unknown is dropped, and so is everything empty: a form whose options are
     * twelve blank strings reads as configured when it is not, and `option()` already answers
     * with a default for a key that is simply absent.
     *
     * @param  array<mixed>  $input
     * @return array<string, mixed>
     */
    public static function clean(array $input): array
    {
        $options = [];

        foreach (self::TRANSLATED as $key) {
            $words = self::words($input[$key] ?? null);

            if ($words !== [] && $words !== '') {
                $options[$key] = $words;
            }
        }

        foreach (self::TEXT as $key) {
            $text = is_scalar($input[$key] ?? null) ? trim((string) $input[$key]) : '';

            if ($text !== '') {
                $options[$key] = $text;
            }
        }

        $recipients = self::recipients($input['recipients'] ?? null);

        if ($recipients !== []) {
            $options['recipients'] = $recipients;
        }

        // The antispam settings are written even when they match the site's defaults: each one
        // is a decision somebody made about this form, and a form that silently inherits a
        // default changed later is a form that starts refusing submissions on its own.
        if (array_key_exists('antispam.honeypot', $input)) {
            $options['antispam.honeypot'] = self::boolean($input['antispam.honeypot']);
        }

        foreach (['antispam.min_seconds', 'antispam.throttle'] as $key) {
            if (isset($input[$key]) && is_numeric($input[$key])) {
                $options[$key] = max(0, (int) $input[$key]);
            }
        }

        $captcha = $input['antispam.captcha'] ?? null;

        if (is_string($captcha) && in_array($captcha, self::CAPTCHAS, true)) {
            $options['antispam.captcha'] = $captcha;
        }

        return $options;
    }

    /**
     * Who is written to (§5). Two shapes: somebody with an account, or an address typed in.
     *
     * @return list<array<string, mixed>>
     */
    public static function recipients(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $recipients = [];

        foreach ($input as $recipient) {
            if (! is_array($recipient)) {
                continue;
            }

            if (isset($recipient['admin_id']) && is_numeric($recipient['admin_id'])) {
                $recipients[] = ['admin_id' => (int) $recipient['admin_id']];

                continue;
            }

            $email = $recipient['email'] ?? null;

            if (is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false) {
                $recipients[] = ['email' => trim($email)];
            }
        }

        return $recipients;
    }

    /**
     * A language map, emptied of the languages nothing was written in.
     *
     * A plain string is accepted and kept as one: that is what a form written before the site
     * had a second language holds, and rewriting it here would decide which language it was in.
     *
     * @return array<string, string>|string
     */
    private static function words(mixed $value): array|string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (! is_array($value)) {
            return [];
        }

        $words = [];

        foreach ($value as $locale => $line) {
            if (is_string($line) && trim($line) !== '') {
                $words[(string) $locale] = $line;
            }
        }

        return $words;
    }

    private static function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
