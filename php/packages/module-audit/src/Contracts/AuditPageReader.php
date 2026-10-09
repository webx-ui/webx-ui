<?php

declare(strict_types=1);

namespace WebxUi\Audit\Contracts;

use Dom\HTMLDocument;
use WebxUi\Audit\Crawl\PageReaders;

/**
 * What another module reads off a crawled page while the parser has it (§12: the page's HTML is
 * not kept, so a check that needs more than the audit's own facts must take it now or never).
 *
 * Registered into {@see PageReaders} from the module's provider. What `read()` returns lands in
 * the page's facts under `id()` — `$page->fact('widgets')` — and the module's own checks read it
 * back at the analysis stage like any other fact. An empty array stores nothing.
 */
interface AuditPageReader
{
    /** `widgets` — the key of its facts, one per module. */
    public function id(): string;

    /**
     * Small and JSON-safe: counts, a few addresses, excerpts cut short — not the page again.
     *
     * @param  string  $url  The page's address, to resolve relative ones against.
     * @return array<string, mixed>
     */
    public function read(HTMLDocument $document, string $url): array;
}
