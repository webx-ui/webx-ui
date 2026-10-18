<?php

declare(strict_types=1);

namespace WebxUi\Auth\Profile;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\UrlGenerator as Urls;
use WebxUi\Auth\Mail\EmailChangeLink;
use WebxUi\Auth\Models\CmsUser;

/**
 * Moving your own sign-in address: a letter to the new one, and a link in it that makes the move
 * (WEBX_UI_PERMISSIONS.md §8, decision 8 (a)).
 *
 * Nothing is stored while the letter is on its way. The link is signed, short-lived, and carries
 * a fingerprint of the address it moves *from*: once the move is made — by this link or any other
 * way — the fingerprint no longer matches and every other letter in flight is dead. The address
 * changes only when somebody who reads the new mailbox follows it, so a session left open on
 * somebody else's machine cannot hand the account to a stranger's mailbox.
 *
 * Offered only where a letter can actually leave the site. A transport that writes letters into
 * a log or an array delivers nothing, and a form that says "check your inbox" over it is a lie —
 * there the profile says the address is changed by whoever manages administrators. A local
 * machine is the exception: there the log *is* the inbox of whoever runs it.
 */
final class EmailChange
{
    /** How long the link in the letter works, in minutes. */
    public const MINUTES = 60;

    public function __construct(
        private readonly Config $config,
        private readonly Application $app,
        private readonly MailFactory $mail,
        private readonly UrlGenerator $urls,
    ) {}

    /**
     * Whether a letter can reach the new address at all. `webx-auth.email_change` settles it when
     * set; otherwise the mail transport does.
     */
    public function available(): bool
    {
        $forced = $this->config->get('webx-auth.email_change');

        if (is_bool($forced)) {
            return $forced;
        }

        if ($this->app->isLocal()) {
            return true;
        }

        $mailer = (string) $this->config->get('mail.default', 'log');
        $transport = (string) $this->config->get("mail.mailers.{$mailer}.transport", $mailer);

        return ! in_array($transport, ['log', 'array'], true);
    }

    /** Send the letter. Throws whatever the transport throws: a letter that did not leave is said. */
    public function send(CmsUser $user, string $email): void
    {
        $this->mail->mailer()->to($email)->send(new EmailChangeLink($user, $this->link($user, $email)));
    }

    /** The address in the letter. */
    public function link(CmsUser $user, string $email): string
    {
        /** @var Urls $urls */
        $urls = $this->urls;

        return $urls->temporarySignedRoute('webx.auth.me.email.confirm', now()->addMinutes(self::MINUTES), [
            'admin' => $user->getKey(),
            'email' => $email,
            'from' => self::fingerprint($user),
        ]);
    }

    /**
     * The address the link moves from, as a short hash: the link may sit in a mailbox for years,
     * and it has no business spelling out the old address.
     */
    public static function fingerprint(CmsUser $user): string
    {
        return substr(hash('sha256', mb_strtolower($user->email)), 0, 16);
    }
}
