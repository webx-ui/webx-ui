<?php

declare(strict_types=1);

namespace WebxUi\Admin\Visitors;

/**
 * A site without visitors: everybody is a guest and there is nowhere to sign in.
 *
 * `webx.visitor` answers 404 under it — the feature behind it does not exist on this site — and
 * a shop works as it does for a guest.
 */
final class NullVisitors implements Visitors
{
    public function current(): ?Visitor
    {
        return null;
    }

    public function find(int $id): ?Visitor
    {
        return null;
    }

    public function findByEmail(string $email): ?Visitor
    {
        return null;
    }

    public function signInUrl(?string $back = null): ?string
    {
        return null;
    }
}
