<?php

declare(strict_types=1);

namespace WebxUi\Users\Emails;

use Illuminate\Contracts\Mail\Factory as Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use WebxUi\Users\Mail\EmailChangeLink;
use WebxUi\Users\Models\User;
use WebxUi\Users\Urls;

/**
 * Moving the sign-in to a new address (spec §8.5, decision 16), after the administrators' own
 * (`WebxUi\Auth\Profile\EmailChange`).
 *
 * Nothing is stored while the letter is on its way: the visitor still signs in with the address
 * they had. The link is signed, lives `webx-users.email_minutes`, carries the new address and a
 * fingerprint of the old one — once the address moved, by this link or any other way, every
 * other link in flight is dead. So a typo in the new address locks nobody out, and a session
 * left open on somebody else's machine cannot hand the account to a stranger's mailbox.
 */
final readonly class EmailChange
{
    /** The session key of the address a letter went to — the profile says it waits (§8.5). */
    public const string PENDING = 'webx-users.email-pending';

    public function __construct(
        private Urls $urls,
        private Mail $mail,
    ) {}

    public function send(User $user, string $email): void
    {
        $minutes = self::minutes();
        $link = $this->urls->signed('email.confirm', [
            'id' => $user->getKey(),
            'email' => $email,
            'from' => self::fingerprint($user),
        ], Date::now()->addMinutes($minutes));

        $this->mail->mailer()->to($email)->send(new EmailChangeLink($user, $link, $email, $minutes));
    }

    /**
     * The visitor and the address a link of this request moves them to, or null: forged, expired,
     * the address moved since, or taken by another account meanwhile.
     *
     * @return array{User, string}|null
     */
    public function resolve(Request $request, string $id): ?array
    {
        $email = $request->query('email');
        $from = $request->query('from');

        if (! $request->hasValidSignature() || ! ctype_digit($id) || ! is_string($email) || ! is_string($from)) {
            return null;
        }

        $user = User::query()->find((int) $id);
        $email = User::normaliseEmail($email);

        if (! $user instanceof User || $user->isBlocked() || ! hash_equals(self::fingerprint($user), $from)) {
            return null;
        }

        return User::query()->byEmail($email)->exists() ? null : [$user, $email];
    }

    /** The address the link moves from, as a short hash: a link has no business spelling it out. */
    public static function fingerprint(User $user): string
    {
        return substr(hash('sha256', User::normaliseEmail($user->email)), 0, 16);
    }

    public static function minutes(): int
    {
        $minutes = config('webx-users.email_minutes');

        return is_numeric($minutes) ? max(1, (int) $minutes) : 60;
    }
}
