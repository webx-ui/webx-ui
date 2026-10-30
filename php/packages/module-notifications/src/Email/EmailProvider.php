<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Email;

use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Message as MailMessage;
use Illuminate\Support\HtmlString;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;
use WebxUi\Admin\Mail\MailRoute;
use WebxUi\Admin\Mail\MailRoutes;
use WebxUi\Admin\Support\Field;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Notifications\Contracts\HasMailRoute;
use WebxUi\Notifications\Contracts\Provider;
use WebxUi\Notifications\Contracts\WritesHtml;
use WebxUi\Notifications\Refusal;
use WebxUi\Notifications\Sending\CheckResult;
use WebxUi\Notifications\Sending\Credentials;
use WebxUi\Notifications\Sending\DeliveryFailed;
use WebxUi\Notifications\Sending\Message;
use WebxUi\Notifications\Sending\Recipient;

/**
 * Letters (§6.2): through the site's own mail by default, or through an SMTP server of the
 * channel's, built on the fly with `MailManager::build()` — `config/mail.php` is never touched.
 *
 * The body is text (§17, question 4): the letter goes as `text/plain` as written and as
 * `text/html` with the whole text escaped, line breaks kept and addresses made links, inside the
 * package's frame. A template that starts with a tag is HTML instead ({@see EmailMarkup}): its
 * tags are checked on save, the values escaped into it, and the plain part read out of it. One
 * letter to each recipient, so nobody sees the others' addresses.
 *
 * Recipients are the ones Inbox has always had — an administrator by id (the address follows the
 * account) or an address — plus an addressee of the event (decision 12).
 */
final class EmailProvider implements HasMailRoute, Provider, WritesHtml
{
    public const KEY = 'email';

    public function __construct(
        private readonly MailManager $mail,
        private readonly Views $views,
        private readonly MailRoutes $routes,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'webx-notifications::providers.email.label';
    }

    public function icon(): string
    {
        return 'mail';
    }

    public function fields(): array
    {
        return [
            new Field('mode', 'webx-notifications::providers.email.mode', kind: Field::CHOICE, choices: ['site', 'smtp'], default: 'site'),
            new Field('host', 'webx-notifications::providers.email.host'),
            new Field('port', 'webx-notifications::providers.email.port', kind: Field::NUMBER, default: 587),
            new Field('encryption', 'webx-notifications::providers.email.encryption', kind: Field::CHOICE, choices: ['tls', 'ssl', 'none'], default: 'tls'),
            new Field('username', 'webx-notifications::providers.email.username'),
            new Field('password', 'webx-notifications::providers.email.password', secret: true),
            new Field('from_address', 'webx-notifications::providers.email.from-address', kind: Field::EMAIL),
            new Field('from_name', 'webx-notifications::providers.email.from-name'),
        ];
    }

    /** The site's mailer as the application sees it now, or the channel's own server. */
    public function route(array $options): MailRoute
    {
        if (($options['mode'] ?? 'site') !== 'smtp') {
            return $this->routes->site();
        }

        return $this->routes->smtp((string) ($options['host'] ?? ''), is_scalar($options['port'] ?? null) ? (string) $options['port'] : null);
    }

    public function hasSubject(): bool
    {
        return true;
    }

    public function address(): string
    {
        return 'email';
    }

    public function limit(): ?int
    {
        return null;
    }

    public function recipients(array $recipients): array
    {
        $list = [];

        foreach ($recipients as $recipient) {
            if (is_string($recipient)) {
                $recipient = ['email' => $recipient];
            }

            if (! is_array($recipient)) {
                throw new Refusal($this->say('recipients-shape'));
            }

            if (isset($recipient['variable'])) {
                $list['variable:'.$recipient['variable']] = ['variable' => $recipient['variable']];

                continue;
            }

            if (isset($recipient['admin_id'])) {
                if (! is_numeric($recipient['admin_id']) || (int) $recipient['admin_id'] < 1) {
                    throw new Refusal($this->say('recipients-shape'));
                }

                $list['admin:'.(int) $recipient['admin_id']] = ['admin_id' => (int) $recipient['admin_id']];

                continue;
            }

            $email = is_string($recipient['email'] ?? null) ? trim($recipient['email']) : '';

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new Refusal($this->say('email.address', ['address' => $email]));
            }

            $list['email:'.mb_strtolower($email)] ??= ['email' => $email];
        }

