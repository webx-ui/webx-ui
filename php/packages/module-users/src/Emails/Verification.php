<?php

declare(strict_types=1);

namespace WebxUi\Users\Emails;

use Illuminate\Contracts\Mail\Factory as Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use WebxUi\Users\Mail\VerifyEmail;
use WebxUi\Users\Models\User;
use WebxUi\Users\Urls;

/**
 * Confirming the address (spec §8.1): a signed link that lives `webx-users.verify_hours`, with a
 * hash of the address it confirms — a later address makes every link of the earlier one dead.
 * The link confirms without signing in: it is opened on whatever device the mail is read on.
 */
final readonly class Verification
{
    public function __construct(
        private Urls $urls,
        private Mail $mail,
    ) {}

    /** The letter, unless the address is confirmed already. */
    public function send(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $hours = self::hours();
        $link = $this->urls->signed('verify', ['id' => $user->getKey(), 'hash' => self::hash($user)], Date::now()->addHours($hours));

        $this->mail->mailer()->to($user->email)->send(new VerifyEmail($user, $link, $hours));
    }

    /** The visitor a link of this request confirms, or null: forged, expired, of an older address. */
    public function visitor(Request $request, string $id, string $hash): ?User
    {
        if (! $request->hasValidSignature() || ! ctype_digit($id)) {
            return null;
        }

        $user = User::query()->find((int) $id);

        return $user instanceof User && hash_equals(self::hash($user), $hash) ? $user : null;
    }

    public static function hash(User $user): string
    {
        return sha1($user->email);
    }

    public static function hours(): int
    {
        $hours = config('webx-users.verify_hours');

        return is_numeric($hours) ? max(1, (int) $hours) : 72;
    }
}
