<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Taxonomy;

use RuntimeException;

/**
 * The file of a list, whichever way it came (§6.2 of the feeds spec): a comment with the version,
 * then `1 - Animals & Pet Supplies`, a line a category. A line that says anything else stops the
 * reading: it is the wrong file, and half a list would quietly unmap categories.
 */
final readonly class TaxonomyFile
{
    private const LINE = '/^(\d{1,32}) - (.+)$/u';

    private const VERSION = '/Version:\s*(\S.*)$/i';

    private const BOM = "\xEF\xBB\xBF";

    /** The width of the column a path goes to. */
    private const MAX_PATH = 500;

    /**
     * @param  array<string, string>  $items  external id => path
     */
    public function __construct(public ?string $version, public array $items) {}

    /**
     * @throws TaxonomyFailure
     */
    public static function read(string $path): self
    {
        $file = fopen($path, 'rb');

        if ($file === false) {
            throw new RuntimeException('The file of a list is no longer on the disk.');
        }

        $version = null;
        $items = [];
        $number = 0;

        try {
            while (($line = fgets($file)) !== false) {
                $number++;
                $line = trim($number === 1 && str_starts_with($line, self::BOM) ? substr($line, strlen(self::BOM)) : $line);

                if ($line === '') {
                    continue;
                }

                if (! mb_check_encoding($line, 'UTF-8')) {
                    throw TaxonomyFailure::of('taxonomy-encoding', ['line' => $number]);
                }

                if (str_starts_with($line, '#')) {
                    $version ??= preg_match(self::VERSION, $line, $found) === 1 ? mb_substr($found[1], 0, 64) : null;

                    continue;
                }

                if (preg_match(self::LINE, $line, $found) !== 1 || mb_strlen($found[2]) > self::MAX_PATH) {
                    throw TaxonomyFailure::of('taxonomy-line', ['line' => $number]);
                }

                $items[$found[1]] = $found[2];
            }
        } finally {
            fclose($file);
        }

        if ($items === []) {
            throw TaxonomyFailure::of('taxonomy-empty');
        }

        return new self($version, $items);
    }
}
