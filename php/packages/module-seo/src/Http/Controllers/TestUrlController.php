<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Seo\Panel\AddressReport;

/**
 * "Why does this page have the wrong title?"
 *
 * The most common question this module gets, and it should take one call to answer rather than
 * a reading of the code: which rule matched, what every source contributed, what the page ends
 * up saying, and whether the address is being redirected before any of that happens. The answer
 * itself is {@see AddressReport}, the same one the MCP tool gives.
 */
final class TestUrlController
{
    public function __construct(
        private readonly AddressReport $report,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'locale' => ['nullable', 'string', 'max:16'],
        ]);

        return ApiResponse::data($this->report->for(
            (string) $validated['url'],
            isset($validated['locale']) ? (string) $validated['locale'] : null,
        ));
    }
}
