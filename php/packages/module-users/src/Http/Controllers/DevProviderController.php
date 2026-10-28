<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers;

use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use WebxUi\Users\Social\DevProvider;
use WebxUi\Users\Social\ProviderKeys;

/**
 * The «provider's» page of the test provider (DevProvider): only where it is registered and
 * switched on, like every route of a provider that is not shown (§9.3).
 */
final readonly class DevProviderController
{
    public function __invoke(Request $request, ProviderKeys $keys, Views $views): View
    {
        $provider = $keys->find(DevProvider::KEY) ?? abort(404);

        return $views->make('webx-users::social.dev', [
            'action' => $provider->callbackUrl(),
            'state' => (string) $request->query('state', ''),
        ]);
    }
}
