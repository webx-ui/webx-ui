<?php

declare(strict_types=1);

namespace WebxUi\Users\Account;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Mail\Factory as Mail;
use Illuminate\Database\ConnectionInterface;
use WebxUi\Admin\Visitors\Events\VisitorDeleting;
use WebxUi\Users\Mail\AccountDeleted;
use WebxUi\Users\Models\User;
use WebxUi\Users\Passwords\ResetLinks;

/**
 * An account gone for good (spec §14.1) — by the visitor in «Sign-in and security», later by an
 * administrator in the card and `users_delete`.
 *
 * One transaction: `VisitorDeleting` first, so the listeners unlink or anonymise what is theirs
 * while the row is still there (the orders keep their snapshot of the contacts, the cart goes);
 * a listener that throws keeps the account whole. Then the row, deleted — no soft deletion: an
 * account deleted at the person's request does not come back — with the linked networks and the
 * reset links. Every session of the visitor ends with it: the guard finds nobody by the id it
 * keeps. Last, «Your account was deleted» to the address it had.
 */
final readonly class DeleteAccount
{
    public function __construct(
        private ConnectionInterface $db,
        private Dispatcher $events,
        private Mail $mail,
    ) {}

    public function __invoke(User $user): void
    {
        $id = (int) $user->getKey();
        $letter = new AccountDeleted($user->name, $user->email, $user->locale);

        $this->db->transaction(function () use ($user, $id): void {
            $this->events->dispatch(new VisitorDeleting($id));

            // The foreign keys cascade on a database that keeps them; these do it everywhere.
            $this->db->table('users_identities')->where('user_id', $id)->delete();
            $this->db->table(ResetLinks::TABLE)->where('user_id', $id)->delete();
            $user->delete();
        });

        $this->mail->mailer()->to($letter->address)->send($letter);
    }
}
