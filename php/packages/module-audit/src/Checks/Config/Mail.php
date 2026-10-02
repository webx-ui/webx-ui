<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Config;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** Mail to `log` or `array`: every form "sends", and nobody ever receives a letter. */
final class Mail extends Check
{
    protected const ID = 'config.mail';

    protected const GROUP = 'config';

    protected const SEVERITY = Severity::ERROR;

    protected const NEEDS = ['config'];

    public function run(AuditContext $context): iterable
    {
        $mailer = (string) $context->config('mail.default', 'log');
        $transport = (string) $context->config("mail.mailers.{$mailer}.transport", $mailer);

        if ($context->production() && in_array($transport, ['log', 'array'], true)) {
            yield $this->found('mail-nowhere', ['mailer' => $mailer, 'transport' => $transport]);
        }
    }
}
