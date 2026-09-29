<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests\Fixtures;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Routing\MissHandler;

/** A module that remembers one address it used to have. */
final class FormerAddresses implements MissHandler
{
    public function miss(Request $request, string $locale, string $path): ?Response
    {
        return $path === 'old-about' ? new RedirectResponse('/about', 301) : null;
    }
}
