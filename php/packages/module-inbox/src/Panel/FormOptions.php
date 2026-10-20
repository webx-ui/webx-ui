<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Panel;

use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Notifications\Channels;

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
    public const TEXT = ['email_field'];

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

        $redirect = self::redirect($input['redirect'] ?? null);

        if ($redirect !== null) {
            $options['redirect'] = $redirect->toArray();
        }

        // Kept for both ways and switched between: the addresses stay on a form told through
        // channels, so going back to letters finds them as they were.
        $via = $input[Form::NOTIFY_VIA] ?? null;

        if (in_array($via, [Form::VIA_EMAIL, Form::VIA_CHANNELS], true)) {
            $options[Form::NOTIFY_VIA] = $via;
        }

        $recipients = self::recipients($input['recipients'] ?? null);

        if ($recipients !== []) {
            $options['recipients'] = $recipients;
        }

        // The channels of the notifications module (NOTIFICATIONS §15.2): ids, each once, and only
        // ones that exist — a channel deleted for good drops out, one in the bin stays.
        $channels = app(Channels::class)->existing(Channels::ids($input[Channels::OPTION] ?? null));

        if ($channels !== []) {
            $options[Channels::OPTION] = $channels;
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
     * Where the visitor goes once the form is sent, as a link of the panel (`wx-link`): a record of
     * the site, or an address — and nothing for the thank-you in place of the form.
     *
     * A plain string is what a form saved before there was a choice holds, and it is the address
     * it always was. Opening in a new tab and `rel` are not kept: this is a redirect, not a link
     * anybody clicks.
     */
    public static function redirect(mixed $input): ?Link
    {
        if (is_string($input)) {
            $input = ['target' => LinkTarget::Url->value, 'url' => $input];
        }

        if (! is_array($input)) {
            return null;
        }

        $link = Link::fromArray($input);

        if ($link->isEmpty()) {
            return null;
        }

        return new Link($link->target, $link->entityType, $link->entityId, $link->url, $link->hash);
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
