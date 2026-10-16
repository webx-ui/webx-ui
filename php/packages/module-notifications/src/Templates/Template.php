<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Templates;

use Closure;

/**
 * `{{name}}` and nothing else (§7): no loops, no conditions, no filters, no calls. A template is
 * stored in the database and is never compiled by Blade nor evaluated — rendering is one pass of
 * string replacement, so a value somebody typed with `{{…}}` in it is not expanded again.
 *
 * Whatever does not match the pattern is text as it is: a lone `{`, `{{ Name }}` with a capital.
 */
final class Template
{
    public const PATTERN = '/\{\{\s*([a-z0-9_]+(?:\.[a-z0-9_]+)?)\s*\}\}/';

    /**
     * The variables a template uses, each once, in order of appearance.
     *
     * @return list<string>
     */
    public static function names(string $template): array
    {
        preg_match_all(self::PATTERN, $template, $found);

        return array_values(array_unique($found[1]));
    }

    /**
     * The template with the values in, each through `$escape`. A name with no value is empty;
     * it is reported as unknown unless `$known` says the event declares it (an empty field is
     * not a mistake in the template).
     *
     * @param  array<string, string>  $values
     * @param  Closure(string): string  $escape
     * @param  (Closure(string): bool)|null  $known
     * @return array{text: string, unknown: list<string>}
     */
    public static function render(string $template, array $values, Closure $escape, ?Closure $known = null): array
    {
        $unknown = [];

        $text = preg_replace_callback(self::PATTERN, static function (array $match) use ($values, $escape, $known, &$unknown): string {
            $name = $match[1];

            if (! array_key_exists($name, $values)) {
                if ($known === null || ! $known($name)) {
                    $unknown[$name] = true;
                }

                return '';
            }

            return $escape($values[$name]);
        }, $template);

        return ['text' => (string) $text, 'unknown' => array_keys($unknown)];
    }
}
