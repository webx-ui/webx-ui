<?php

declare(strict_types=1);

namespace WebxUi\Debug\Mcp;

/**
 * How `debug_page` asks the site for a page: a real request, as a visitor's browser would make
 * it (§13 of the debug bar spec). Bound to {@see HttpPageFetcher}; a test binds one that answers
 * from inside the application.
 */
interface PageFetcher
{
    /**
     * @param  array<string, string>  $headers
     * @return array{status: int|null, ms: int, error: string|null}
     */
    public function fetch(string $url, array $headers): array;
}