        return array_values($list);
    }

    public function resolve(array $recipients, array $variables): array
    {
        $ids = array_values(array_filter(array_map(static fn (array $one): int => (int) ($one['admin_id'] ?? 0), $recipients)));
        $admins = $ids === [] ? collect() : CmsUser::query()->whereIn('id', $ids)->get()->keyBy(static fn (CmsUser $admin): int => (int) $admin->getKey());
        $resolved = [];

        foreach ($recipients as $recipient) {
            if (isset($recipient['variable'])) {
                $name = (string) $recipient['variable'];
                $value = $variables[$name] ?? '';
                $value = is_string($value) ? trim($value) : '';

                $resolved[] = filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                    ? new Recipient($value, '{{'.$name.'}}')
                    : Recipient::skipped('{{'.$name.'}}', 'no_address');

                continue;
            }

            if (isset($recipient['admin_id'])) {
                $admin = $admins->get((int) $recipient['admin_id']);

                $resolved[] = match (true) {
                    $admin === null => Recipient::skipped('#'.$recipient['admin_id'], 'admin_deleted'),
                    ! $admin->is_active => Recipient::skipped((string) $admin->email, 'admin_inactive', $admin->name),
                    default => new Recipient((string) $admin->email, $admin->name),
                };

                continue;
            }

            $resolved[] = new Recipient((string) $recipient['email']);
        }

        return $resolved;
    }

    public function missing(array $options, array $credentialsSet): ?string
    {
        if (($options['mode'] ?? 'site') !== 'smtp') {
            return null;
        }

        return ($options['host'] ?? '') === '' || ($options['port'] ?? '') === '' ? 'missing-smtp' : null;
    }

    public function isHtml(string $template): bool
    {
        return EmailMarkup::isHtml($template);
    }

    public function checkTemplate(string $template): array
    {
        if (! $this->isHtml($template)) {
            return [];
        }

        $words = [];

        foreach (EmailMarkup::problems($template) as [$problem, $what]) {
            $words[] = $this->say('email.markup-'.$problem, [
                'tag' => $what,
                'attribute' => $what,
                'allowed' => implode(', ', $problem === 'attribute' ? EmailMarkup::ATTRIBUTES : EmailMarkup::TAGS),
            ]);
        }

        return array_values(array_unique($words));
    }

    /** The body is text; it is escaped whole when the letter's HTML is built. */
    public function escape(string $value): string
    {
        return $value;
    }

    public function plain(string $body): string
    {
        return $body;
    }

    public function send(Message $message, Credentials $credentials, array $options, Recipient $to): void
    {
        try {
            $this->mailer($credentials, $options)->send(
                [
                    'html' => new HtmlString($message->html ? $this->frame($message->body) : $this->html($message->body)),
                    'text' => new HtmlString($message->html ? $message->plain : $message->body),
                ],
                [],
                function (MailMessage $mail) use ($message, $options, $to): void {
                    // The label of an address taken from the event is the variable, for the log of deliveries;
                    // in the header it would greet the buyer as «{{customer_email}}».
                    $mail->to($to->key, $to->label !== null && ! str_starts_with($to->label, '{{') ? $to->label : null);
                    $mail->subject((string) $message->subject);

                    $from = $options['from_address'] ?? null;

                    if (is_string($from) && $from !== '') {
                        $name = $options['from_name'] ?? null;
                        $mail->from($from, is_string($name) && $name !== '' ? $name : null);
                    }

                    if ($message->replyTo !== null) {
                        $mail->replyTo($message->replyTo);
                    }
                },
            );
        } catch (Throwable $exception) {
            throw new DeliveryFailed($this->say('email.refused', ['error' => $credentials->mask($exception->getMessage())]));
        }
    }

    public function check(Credentials $credentials, array $options): CheckResult
    {
        if (($options['mode'] ?? 'site') !== 'smtp') {
            $route = $this->routes->site();

            return $route->isTrap() && $route->host === null
                ? new CheckResult(false, $this->say('email.site-nowhere', ['transport' => $route->transport]))
                : new CheckResult(true, $this->say('email.site-ok'));
        }

        try {
            $transport = $this->mailer($credentials, $options)->getSymfonyTransport();

            if ($transport instanceof SmtpTransport) {
                $transport->start();
                $transport->stop();
            }
        } catch (Throwable $exception) {
            return new CheckResult(false, $this->say('email.refused', ['error' => $credentials->mask($exception->getMessage())]));
        }

        return new CheckResult(true, $this->say('email.smtp-ok', ['host' => (string) ($options['host'] ?? '')]));
    }

    public function screen(): array
    {
        $visible = ['when' => 'provider', 'is' => self::KEY];
        $smtp = ['all' => [$visible, ['when' => 'mode', 'is' => 'smtp']]];
        $field = static fn (string $name, string $label, array $extra = []): array => [
            'id' => $name,
            'type' => 'wx-input',
            'name' => $name,
            'label' => 'trans::webx-notifications::providers.email.'.$label,
            ...$extra,
        ];

        $nodes = [
            [
                'id' => 'mode',
                'type' => 'wx-segmented',
                'name' => 'mode',
                'label' => 'trans::webx-notifications::providers.email.mode',
                'help' => 'trans::webx-notifications::screen.mode-help',
                'props' => ['options' => [
                    ['value' => 'site', 'label' => 'trans::webx-notifications::providers.email.mode-site'],
                    ['value' => 'smtp', 'label' => 'trans::webx-notifications::providers.email.mode-smtp'],
                ]],
            ],
            $field('host', 'host', ['visible' => $smtp]),
            $field('port', 'port', ['visible' => $smtp, 'props' => ['type' => 'number']]),
            [
                'id' => 'encryption',
                'type' => 'wx-select',
                'name' => 'encryption',
                'label' => 'trans::webx-notifications::providers.email.encryption',
                'visible' => $smtp,
                'props' => ['options' => [
                    ['value' => 'tls', 'label' => 'TLS'],
                    ['value' => 'ssl', 'label' => 'SSL'],
                    ['value' => 'none', 'label' => 'trans::webx-notifications::providers.email.no-encryption'],
                ]],
            ],
            $field('username', 'username', ['visible' => $smtp, 'props' => ['autocomplete' => 'off']]),
            $field('password', 'password', ['visible' => $smtp, 'props' => ['type' => 'password', 'autocomplete' => 'off']]),
            $field('from_address', 'from-address', ['help' => 'trans::webx-notifications::screen.from-help']),
            $field('from_name', 'from-name'),
            [
                'id' => 'email_recipients',
                'type' => 'wx-notifications-emails',
                'name' => 'recipients',
                'label' => 'trans::webx-notifications::screen.emails',
                'help' => 'trans::webx-notifications::screen.emails-help',
            ],
        ];

        // Each node shown only on a channel of this kind; one with a condition of its own keeps it.
        return array_map(static fn (array $node): array => $node + ['visible' => $visible], $nodes);
    }

    /**
     * The site's mailer, or one built for the channel's SMTP server.
     *
     * @param  array<string, mixed>  $options
     */
    private function mailer(Credentials $credentials, array $options): Mailer
    {
        if (($options['mode'] ?? 'site') !== 'smtp') {
            /** @var Mailer */
            return $this->mail->mailer();
        }

        $encryption = (string) ($options['encryption'] ?? 'tls');

        /** @var Mailer */
        return $this->mail->build([
            'transport' => 'smtp',
            'host' => (string) ($options['host'] ?? ''),
            'port' => (int) ($options['port'] ?? 587),
            'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'auto_tls' => $encryption !== 'none',
            'username' => ($options['username'] ?? '') !== '' ? (string) $options['username'] : null,
            'password' => $credentials->get('password'),
            'timeout' => 10,
        ]);
    }

    /** The letter's HTML: the text escaped whole, line breaks and links kept, in the frame. */
    private function html(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $linked = (string) preg_replace('~https?://[^\s<>"]+~u', '<a href="$0">$0</a>', $escaped);

        return $this->frame(nl2br($linked, false));
    }

    /** The package's frame around a body that is HTML already. */
    private function frame(string $body): string
    {
        return $this->views->file(dirname(__DIR__, 2).'/resources/views/mail/frame.blade.php', ['body' => new HtmlString($body)])->render();
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function say(string $key, array $replace = []): string
    {
        $line = __('webx-notifications::errors.'.$key, $replace);

        return is_string($line) ? $line : $key;
    }
}
