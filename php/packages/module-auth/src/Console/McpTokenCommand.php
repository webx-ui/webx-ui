<?php

declare(strict_types=1);

namespace WebxUi\Auth\Console;

use DateTimeInterface;
use Illuminate\Console\Command;
use WebxUi\Auth\Keys\KeyIssuer;
use WebxUi\Auth\Keys\KeyRefused;
use WebxUi\Auth\Models\CmsUser;

/**
 * A key to the MCP server for a program, issued without anybody pressing Allow.
 *
 * The same key the panel issues under Administrators → Keys ({@see KeyIssuer}): it is listed
 * there, and revoked there.
 *
 * Without scopes the key carries `mcp:use`, the scope an agent connected over OAuth gets: it
 * reaches whatever the administrator may do, including the tools of a module installed after
 * the key was issued. Named scopes are read one by one, and a module added later is out of
 * reach until a new key names it. Either way a key is a program, never a person: what a module
 * keeps for a person — a stage reviewed by hand, an answer to a question — it does not get.
 *
 * Every call issues a new key and leaves the earlier ones working; `--revoke-existing`
 * switches off the administrator's live keys of the same name first, which is how a script
 * that runs again replaces its key rather than collecting them.
 *
 * Only the key goes to standard output — or one line of JSON with `--json` — so that a
 * script can take it as it is. Anything that went wrong goes to standard error.
 */
final class McpTokenCommand extends Command
{
    protected $signature = 'webx:mcp:token
                            {--name= : What the key is called — the name the list of keys shows and --revoke-existing looks for}
                            {--scopes= : Comma-separated scopes, e.g. pages:write,media:read; empty or "all" — everything the administrator may do}
                            {--admin= : Sign-in address of the administrator the key acts as; the first super administrator when left out}
                            {--days=365 : How many days the key lives, from 1 to 3650}
                            {--json : Print {"token","type","scopes","expires_at"} instead of the bare key}
                            {--revoke-existing : Revoke this administrator\'s live keys of the same name first}';

    protected $description = 'Issue a key for a program to the MCP server';

    public function handle(KeyIssuer $issuer): int
    {
        $name = trim((string) $this->option('name'));

        if ($name === '') {
            return $this->refuse('Name the key with --name: it is what the list of keys shows, and what --revoke-existing looks for.');
        }

        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);

        if (! is_int($days) || $days < 1 || $days > KeyIssuer::MAX_DAYS) {
            return $this->refuse('--days takes a whole number of days from 1 to '.KeyIssuer::MAX_DAYS.'.');
        }

        try {
            $scopes = $issuer->scopes(explode(',', (string) $this->option('scopes')));

            $trouble = $issuer->missingSetup();

            if ($trouble !== null) {
                return $this->refuse($trouble);
            }

            $admin = $this->admin();

            if (is_string($admin)) {
                return $this->refuse($admin);
            }

            if ($this->option('revoke-existing')) {
                $issuer->revokeNamed($admin, $name);
            }

            $issued = $issuer->issue($admin, $name, $scopes, $days);
        } catch (KeyRefused $refused) {
            return $this->refuse($refused->getMessage());
        }

        $expires = $issued->key->expires_at;

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'token' => $issued->token,
                'type' => 'Bearer',
                'scopes' => $scopes,
                'expires_at' => $expires instanceof DateTimeInterface ? $expires->format(DATE_ATOM) : null,
            ], JSON_UNESCAPED_SLASHES));
        } else {
            $this->line($issued->token);
        }

        return self::SUCCESS;
    }

    private function admin(): CmsUser|string
    {
        $email = trim((string) $this->option('admin'));

        if ($email !== '') {
            $admin = CmsUser::query()->where('email', $email)->first();

            if ($admin === null) {
                return "There is no administrator [{$email}].";
            }

            return $admin;
        }

        return CmsUser::query()
            ->where('is_super', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->first()
            ?? 'There is no active super administrator to issue the key for. Name one with --admin, or create one with `php artisan webx:admin --super`.';
    }

    /**
     * To standard error, so that a script reading the key from standard output gets either
     * a key or nothing.
     */
    private function refuse(string $message): int
    {
        $this->getOutput()->getErrorStyle()->writeln("<error>{$message}</error>");

        return self::FAILURE;
    }
}
