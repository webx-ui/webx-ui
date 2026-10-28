<?php

declare(strict_types=1);

namespace WebxUi\Users\Doctor;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;

/**
 * Whether the letters the visitors are promised can leave (spec §8.6): the registration says «we
 * sent you a link», the reset of a password is nothing but a letter. A transport that writes
 * letters into the log or an array sends none of them — a warning, except on a local machine,
 * where the log is the inbox of whoever runs it.
 */
final readonly class MailCheck implements Check
{
    private const string SUBJECT = 'Visitors';

    public function __construct(
        private Config $config,
        private Application $app,
    ) {}

    public function run(): array
    {
        $mailer = (string) $this->config->get('mail.default', 'log');
        $transport = (string) $this->config->get("mail.mailers.{$mailer}.transport", $mailer);

        if (! in_array($transport, ['log', 'array'], true)) {
            return [Diagnosis::ok(self::SUBJECT, "letters to visitors leave by {$transport}.")];
        }

        if ($this->app->isLocal()) {
            return [Diagnosis::ok(self::SUBJECT, "letters to visitors go to the {$transport} — the inbox of this local machine.")];
        }

        return [Diagnosis::warn(
            self::SUBJECT,
            "letters to visitors go to the {$transport} transport and reach nobody: the confirmation of an address "
            .'and the link of a forgotten password are promised and never arrive. Set MAIL_MAILER to a real server.',
        )];
    }
}
