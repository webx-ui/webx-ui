<?php

declare(strict_types=1);

namespace WebxUi\Geography;

use WebxUi\Localization\Locales;

/** Names by language, as every `names` column of the geography holds them. */
final class Names
{
    /**
     * The name in the language asked for, else in the site's main one, else any: a place a
     * carrier named only in its own language still has a name in every other.
     *
     * @param  array<string, mixed>|null  $names
     */
    public static function pick(?array $names, ?string $locale = null): ?string
    {
        $names = self::clean($names);

        if ($names === []) {
            return null;
        }

        foreach (app(Locales::class)->chain($locale) as $code) {
            if (isset($names[$code])) {
                return $names[$code];
            }
        }

        return reset($names);
    }

    /**
     * The name a key is taken from: the site's main language first, so that the key of a
     * record does not move when someone adds a translation.
     *
     * @param  array<string, mixed>|null  $names
     */
    public static function main(?array $names): ?string
    {
        $names = self::clean($names);
        $default = app(Locales::class)->defaultCode();

        return $names[$default] ?? (reset($names) ?: null);
    }

    /**
     * Trimmed, without empty entries, without anything that is not a string.
     *
     * @param  array<string, mixed>|null  $names
     * @return array<string, string>
     */
    public static function clean(?array $names): array
    {
        $clean = [];

        foreach ($names ?? [] as $locale => $name) {
            if (! is_string($locale) || ! is_string($name)) {
                continue;
            }

            $name = trim((string) preg_replace('/\s+/u', ' ', $name));

            if ($name !== '') {
                $clean[$locale] = mb_substr($name, 0, Normalizer::MAX_LENGTH, 'UTF-8');
            }
        }

        return $clean;
    }
}
