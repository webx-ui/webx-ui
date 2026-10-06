<?php

declare(strict_types=1);

namespace WebxUi\Routing\Contracts;

/**
 * How an address is written — the one place that decides it (§8.2).
 *
 * The resolver redirects any other spelling of a path to this one before it looks anything up.
 * Whoever also normalises addresses ahead of it — `module-seo`'s middleware, with its trailing
 * slash and case settings — binds this contract to the same policy, so that its single 301 lands
 * on what the resolver accepts: two policies with two opinions are a chain of redirects, or a
 * loop.
 *
 * Paths in and out are decoded and start with `/`; the language prefix is part of the path.
 */
interface Spelling
{
    public function of(string $path): string;
}
