<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Formats;

/**
 * A file a template cannot describe (§10 of the feeds spec): nesting that depends on the data, an
 * order of its own, a binary format. The way out, not the main road: a template can be seen and
 * changed without a release.
 *
 * The selection, the third layer, the variables, the writing, the swap of the file, the journal and
 * the delivery are a build's own, as for a template.
 *
 * **A build is many jobs, so an instance remembers nothing between calls.** What a file needs to
 * know about itself comes from {@see FeedWriter::written()} and {@see FeedContext}.
 *
 * **`open()` comes after the products**, for the reason a head does: it speaks about what they
 * found. The order of the file stays open, products, close.
 */
interface FeedFormat
{
    /** What `driver` of a feed holds: `a-z`, digits, `-`, `_`, up to 32 characters. */
    public function key(): string;

    /** What a person calls it. */
    public function label(): string;

    /** Of the file and of its address, without the dot: `a-z` and digits. */
    public function extension(): string;

    /** The whole header, charset included where the file has one. */
    public function contentType(): string;

    /**
     * The names to compute for each product. A name the site does not have fails the build.
     *
     * @return list<string>
     */
    public function variables(): array;

    public function open(FeedWriter $out, FeedContext $context): void;

    /**
     * @param  array<string, mixed>  $values  of one product, by the names of {@see variables()}
     */
    public function item(FeedWriter $out, array $values): void;

    public function close(FeedWriter $out, FeedContext $context): void;

    /**
     * What is wrong with the finished file, null for nothing (§7.2). It goes through the whole file
     * in one job, so a slow reader has to read it as a stream.
     */
    public function validate(string $path): ?string;
}
