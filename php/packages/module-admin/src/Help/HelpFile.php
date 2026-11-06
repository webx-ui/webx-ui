<?php

declare(strict_types=1);

namespace WebxUi\Admin\Help;

/**
 * One file of help taken apart: a short header, then sections named by fixed English words
 * (WEBX_UI_PANEL_HELP.md §2.2). The words never reach the screen, which is why the same parser
 * reads every language.
 *
 * Only the shape of the file is refused here. Whether a text may hold a table or repeats the
 * hint of its field is for the coverage test: those do not stop a page from being shown.
 */
final readonly class HelpFile
{
    private const array SECTIONS = ['about', 'howto', 'fields'];

    private const array HEADER = ['title', 'guide', 'include'];

    /**
     * @param  list<string>  $include
     * @param  list<array{title: string, body: string}>  $howto
     * @param  array<string, string>  $fields  By node id, or by the words of a label on a page
     *                                         with no tree; in the order of the file.
     */
    public function __construct(
        public string $path,
        public string $locale,
        public ?string $title = null,
        public ?string $guide = null,
        public array $include = [],
        public string $about = '',
        public array $howto = [],
        public array $fields = [],
    ) {}

    public static function read(string $path, string $locale): self
    {
        $text = @file_get_contents($path);

        if ($text === false) {
            throw HelpException::unreadable($path);
        }

        return self::parse($text, $path, $locale);
    }

    public static function parse(string $text, string $path, string $locale): self
    {
        $text = ltrim(str_replace(["\r\n", "\r"], "\n", $text), "\u{FEFF}\n ");

        /** @var list<string> $lines */
        $lines = explode("\n", $text);

        $header = self::header($lines, $path);
        $parts = self::parts($lines, $path);

        return new self(
            path: $path,
            locale: $locale,
            title: $header['title'] ?? null,
            guide: $header['guide'] ?? null,
            include: self::keys($header['include'] ?? ''),
            about: $parts['about'],
            howto: $parts['howto'],
            fields: $parts['fields'],
        );
    }

    /**
     * Whether this file speaks for the screen itself. A satellite brings only fields and
     * scenarios; a title and an about are what makes a page of help out of them.
     */
    public function isOwner(): bool
    {
        return $this->title !== null && $this->about !== '';
    }

    /**
     * Takes the header off the lines.
     *
     * Not YAML: the three keys hold one line each, and a parser for them is not worth a
     * dependency of the panel.
     *
     * @param  list<string>  $lines
     * @return array<string, string>
     */
    private static function header(array &$lines, string $path): array
    {
        if (trim($lines[0] ?? '') !== '---') {
            return [];
        }

        $end = null;

        foreach ($lines as $index => $line) {
            if ($index > 0 && trim($line) === '---') {
                $end = $index;
                break;
            }
        }

        if ($end === null) {
            throw HelpException::invalid($path, 'the header is not closed with ---');
        }

        $header = [];

        foreach (array_slice($lines, 1, $end - 1) as $line) {
            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^([a-z]+):(.*)$/', $line, $match) !== 1 || ! in_array($match[1], self::HEADER, true)) {
                throw HelpException::invalid($path, "the header line [{$line}] is not one of title, guide, include");
            }

            $value = self::unquoted(trim($match[2]));

            if ($value !== '') {
                $header[$match[1]] = $value;
            }
        }

        $lines = array_slice($lines, $end + 1);

        return $header;
    }

    /**
     * @param  list<string>  $lines
     * @return array{about: string, howto: list<array{title: string, body: string}>, fields: array<string, string>}
     */
    private static function parts(array $lines, string $path): array
    {
        $parts = ['about' => '', 'howto' => [], 'fields' => []];

        foreach (self::blocks($lines, $path) as $block) {
            $body = trim(implode("\n", $block['lines']));

            if ($block['heading'] === null) {
                if ($block['section'] === 'about') {
                    $parts['about'] = $body;
                } elseif ($body !== '') {
                    throw HelpException::invalid($path, "the text under # {$block['section']} has no ## heading");
                }

                continue;
            }

            if ($block['section'] === 'howto') {
                $parts['howto'][] = ['title' => $block['heading'], 'body' => $body];

                continue;
            }

            if (array_key_exists($block['heading'], $parts['fields'])) {
                throw HelpException::invalid($path, "the field [{$block['heading']}] is described twice");
            }

            $parts['fields'][$block['heading']] = $body;
        }

        return $parts;
    }

    /**
     * Cuts the lines at the headings of the file. Anything between code fences is text: a `#`
     * there is a comment in an example, not a section.
     *
     * @param  list<string>  $lines
     * @return list<array{section: string, heading: ?string, lines: list<string>}>
     */
    private static function blocks(array $lines, string $path): array
    {
        $blocks = [];
        $seen = [];
        $section = null;
        $fenced = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, '```')) {
                $fenced = ! $fenced;
            }

            if (! $fenced && preg_match('/^(#{1,2})\s+(.+?)\s*$/', $line, $match) === 1) {
                $heading = $match[2];

                if ($match[1] === '#') {
                    if (! in_array($heading, self::SECTIONS, true)) {
                        throw HelpException::invalid($path, "[# {$heading}] is not one of about, howto, fields");
                    }

                    if (isset($seen[$heading])) {
                        throw HelpException::invalid($path, "the section # {$heading} comes twice");
                    }

                    $seen[$heading] = true;
                    $section = $heading;
                    $blocks[] = ['section' => $section, 'heading' => null, 'lines' => []];

                    continue;
                }

                if ($section === null || $section === 'about') {
                    throw HelpException::invalid($path, "[## {$heading}] belongs under # howto or # fields");
                }

                $blocks[] = ['section' => $section, 'heading' => $heading, 'lines' => []];

                continue;
            }

            if ($blocks === []) {
                if (trim($line) !== '') {
                    throw HelpException::invalid($path, 'there is text before the first section');
                }

                continue;
            }

            $blocks[array_key_last($blocks)]['lines'][] = $line;
        }

        return $blocks;
    }

    /**
     * `a, b` and `[a, b]` are the same list: the second is what a writer used to YAML types.
     *
     * @return list<string>
     */
    private static function keys(string $value): array
    {
        $keys = array_map(
            static fn (string $key): string => self::unquoted(trim($key)),
            explode(',', trim($value, "[] \t")),
        );

        return array_values(array_filter($keys, static fn (string $key): bool => $key !== ''));
    }

    private static function unquoted(string $value): string
    {
        if (preg_match('/^(["\'])(.*)\1$/', $value, $match) === 1) {
            return $match[2];
        }

        return $value;
    }
}
