<?php

declare(strict_types=1);

namespace WebxUi\Users\Account;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use InvalidArgumentException;
use WebxUi\Admin\Visitors\Visitor;

/**
 * The sections of the account (spec §11.2): the module's own two and whatever the modules of the
 * site registered. A section whose route is not there — its module removed, the routes cached
 * without it — is left out rather than a page that fails on a missing route.
 */
final class AccountMenu
{
    public const string PROFILE = 'profile';

    public const string SECURITY = 'security';

    /** @var array<string, AccountSection> */
    private array $sections = [];

    public function __construct(private readonly Router $router) {}

    /** A module, in `boot()`. The same id twice is a mistake of two modules, not a replacement. */
    public function register(AccountSection $section): void
    {
        if (isset($this->sections[$section->id])) {
            throw new InvalidArgumentException("The section of the account \"{$section->id}\" is registered already.");
        }

        $this->sections[$section->id] = $section;
    }

    /**
     * The sections in position order, without those the site hid (`webx-users.account.hidden`).
     * Nothing here asks the database: a badge is the menu's to ask for, once it is shown.
     *
     * @return list<AccountSection>
     */
    public function for(Visitor $visitor): array
    {
        return $this->all();
    }

    /**
     * Every section a visitor may meet, in order — what `users://catalog` lists. The menu of one
     * visitor is the same today; `for()` is where a section for some visitors only would go.
     *
     * @return list<AccountSection>
     */
    public function all(): array
    {
        $hidden = array_map('strval', (array) config('webx-users.account.hidden', []));
        $sections = array_values(array_filter(
            $this->sections,
            fn (AccountSection $section): bool => ! in_array($section->id, $hidden, true) && $this->router->has($section->route),
        ));

        usort($sections, static fn (AccountSection $a, AccountSection $b): int => [$a->position, $a->id] <=> [$b->position, $b->id]);

        return $sections;
    }

    /** Whether the page of the request is this section's — the copy behind a language prefix too. */
    public function active(AccountSection $section, Request $request): bool
    {
        $pattern = $section->active ?? $section->route;

        return $request->routeIs($pattern, $pattern.'.localised');
    }

    public function get(string $id): ?AccountSection
    {
        return $this->sections[$id] ?? null;
    }
}
