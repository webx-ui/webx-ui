<?php

declare(strict_types=1);

namespace WebxUi\Themes\Checks;

/**
 * Rule 37 of spec §16.1 in a template of the chain — a view of a layer or the template of one of
 * its block types: every `<img>` says how big it is, `width` and `height`, so the browser keeps
 * its place before the file arrives. Without them the text below jumps when the picture loads —
 * the layout shift a visitor feels and the search engine measures — and nothing on a fast
 * developer's machine shows it.
 *
 * The values may be Blade (`width="{{ $picture['width'] }}"`) and may sit under a condition
 * (`@if ($picture['width']) width=… @endif`): a picture of the library whose size is unknown has
 * none to give, and that is decided when the page runs. A tag that takes its attributes from the
 * component (`{{ $attributes }}`) is not judged — the caller names them. Blade comments are not
 * read.
 */
final class ImageCheck
{
    /**
     * @return list<array{line: int, message: string}>
     */
    public static function check(string $template): array
    {
        $source = self::blank($template);
        $findings = [];

        if (preg_match_all('/<img\b[^>]*>/i', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        foreach ($matches[0] as [$tag, $offset]) {
            $original = substr($template, $offset, strlen($tag));

            if (preg_match('/\$attributes\b/', $original) === 1) {
                continue;
            }

            $missing = array_values(array_filter(
                ['width', 'height'],
                static fn (string $name): bool => preg_match('/[\s"\'}]'.$name.'\s*=/i', $tag) !== 1,
            ));

            if ($missing !== []) {
                $findings[] = [
                    'line' => substr_count($source, "\n", 0, $offset) + 1,
                    'message' => '<img> without '.implode(' and ', $missing).' — the page jumps when the picture arrives; give the size of the file, or the size the layout draws it at (rule 37)',
                ];
            }
        }

        return $findings;
    }

    /**
     * The template with Blade comments, PHP, echoes and the parentheses of directives blanked out, the
     * length and the lines kept: a `->` inside `{{ }}` or `@if ()` is not the end of a tag.
     */
    private static function blank(string $template): string
    {
        $keep = static fn (array $match): string => (string) preg_replace('/[^\n]/', ' ', $match[0]);

        $source = (string) preg_replace_callback('/\{\{--.*?--\}\}/s', $keep, $template);
        $source = (string) preg_replace_callback('/@php\b(?!\s*\().*?@endphp|<\?php.*?\?>/s', $keep, $source);
        $source = (string) preg_replace_callback('/\{!!.*?!!\}|\{\{.*?\}\}/s', $keep, $source);

        // A directive's arguments may hold parentheses of their own: walk them to the balance.
        $length = strlen($source);

        if (preg_match_all('/@[a-zA-Z]+\s*\(/', $source, $directives, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($directives[0] as [$directive, $offset]) {
                $start = $offset + strlen($directive);
                $depth = 1;

                for ($at = $start; $at < $length && $depth > 0; $at++) {
                    $depth += match ($source[$at]) {
                        '(' => 1,
                        ')' => -1,
                        default => 0,
                    };

                    if ($depth > 0 && $source[$at] !== "\n") {
                        $source[$at] = ' ';
                    }
                }
            }
        }

        return $source;
    }
}
