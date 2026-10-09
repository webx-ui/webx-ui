<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

use Dom\HTMLDocument;
use Throwable;
use WebxUi\Audit\Contracts\AuditPageReader;

/**
 * Every module's reader of crawled pages, by id. A singleton like the checks: a module adds its
 * reader from its provider when the audit is installed, and the parser asks each of them once
 * per page.
 */
final class PageReaders
{
    /** @var array<string, AuditPageReader> */
    private array $readers = [];

    public function register(AuditPageReader $reader): void
    {
        $this->readers[$reader->id()] = $reader;
    }

    /** @return array<string, AuditPageReader> */
    public function all(): array
    {
        return $this->readers;
    }

    /**
     * Each reader's facts of one page, under its id. A reader that throws is reported and left
     * out: a module's bug must not stop the crawl of the whole site.
     *
     * @return array<string, array<string, mixed>>
     */
    public function read(HTMLDocument $document, string $url): array
    {
        $facts = [];

        foreach ($this->readers as $id => $reader) {
            try {
                $read = $reader->read($document, $url);
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            if ($read !== []) {
                $facts[$id] = $read;
            }
        }

        return $facts;
    }
}
