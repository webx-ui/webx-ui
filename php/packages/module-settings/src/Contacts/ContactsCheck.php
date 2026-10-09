<?php

declare(strict_types=1);

namespace WebxUi\Settings\Contacts;

use DateTimeZone;
use Illuminate\Validation\ValidationException;

/**
 * What the field types cannot say about the "Contacts" tab, checked when it is saved — from the
 * panel and over MCP alike. A number without its country code is refused here with a word on
 * what to add (§12.1), rather than kept and turned into a link that rings somebody else.
 */
final class ContactsCheck
{
    /**
     * @param  array<string, mixed>  $stored  what `ScreenValues::validate()` is about to save
     *
     * @throws ValidationException
     */
    public static function check(array $stored): void
    {
        $errors = [];

        foreach (self::rows($stored, Contacts::KEYS['phones']) as $row => $item) {
            $number = is_string($item['number'] ?? null) ? trim($item['number']) : '';

            $problem = match (true) {
                $number === '' => 'phone-missing',
                PhoneNumber::problem($number) === PhoneNumber::NO_COUNTRY => 'phone-no-country',
                PhoneNumber::problem($number) === PhoneNumber::INVALID => 'phone-invalid',
                default => null,
            };

            if ($problem !== null) {
                $errors[Contacts::KEYS['phones']][] = self::message($row, $problem, ['number' => $number]);
            }
        }

        foreach (self::rows($stored, Contacts::KEYS['emails']) as $row => $item) {
            $email = is_string($item['email'] ?? null) ? trim($item['email']) : '';

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[Contacts::KEYS['emails']][] = self::message($row, 'email-invalid', ['email' => $email]);
            }
        }

        foreach (self::rows($stored, Contacts::KEYS['addresses']) as $row => $item) {
            $map = is_string($item['map'] ?? null) ? trim($item['map']) : '';

            if ($map !== '' && ! Contacts::isWebLink($map)) {
                $errors[Contacts::KEYS['addresses']][] = self::message($row, 'link-invalid', ['url' => $map]);
            }
        }

        foreach (['messengers' => 'channel', 'socials' => 'network'] as $list => $kind) {
            foreach (self::rows($stored, Contacts::KEYS[$list]) as $row => $item) {
                $url = is_string($item['url'] ?? null) ? trim($item['url']) : '';

                if (! is_string($item[$kind] ?? null) || $item[$kind] === '') {
                    $errors[Contacts::KEYS[$list]][] = self::message($row, $list === 'socials' ? 'network-missing' : 'channel-missing');
                } elseif (! Contacts::isLink($url, $list === 'messengers')) {
                    $errors[Contacts::KEYS[$list]][] = self::message($row, 'link-invalid', ['url' => $url]);
                }
            }
        }

        foreach (self::rows($stored, Contacts::KEYS['hours']) as $row => $item) {
            if (($item['days'] ?? []) === [] || ! is_string($item['opens'] ?? null) || ! is_string($item['closes'] ?? null)) {
                $errors[Contacts::KEYS['hours']][] = self::message($row, 'hours-incomplete');
            }
        }

        foreach (self::rows($stored, Contacts::KEYS['exceptions']) as $row => $item) {
            $open = ($item['closed'] ?? true) === false;

            if (! is_string($item['date'] ?? null) || ($open && (! is_string($item['opens'] ?? null) || ! is_string($item['closes'] ?? null)))) {
                $errors[Contacts::KEYS['exceptions']][] = self::message($row, 'exception-incomplete');
            }
        }

        $zone = $stored[Contacts::KEYS['timezone']] ?? null;

        if (is_string($zone) && trim($zone) !== '' && ! in_array(trim($zone), DateTimeZone::listIdentifiers(), true)) {
            $errors[Contacts::KEYS['timezone']][] = (string) __('webx-settings::screen.timezone-unknown', ['zone' => trim($zone)]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<int, array<string, mixed>> by position, from 0
     */
    private static function rows(array $stored, string $key): array
    {
        $rows = $stored[$key] ?? null;

        return is_array($rows) ? array_filter(array_values($rows), is_array(...)) : [];
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function message(int $row, string $key, array $replace = []): string
    {
        return (string) __('webx-settings::screen.row', [
            'row' => $row + 1,
            'message' => (string) __("webx-settings::screen.{$key}", $replace),
        ]);
    }
}
