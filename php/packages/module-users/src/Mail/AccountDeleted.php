<?php

declare(strict_types=1);

namespace WebxUi\Users\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * «Your account was deleted» (spec §14.1), to the address the account had. Not a `Letter`: that one
 * carries the visitor to the queue, and by the time the queue sends this there is no visitor —
 * the name and the address travel as text.
 */
final class AccountDeleted extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $address,
        ?string $locale,
    ) {
        $this->locale($locale ?? app()->getLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) trans('webx-users::mail.'.self::letter().'.subject', ['site' => self::site()]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'webx-users::mail.'.self::letter(),
            with: ['name' => $this->name, 'site' => self::site()],
        );
    }

    /** The view and the words, as a `Letter` names them: `webx-users::mail.account-deleted`. */
    private static function letter(): string
    {
        return 'account-deleted';
    }

    private static function site(): string
    {
        $name = config('app.name');

        return is_string($name) ? $name : '';
    }
}
