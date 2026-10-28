<?php

declare(strict_types=1);

namespace WebxUi\Users\Account;

use Closure;
use WebxUi\Admin\Visitors\Visitor;

/**
 * A section of the visitor's account (spec §11.2): a line of the menu and the pages behind it.
 * The profile and «Sign-in and security» are the module's own; a shop's module adds «My orders»
 * or the wallet in its `boot()`, and neither the module of visitors nor the theme lists them.
 */
final readonly class AccountSection
{
    /**
     * @param  string  $id  `orders` — the class of the item (`webx-account__item--orders`) and what `webx-users.account.hidden` names
     * @param  string  $label  a dictionary key: `webx-orders::account.menu`
     * @param  string  $route  a route name declared in `AccountRoutes::group()`: `webx.orders.account.index`
     * @param  string  $icon  an icon of the theme's set
     * @param  int  $position  profile — 10, security — 90: the shop's sections fall between
     * @param  bool  $verified  needs a confirmed e-mail (§8.4): its routes go in `AccountRoutes::group(…, verified: true)`, the item is `is-locked` for an unconfirmed visitor
     * @param  string|null  $active  a pattern of route names that lights the item: `webx.orders.account.*`; the route itself when null
     * @param  (Closure(Visitor): ?string)|null  $badge  a counter by the item: «2» active orders
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $route,
        public string $icon = 'circle',
        public int $position = 100,
        public bool $verified = false,
        public ?string $active = null,
        public ?Closure $badge = null,
    ) {}
}
