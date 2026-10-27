<?php

declare(strict_types=1);

namespace WebxUi\Debug;

/**
 * Which half of the package is on (§3.3 of the debug bar spec).
 *
 * Outside production, both: the bar on the pages and `debug_page`. In production, nothing — unless
 * the site says `WEBX_DEBUG_MCP_IN_PRODUCTION=true`, and then only the agent's path: the tool, the
 * one-time token it sends, the report it reads back. No cookie, no late start, no bar, no API of
 * reports. A report made there keeps no value of any statement: the agent needs the shapes, the
 * counts and the times, and the values on a live site are its visitors'.
 */
final readonly class Mode
{
    public function __construct(public bool $agentOnly = false) {}

    /** Whether values are kept out of a report whole, rather than by the lists of `mask`. */
    public function masksEverything(): bool
    {
        return $this->agentOnly;
    }
}
