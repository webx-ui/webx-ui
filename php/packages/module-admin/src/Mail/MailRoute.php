<?php

declare(strict_types=1);

namespace WebxUi\Admin\Mail;

/**
 * Which way a letter leaves: the mailer, its transport, the server it talks to — and whether
 * that way is a trap that takes letters and delivers them to nobody ({@see MailRoutes}).
 */
final readonly class MailRoute
{
    /**
     * @param  string  $mailer  The mailer by name — the site's (`smtp`, `log`), or `channel` for a channel's own server.
     * @param  string  $transport  What the mailer is: `smtp`, `log`, `array`, `ses`, `sendmail`…
     * @param  string|null  $trap  What makes it a trap — the transport or the host — or null for a real way out.
     */
    public function __construct(
        public string $mailer,
        public string $transport,
        public ?string $host = null,
        public ?int $port = null,
        public ?string $trap = null,
    ) {}

    public function isTrap(): bool
    {
        return $this->trap !== null;
    }

    /** `smtp — mail.example.com:587`, or the transport alone when there is no server to name. */
    public function label(): string
    {
        if ($this->host === null || $this->host === '') {
            return $this->transport;
        }

        return $this->transport.' — '.$this->host.($this->port === null ? '' : ':'.$this->port);
    }

    /**
     * @return array{mailer: string, transport: string, host: string|null, port: int|null, label: string, trap: bool}
     */
    public function toArray(): array
    {
        return [
            'mailer' => $this->mailer,
            'transport' => $this->transport,
            'host' => $this->host,
            'port' => $this->port,
            'label' => $this->label(),
            'trap' => $this->isTrap(),
        ];
    }
}
