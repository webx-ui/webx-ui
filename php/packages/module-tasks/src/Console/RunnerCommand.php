<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Console;

use DateTimeInterface;
use Illuminate\Console\Command;
use WebxUi\Auth\Keys\KeyIssuer;
use WebxUi\Auth\Keys\KeyRefused;
use WebxUi\Tasks\Runner\RunnerAccount;

/**
 * The watcher's account and its key (§14.1): the role `runner`, the administrator «taskd», and a
 * fresh key — the one issued before is revoked, so running it again replaces the key rather than
 * collecting them.
 *
 * Only the key goes to standard output — or one line of JSON with `--json` — so that a script can
 * take it as it is; anything that went wrong goes to standard error.
 */
final class RunnerCommand extends Command
{
    protected $signature = 'webx:tasks:runner
                            {--days=365 : How many days the key lives, from 1 to 3650}
                            {--json : Print {"token","type","scopes","expires_at"} instead of the bare key}';

    protected $description = 'Make the task watcher\'s role and account, and issue its key';

    public function handle(RunnerAccount $account, KeyIssuer $issuer): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);

        if (! is_int($days) || $days < 1 || $days > KeyIssuer::MAX_DAYS) {
            return $this->refuse('--days takes a whole number of days from 1 to '.KeyIssuer::MAX_DAYS.'.');
        }

        $trouble = $issuer->missingSetup();

        if ($trouble !== null) {
            return $this->refuse($trouble);
        }

        try {
            $issued = $account->issue($days);
        } catch (KeyRefused $refused) {
            return $this->refuse($refused->getMessage());
        }

        if ($this->option('json')) {
            $expires = $issued->key->expires_at;

            $this->line((string) json_encode([
                'token' => $issued->token,
                'type' => 'Bearer',
                'scopes' => $issued->key->scopes,
                'expires_at' => $expires instanceof DateTimeInterface ? $expires->format(DATE_ATOM) : null,
            ], JSON_UNESCAPED_SLASHES));
        } else {
            $this->line($issued->token);
        }

        return self::SUCCESS;
    }

    private function refuse(string $message): int
    {
        $this->getOutput()->getErrorStyle()->writeln("<error>{$message}</error>");

        return self::FAILURE;
    }
}
