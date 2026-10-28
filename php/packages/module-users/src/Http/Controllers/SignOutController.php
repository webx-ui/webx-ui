<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use WebxUi\Admin\Visitors\Events\VisitorSignedOut;
use WebxUi\Users\SignIn;
use WebxUi\Users\Urls;
use WebxUi\Widgets\Forms\FormResponse;

/**
 * `POST /account/sign-out`: the visitor leaves, an administrator signed in on the same browser
 * stays in the panel (spec §1). A POST behind CSRF, so a link on another site cannot sign anybody
 * out.
 */
final readonly class SignOutController
{
    public function __invoke(Request $request, SignIn $signIn, Dispatcher $events, Urls $urls): FormResponse
    {
        $id = $signIn->out($request);

        if ($id !== null) {
            $events->dispatch(new VisitorSignedOut($id));
        }

        return FormResponse::redirect($urls->home());
    }
}
