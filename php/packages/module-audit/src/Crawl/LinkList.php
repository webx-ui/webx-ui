<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

/**
 * The addresses of one page as the parser finds them: resolved against the page, once per kind,
 * address and `rel` (a logo linked from the header and the footer is one link to the home page,
 * not two), and no more than a page can sensibly have.
 */
final class LinkList
{
    /** @var array<string, FoundLink> */
    private array $links = [];

    public function __construct(
        private readonly string $base,
        private readonly int $limit,
    ) {}

    public function resolve(string $value): ?string
    {
        return Urls::resolve($this->base, $value);
    }

    public function add(string $value, string $kind, ?string $anchor = null, ?string $rel = null, ?string $target = null): void
    {
        if (count($this->links) >= $this->limit) {
            return;
        }

        $url = $this->resolve($value);

        // The `rel` is part of the key: the same page linked once plainly and once with nofollow
        // is two different things to say about it.
        $key = $kind."\n".$url."\n".$rel;

        if ($url === null || isset($this->links[$key])) {
            return;
        }

        $this->links[$key] = new FoundLink($url, $kind, $anchor, $rel, $target, Urls::absolute($value));
    }

    /** @return list<FoundLink> */
    public function all(): array
    {
        return array_values($this->links);
    }
}
