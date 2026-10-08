<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * Capitals, underscores, characters outside ASCII, spaces, doubled slashes or a repeated part in
 * the address — one finding for each.
 */
final class UrlFormat extends PageCheck
{
    protected const ID = 'url.format';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $path = rawurldecode((string) parse_url($page->url, PHP_URL_PATH));

        if (preg_match('/[A-Z]/', $path) === 1) {
            yield $this->on($page, 'url-uppercase', key: 'uppercase');
        }

        if (str_contains($path, '_')) {
            yield $this->on($page, 'url-underscore', key: 'underscore');
        }

        if (preg_match('/[^\x00-\x7F]/', $path) === 1) {
            yield $this->on($page, 'url-non-ascii', key: 'non-ascii');
        }

        if (str_contains($path.'?'.rawurldecode((string) parse_url($page->url, PHP_URL_QUERY)), ' ')) {
            yield $this->on($page, 'url-space', key: 'space');
        }

        if (str_contains($path, '//')) {
            yield $this->on($page, 'url-double-slash', key: 'double-slash');
        }

        $repeated = self::repeated($path);

        if ($repeated !== null) {
            yield $this->on($page, 'url-repeated', ['value' => $repeated], key: 'repeated');
        }
    }

    /**
     * A part of the path said twice in a row — `/catalog/catalog/`, `/a/b/a/b` — which is what a
     * relative link resolved against the wrong page makes. Null when there is none.
     */
    private static function repeated(string $path): ?string
    {
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
        $count = count($segments);

        for ($length = 1; $length * 2 <= $count; $length++) {
            for ($at = 0; $at + 2 * $length <= $count; $at++) {
                $first = array_slice($segments, $at, $length);

                // `/2026/10/10/` is a date, not a mistake.
                if ($first === array_slice($segments, $at + $length, $length) && ! ctype_digit(implode('', $first))) {
                    return '/'.implode('/', $first).'/';
                }
            }
        }

        return null;
    }
}
