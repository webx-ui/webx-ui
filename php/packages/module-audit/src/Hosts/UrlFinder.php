<?php

declare(strict_types=1);

namespace WebxUi\Audit\Hosts;

/**
 * Every absolute address in a piece of text — plain, HTML or JSON.
 *
 * One finder for every content source, so a module only says which fields it has. JSON may
 * arrive with escaped slashes (`http:\/\/`), which a plain search never finds; protocol-relative
 * addresses count only after a quote, a bracket or an equals sign, so a `//` in prose or in a
 * comment is not mistaken for one.
 */
final class UrlFinder
{
    private const HOST = '(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[[0-9a-f:.]+\]';

    private const REST = '(?::\d{1,5})?(?:[/?#][^\s"\'<>\\\\`{}|^]*)?';

    /**
     * @return list<string>
     */
    public function find(string $text): array
    {
        if ($text === '' || ! str_contains($text, '/')) {
            return [];
        }

        $text = str_replace('\\/', '/', $text);

        $patterns = [
            '~https?://(?:'.self::HOST.')'.self::REST.'~i',
            '~(?<=["\'(=])//(?:(?:[a-z0-9-]+\.)+[a-z0-9-]+|localhost|\[[0-9a-f:.]+\])'.self::REST.'~i',
        ];

        $found = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches) > 0) {
                foreach ($matches[0] as $match) {
                    $url = rtrim(html_entity_decode($match, ENT_QUOTES | ENT_HTML5), '.,;:!?)]\'"');

                    if (HostClassifier::hostOf($url) !== null) {
                        $found[$url] = true;
                    }
                }
            }
        }

        return array_keys($found);
    }
}
