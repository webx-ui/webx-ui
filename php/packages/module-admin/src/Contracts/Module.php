<?php

declare(strict_types=1);

namespace WebxUi\Admin\Contracts;

/**
 * One section of the admin panel.
 *
 * A module describes itself; it does not route, render or query. Its own service provider
 * registers routes and bindings the way any Laravel package would, and this contract only
 * supplies what the front end needs to know a module exists — the manifest.
 */
interface Module
{
    /** Stable machine name, used in URLs and permissions: `pages`, `module-media`. */
    public function id(): string;

    /** What a person sees in the navigation. */
    public function title(): string;

    /** Icon name understood by the front end, or null to let it choose. */
    public function icon(): ?string;

    /** Navigation order; equal values fall back to the id. */
    public function order(): int;

    /**
     * Permissions the module defines, as `<id>.<action>`.
     *
     * @return list<string>
     */
    public function permissions(): array;

    /**
     * Anything else the front end needs to build the section: routes it owns, the shape of
     * its list screen, feature flags. Kept apart from the fields above so a module can never
     * shadow them.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array;
}
