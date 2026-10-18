<?php

declare(strict_types=1);

namespace WebxUi\Admin\Contracts;

/**
 * A section of the panel that declares no permissions of its own and is opened by somebody
 * else's: the catalogue's dictionaries by the catalogue's, the header and the footer by
 * `blocks.regions` (WEBX_UI_PERMISSIONS.md §11, fork 3).
 *
 * The menu shows a section to whoever holds any one of these. Without the contract a section is
 * shown by its own `permissions()`, and a section with neither is open to everybody signed in —
 * the page that tells a person how to connect their own agent, say.
 *
 * A contract of its own rather than a method of {@see Module}, for the reason
 * {@see HasNavSection} gives: a project may implement `Module` directly.
 */
interface HasVisibility
{
    /**
     * Any one of these shows the section.
     *
     * @return list<string>
     */
    public function visibleWith(): array;
}
