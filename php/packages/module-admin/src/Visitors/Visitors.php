<?php

declare(strict_types=1);

namespace WebxUi\Admin\Visitors;

/**
 * Who is signed in on the site — the visitor, never an administrator of the panel.
 *
 * Lives here rather than in a visitors module so that a shop, favourites or anything else can ask
 * and still work on a site without one: the default binding is {@see NullVisitors}, which knows
 * nobody, and a visitors module (`webx-ui/module-users`) binds its own.
 */
interface Visitors
{
    /** The signed-in visitor of this request, or null for a guest. */
    public function current(): ?Visitor;

    public function find(int $id): ?Visitor;

    /** For a gift to another customer: an exact, normalised address; never a search. */
    public function findByEmail(string $email): ?Visitor;

    /** Where to send a guest who must sign in first; null — nowhere (no visitors module). */
    public function signInUrl(?string $back = null): ?string;
}
