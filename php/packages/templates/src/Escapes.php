<?php

declare(strict_types=1);

namespace WebxUi\Templates;

use Closure;
use InvalidArgumentException;

/**
 * How a value goes into a file of a format. A value passes the strategy once and the text of the
 * template never does. `escapeForFormat()` of `@webx-ui/core` is the same table in the browser;
 * `tests/fixtures/render.json` holds the two together.
 *
 * The second argument says the value is a {@see Cdata}: only `xml` has anything to do with it, the
 * others take its text like any value.
 */
final class Escapes
{
    public const FORMATS = ['xml', 'json', 'csv', 'text'];

    /** Without the last flag PHP escapes U+2028 and `JSON.stringify` does not. */
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_INVALID_UTF8_SUBSTITUTE;

    /** What an XML 1.0 parser refuses even as an entity. */
    private const NOT_XML = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x{FFFE}\x{FFFF}]/u';

    /**
     * @param  array{delimiter?: string}  $options  `delimiter` of `csv`, a comma unless said
     * @return Closure(string, bool=): string
     *
     * @throws InvalidArgumentException for a format nobody knows
     */
    public static function for(string $format, array $options = []): Closure
    {
        $delimiter = $options['delimiter'] ?? ',';

        return match ($format) {
            'xml' => self::xml(...),
            'json' => self::json(...),
            'csv' => static fn (string $value): string => self::csv($value, $delimiter),
            'text' => static fn (string $value): string => $value,
            default => throw new InvalidArgumentException("[{$format}] is not a format of a template: ".implode(', ', self::FORMATS).'.'),
        };
    }

    private static function xml(string $value, bool $cdata = false): string
    {
        $clean = (string) preg_replace(self::NOT_XML, '', mb_scrub($value, 'UTF-8'));

        if ($cdata) {
            return '<![CDATA['.str_replace(']]>', ']]]]><![CDATA[>', $clean).']]>';
        }

        return strtr($clean, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;', '"' => '&quot;', "'" => '&apos;']);
    }

    /** The inside of a JSON string: the quotes are the template's. */
    private static function json(string $value): string
    {
        return substr((string) json_encode($value, self::JSON), 1, -1);
    }

    private static function csv(string $value, string $delimiter): string
    {
        if (strpbrk($value, "\"\r\n") === false && ! str_contains($value, $delimiter)) {
            return $value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }
}
