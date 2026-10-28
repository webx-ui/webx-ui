<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use Illuminate\Contracts\Container\Container;
use WebxUi\Admin\Visitors\Visitors;

/**
 * Whose cart the request opens: the signed-in visitor's id, or null for a guest.
 *
 * Asked through the seam of the panel (`Visitors`), so the cart neither requires the panel nor
 * a visitors module: without them every visitor is a guest and the cookie alone opens the cart.
 */
final class CartOwner
{
    public function __construct(private readonly Container $app) {}

    public function id(): ?int
    {
        if (! interface_exists(Visitors::class) || ! $this->app->bound(Visitors::class)) {
            return null;
        }

        $visitors = $this->app->make(Visitors::class);

        return $visitors instanceof Visitors ? $visitors->current()?->id : null;
    }
}
