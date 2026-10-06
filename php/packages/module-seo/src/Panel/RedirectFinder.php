<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use WebxUi\Routing\UrlNormaliser;

/**
 * The redirect that catches an address, and where it sends the browser.
 *
 * Shared by the middleware that answers the request and by `test_url` in the panel and over MCP,
 * so that what the panel says about an address is what a visitor gets — loops stepped over and
 * `$1` already put back.
 */
final class RedirectFinder
{
    public function __construct(
        private readonly SeoRules $rules,
        private readonly UrlMatcher $matcher,
    ) {}

    /**
     * The first redirect that covers the address and does not send it back to itself, with the
     * target it resolves to here.
     *
     * The loop guard skips rather than refuses: a mask redirect is a loop only for some of the
     * addresses it covers, and the rest of them are still worth serving.
     *
     * @return array{array<string, mixed>, string}|null
     */
    public function find(string $url): ?array
    {
        $url = UrlNormaliser::normalise($url);
        $rows = $this->rules->redirects();

        while ($rows !== []) {
            $row = $this->matcher->match($url, $rows);

            if ($row === null) {
                return null;
            }

            $target = UrlMatcher::target(
                is_string($row['match_type'] ?? null) ? $row['match_type'] : '',
                is_string($row['pattern'] ?? null) ? $row['pattern'] : '',
                is_string($row['target'] ?? null) ? $row['target'] : '',
                $url,
            );

            if ($target !== '' && UrlNormaliser::normalise($target) !== $url) {
                return [$row, $target];
            }

            // Drop the one that would have looped and keep looking behind it.
            $rows = array_values(array_filter(
                $rows,
                static fn (array $candidate): bool => ($candidate['id'] ?? null) !== ($row['id'] ?? null),
            ));
        }

        return null;
    }
}
