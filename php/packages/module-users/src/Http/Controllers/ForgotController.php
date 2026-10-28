<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers;

use Illuminate\Contracts\Mail\Factory as Mail;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use WebxUi\Users\Mail\ResetPassword;
use WebxUi\Users\Models\User;
use WebxUi\Users\Passwords\ResetLinks;
use WebxUi\Users\Throttle;
use WebxUi\Users\Urls;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;

/**
 * `GET|POST /account/forgot` (spec §7 item 1): one field, one answer.
 *
 * «If there is an account with this address, we sent a link» — for an address that is there,
 * one that is not, a blocked account and a request over the limit alike. The letter goes
 * through the queue, so the answer takes as long whether one was written or not.
 */
final readonly class ForgotController
{
    public const array RULES = ['email' => ['required', 'string', 'email', 'max:191']];

    public function __construct(private Urls $urls) {}

    public function show(Request $request, Views $views): View
    {
        return self::page($views, $this->urls);
    }

    public function store(Request $request, Throttle $throttle, ResetLinks $links, Mail $mail): FormResponse
    {
        $data = $request->validate(self::RULES, [], self::attributes());
        $email = User::normaliseEmail((string) $data['email']);

        if ($throttle->forgot($email, (string) $request->ip())) {
            $user = User::query()->byEmail($email)->first();

            // A blocked account is not let in by a link either (§6.3): no letter, the same answer.
            if ($user instanceof User && ! $user->isBlocked()) {
                $link = $this->urls->to('reset', parameters: ['token' => $links->issue($user)]);
                $mail->mailer()->to($user->email)->send(new ResetPassword($user, $link, ResetLinks::minutes()));
            }
        }

        return FormResponse::message(trans('webx-users::auth.forgot.sent'));
    }

    /** The form, also under «The link is out of date» on the page of a dead link (§7 item 3). */
    public static function page(Views $views, Urls $urls, bool $expired = false): View
    {
        return $views->make('webx-users::forgot', [
            'action' => $urls->to('forgot'),
            'rules' => FormRules::for(self::RULES, self::attributes()),
            'expired' => $expired,
            'signIn' => $urls->to('sign-in'),
        ]);
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return ['email' => trans('webx-users::auth.fields.email')];
    }
}
