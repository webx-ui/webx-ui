<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Throwable;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Profile\EmailChange;

/**
 * Moving your own sign-in address, in two halves (WEBX_UI_PERMISSIONS.md §8).
 *
 * The first is asked from the profile, with the current password: it sends a letter to the new
 * address and changes nothing. The second is the link in that letter, followed from whatever
 * mailbox received it — signed in or not, because owning the mailbox is the proof — and it is
 * what makes the move. The model writes the change into the history like any other edit of the
 * account.
 */
final class EmailChangeController
{
    /** What the first half takes, and nothing else — the same rule as the profile. */
    private const FIELDS = ['email', 'current_password', '_method', '_token'];

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly EmailChange $change,
    ) {}

    public function store(Request $request): JsonResponse
    {
        /** @var CmsUser $user */
        $user = $this->auth->guard((string) config('webx-auth.guard'))->user();

        $validator = validator($request->all(), [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique($user->getTable(), 'email')->ignore($user->getKey()),
            ],
            'current_password' => ['required', 'string', 'current_password:'.(string) config('webx-auth.guard')],
        ], [
            'current_password.current_password' => (string) trans('webx-auth::profile.wrong-password'),
        ]);

        $validator->after(function (Validator $validator) use ($request, $user): void {
            foreach (array_diff(array_keys($request->all()), self::FIELDS) as $field) {
                $validator->errors()->add((string) $field, (string) trans('webx-auth::profile.not-yours', ['field' => $field]));
            }

            if (! $this->change->available()) {
                $validator->errors()->add('email', (string) trans('webx-auth::profile.email-unavailable'));
            }

            if (mb_strtolower((string) $request->input('email')) === mb_strtolower($user->email)) {
                $validator->errors()->add('email', (string) trans('webx-auth::profile.email-same'));
            }
        });

        $email = (string) $validator->validate()['email'];

        try {
            $this->change->send($user, $email);
        } catch (Throwable $failed) {
            report($failed);

            throw ValidationException::withMessages(['email' => (string) trans('webx-auth::profile.email-not-sent')]);
        }

        return new JsonResponse(['data' => ['email' => $email, 'minutes' => EmailChange::MINUTES]], 202);
    }

    /**
     * The link in the letter. Always lands in the panel, with a word for it to say: a link that
     * worked, one that ran out or was already used, one whose address somebody else took since.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $user = CmsUser::query()->find($request->query('admin'));
        $email = (string) $request->query('email');

        if (
            ! $request->hasValidSignature()
            || ! $user instanceof CmsUser
            || ! $user->is_active
            || ! hash_equals(EmailChange::fingerprint($user), (string) $request->query('from'))
        ) {
            return $this->back('expired');
        }

        if (CmsUser::query()->where('email', $email)->whereKeyNot($user->getKey())->exists()) {
            return $this->back('taken');
        }

        $user->email = $email;
        $user->save();

        return $this->back('done');
    }

    private function back(string $outcome): RedirectResponse
    {
        return new RedirectResponse(url('/'.trim((string) config('webx-admin.path', 'cms'), '/')).'/?email-change='.$outcome);
    }
}
