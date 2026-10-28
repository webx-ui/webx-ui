<?php

declare(strict_types=1);

namespace WebxUi\Users\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use WebxUi\Users\Models\User;

/**
 * A letter of the module to a visitor (spec §8.6): in the visitor's language, through the queue
 * — the page that asked for it answers at once and as fast whether a letter left or not (§7, §15)
 * — and with a view of its own, `webx-users::mail.<name>`, that a theme or the site overrides.
 *
 * Sent without `module-notifications`: restoring a password cannot hang on a module a site may
 * not have.
 */
abstract class Letter extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public User $user)
    {
        $this->locale($user->locale ?? app()->getLocale());
    }

    /** `verify`, `reset`, … — the view and the words: `webx-users::mail.<name>.*`. */
    abstract protected function letter(): string;

    /** @return array<string, mixed> */
    protected function data(): array
    {
        return [];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) trans('webx-users::mail.'.$this->letter().'.subject', ['site' => self::site()]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'webx-users::mail.'.$this->letter(),
            with: [
                'name' => $this->user->name,
                'site' => self::site(),
                ...$this->data(),
            ],
        );
    }

    private static function site(): string
    {
        $name = config('app.name');

        return is_string($name) ? $name : '';
    }
}
