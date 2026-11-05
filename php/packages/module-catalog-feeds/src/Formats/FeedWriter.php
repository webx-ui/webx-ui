<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Formats;

use RuntimeException;

/**
 * Where a build's text goes. It counts the products, because a build is many jobs and the one
 * writing the next product is not the one that wrote the last: what stands between two products is
 * decided by `written() > 0`, not by a flag kept in a class.
 */
final class FeedWriter
{
    /**
     * @param  resource  $stream
     */
    public function __construct(private $stream, private int $written = 0) {}

    public function write(string $text): void
    {
        if (fwrite($this->stream, $text) === false) {
            throw new RuntimeException('The file of a feed cannot be written.');
        }
    }

    /** How many products the file holds so far; in `open()` and `close()`, all of them. */
    public function written(): int
    {
        return $this->written;
    }

    /** One product more, for the build to say once it is written. */
    public function counted(): void
    {
        $this->written++;
    }
}
