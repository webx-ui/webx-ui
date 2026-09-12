<?php

declare(strict_types=1);

namespace WebxUi\Localization;

/**
 * The languages a site may be published in.
 *
 * Reference data, not a promise: a code being here means we know what to call it and which way
 * it reads, nothing more. Whether the panel has been translated into it is a separate question
 * — see `webx-localization.panel` — and conflating the two is how a half-translated interface
 * ends up in front of somebody.
 */
final class LocaleCatalogue
{
    /**
     * Keyed by BCP-47 code: the English name, the name in the language itself, and direction.
     *
     * @var array<string, array{name: string, native: string, direction: string}>
     */
    private const LOCALES = [
        'en' => ['name' => 'English', 'native' => 'English', 'direction' => 'ltr'],
        'ru' => ['name' => 'Russian', 'native' => 'Русский', 'direction' => 'ltr'],
        'uk' => ['name' => 'Ukrainian', 'native' => 'Українська', 'direction' => 'ltr'],
        'be' => ['name' => 'Belarusian', 'native' => 'Беларуская', 'direction' => 'ltr'],
        'kk' => ['name' => 'Kazakh', 'native' => 'Қазақша', 'direction' => 'ltr'],
        'pl' => ['name' => 'Polish', 'native' => 'Polski', 'direction' => 'ltr'],
        'cs' => ['name' => 'Czech', 'native' => 'Čeština', 'direction' => 'ltr'],
        'sk' => ['name' => 'Slovak', 'native' => 'Slovenčina', 'direction' => 'ltr'],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'direction' => 'ltr'],
        'fr' => ['name' => 'French', 'native' => 'Français', 'direction' => 'ltr'],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'direction' => 'ltr'],
        'it' => ['name' => 'Italian', 'native' => 'Italiano', 'direction' => 'ltr'],
        'pt' => ['name' => 'Portuguese', 'native' => 'Português', 'direction' => 'ltr'],
        'nl' => ['name' => 'Dutch', 'native' => 'Nederlands', 'direction' => 'ltr'],
        'sv' => ['name' => 'Swedish', 'native' => 'Svenska', 'direction' => 'ltr'],
        'nb' => ['name' => 'Norwegian', 'native' => 'Norsk', 'direction' => 'ltr'],
        'da' => ['name' => 'Danish', 'native' => 'Dansk', 'direction' => 'ltr'],
        'fi' => ['name' => 'Finnish', 'native' => 'Suomi', 'direction' => 'ltr'],
        'is' => ['name' => 'Icelandic', 'native' => 'Íslenska', 'direction' => 'ltr'],
        'et' => ['name' => 'Estonian', 'native' => 'Eesti', 'direction' => 'ltr'],
        'lv' => ['name' => 'Latvian', 'native' => 'Latviešu', 'direction' => 'ltr'],
        'lt' => ['name' => 'Lithuanian', 'native' => 'Lietuvių', 'direction' => 'ltr'],
        'ro' => ['name' => 'Romanian', 'native' => 'Română', 'direction' => 'ltr'],
        'bg' => ['name' => 'Bulgarian', 'native' => 'Български', 'direction' => 'ltr'],
        'hr' => ['name' => 'Croatian', 'native' => 'Hrvatski', 'direction' => 'ltr'],
        'sr' => ['name' => 'Serbian', 'native' => 'Српски', 'direction' => 'ltr'],
        'sl' => ['name' => 'Slovenian', 'native' => 'Slovenščina', 'direction' => 'ltr'],
        'hu' => ['name' => 'Hungarian', 'native' => 'Magyar', 'direction' => 'ltr'],
        'el' => ['name' => 'Greek', 'native' => 'Ελληνικά', 'direction' => 'ltr'],
        'tr' => ['name' => 'Turkish', 'native' => 'Türkçe', 'direction' => 'ltr'],
        'ka' => ['name' => 'Georgian', 'native' => 'ქართული', 'direction' => 'ltr'],
        'hy' => ['name' => 'Armenian', 'native' => 'Հայերեն', 'direction' => 'ltr'],
        'az' => ['name' => 'Azerbaijani', 'native' => 'Azərbaycan', 'direction' => 'ltr'],
        'he' => ['name' => 'Hebrew', 'native' => 'עברית', 'direction' => 'rtl'],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'direction' => 'rtl'],
        'zh' => ['name' => 'Chinese', 'native' => '中文', 'direction' => 'ltr'],
        'ja' => ['name' => 'Japanese', 'native' => '日本語', 'direction' => 'ltr'],
        'ko' => ['name' => 'Korean', 'native' => '한국어', 'direction' => 'ltr'],
    ];

    /**
     * @return array<string, array{name: string, native: string, direction: string}>
     */
    public static function all(): array
    {
        return self::LOCALES;
    }

    public static function has(string $code): bool
    {
        return isset(self::LOCALES[self::normalise($code)]);
    }

    /**
     * @return array{name: string, native: string, direction: string}|null
     */
    public static function get(string $code): ?array
    {
        return self::LOCALES[self::normalise($code)] ?? null;
    }

    /**
     * A language that is not in the catalogue still has to be nameable: a site may legitimately
     * publish in something we have never heard of, and refusing it would make the catalogue a
     * gate rather than a convenience.
     *
     * @return array{name: string, native: string, direction: string}
     */
    public static function describe(string $code): array
    {
        return self::get($code) ?? [
            'name' => $code,
            'native' => $code,
            'direction' => 'ltr',
        ];
    }

    /**
     * `ru_RU` and `pt-br` name the same things as `ru` and `pt-BR`; the region keeps its case
     * so a code can be handed to Intl and to the `lang` attribute unchanged.
     */
    public static function normalise(string $code): string
    {
        $parts = preg_split('/[-_]/', trim($code)) ?: [];
        $language = strtolower((string) ($parts[0] ?? ''));

        if (count($parts) < 2 || $parts[1] === '') {
            return $language;
        }

        $region = (string) $parts[1];

        return $language.'-'.(strlen($region) === 4 ? ucfirst(strtolower($region)) : strtoupper($region));
    }
}
