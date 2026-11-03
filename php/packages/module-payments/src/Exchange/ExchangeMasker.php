<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

/**
 * The only change made to what the journal writes (spec §20.5, decision 40), the same for every
 * satellite and done before the insert, so a secret is never in the database even for a moment.
 *
 * 1. The method's keys by **value**, not by field name — names differ from gateway to gateway, the
 *    value is known exactly: as is, url-encoded, JSON-escaped and inside base64.
 * 2. The secret headers whole.
 * 3. Derived secrets: the values of the gateway's secret fields in a JSON or form body, and what a
 *    satellite marked at run time — from then on wiped like the keys in every event of the trace.
 * 4. Card numbers: 13–19 digits passing Luhn, all but the last four starred — a second line of
 *    defence, the card is only ever typed on the gateway's page.
 *
 * The signature, payment ids, amounts and the buyer's contacts stay: they are what an event is
 * read for. What was wiped is counted by name, never by value — the `masked` column.
 */
final class ExchangeMasker
{
    public const string HEADER = '[secret:header]';

    /**
     * Wipes a body (or a URL, or an error's text) and adds to the counts.
     *
     * @param  array<string, int>  $counts
     */
    public function text(?string $text, ExchangeSecrets $secrets, array &$counts, bool $body = false): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        if ($body) {
            $this->collectFields($text, $secrets);
        }

        $text = $this->secrets($text, $secrets, $counts);

        return $this->cards($text, $counts);
    }

    /**
     * Wipes headers: the secret ones whole, the values of the rest like a body.
     *
     * @param  array<array-key, mixed>|null  $headers  name => value or list of values
     * @param  array<string, int>  $counts
     * @return array<string, list<string>>|null
     */
    public function headers(?array $headers, ExchangeSecrets $secrets, array &$counts): ?array
    {
        if ($headers === null) {
            return null;
        }

        $masked = [];

        foreach ($headers as $name => $values) {
            $name = (string) $name;
            $list = [];

            foreach (is_array($values) ? $values : [$values] as $value) {
                $value = is_scalar($value) ? (string) $value : '';

                if ($secrets->isSecretHeader($name)) {
                    $key = 'header:'.strtolower($name);
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                    $list[] = self::HEADER;

                    continue;
                }

                $list[] = (string) $this->text($value, $secrets, $counts);
            }

            $masked[$name] = $list;
        }

        return $masked;
    }

    /**
     * Every way a value of a secret may stand in a text, longest first, so that one encoding of a
     * key is not cut by a shorter key inside it.
     *
     * @return array<string, string> needle => replacement
     */
    public function needles(ExchangeSecrets $secrets): array
    {
        $needles = [];

        foreach ($secrets->values() as $value => $name) {
            $value = (string) $value;
            $replacement = '[secret:'.$name.']';

            foreach ($this->encodings($value) as $needle) {
                if ($needle !== '' && ! isset($needles[$needle])) {
                    $needles[$needle] = $replacement;
                }
            }
        }

        uksort($needles, static fn (string|int $a, string|int $b): int => strlen((string) $b) <=> strlen((string) $a));

        return $needles;
    }

    /** @return list<string> */
    private function encodings(string $value): array
    {
        $encodings = [
            $value,
            urlencode($value),
            rawurlencode($value),
            substr((string) json_encode($value), 1, -1),
            substr((string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 1, -1),
        ];

        // Inside base64 a value lands on any of three byte offsets; at each, the characters made of
        // its bytes alone are fixed whatever stands around it (two edge characters may leak a few
        // bits of the first and last byte — not a key).
        foreach ([0, 1, 2] as $offset) {
            $encoded = base64_encode(str_repeat("\0", $offset).$value);
            $from = intdiv(8 * $offset + 5, 6);
            $to = intdiv(8 * ($offset + strlen($value)), 6);
            $core = substr($encoded, $from, $to - $from);

            if (strlen($core) >= ExchangeSecrets::MIN_LENGTH) {
                $encodings[] = $core;
                $encodings[] = strtr($core, '+/', '-_');
            }
        }

        return array_values(array_unique($encodings));
    }

    /** @param  array<string, int>  $counts */
    private function secrets(string $text, ExchangeSecrets $secrets, array &$counts): string
    {
        foreach ($this->needles($secrets) as $needle => $replacement) {
            $needle = (string) $needle;
            $found = substr_count($text, $needle);

            if ($found === 0) {
                continue;
            }

            $text = str_replace($needle, $replacement, $text);
            $key = substr($replacement, 1, -1);
            $counts[$key] = ($counts[$key] ?? 0) + $found;
        }

        return $text;
    }

    /** @param  array<string, int>  $counts */
    private function cards(string $text, array &$counts): string
    {
        return (string) preg_replace_callback('/(?<![0-9])[0-9]{13,19}(?![0-9])/', static function (array $match) use (&$counts): string {
            $digits = $match[0];

            if (! self::luhn($digits)) {
                return $digits;
            }

            $counts['pan'] = ($counts['pan'] ?? 0) + 1;

            return str_repeat('*', strlen($digits) - 4).substr($digits, -4);
        }, $text);
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($double) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }

    /**
     * The values of the gateway's secret fields in a JSON or a form body — an OAuth token in the
     * answer that made it — become secrets of the trace.
     */
    private function collectFields(string $body, ExchangeSecrets $secrets): void
    {
        $fields = $secrets->fields();

        if ($fields === []) {
            return;
        }

        $data = json_decode($body, true);

        if (! is_array($data)) {
            $data = [];
            parse_str($body, $data);
        }

        $walk = static function (array $node) use (&$walk, $fields, $secrets): void {
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $walk($value);
                } elseif (is_string($value) && in_array((string) $key, $fields, true)) {
                    $secrets->add($value, (string) $key);
                }
            }
        };

        $walk($data);
    }
}
