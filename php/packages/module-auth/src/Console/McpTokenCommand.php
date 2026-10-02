<?php

declare(strict_types=1);

namespace WebxUi\Auth\Console;

use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use LogicException;
use RuntimeException;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Scopes;

/**
 * A key to the MCP server for a program, issued without anybody pressing Allow.
 *
 * A personal access token of an administrator rather than a client-credentials token, because
 * every tool acts as `$request->user()`: its permissions, the history it writes and the call
 * log all name a person, and a token with no person behind it would be refused by the first
 * permission check — or, worse, would have to be let past all of them.
 *
 * Without scopes the token carries `mcp:use`, the one an agent connected over OAuth gets: it
 * reaches whatever the administrator may do, including the tools of a module installed after
 * the token was issued. Named scopes are read one by one, and a module added later is out of
 * reach until a new token names it.
 *
 * Every call issues a new token and leaves the earlier ones working; `--revoke-existing`
 * switches off the administrator's live tokens of the same name first, which is how a script
 * that runs again replaces its key rather than collecting them.
 *
 * Only the token goes to standard output — or one line of JSON with `--json` — so that a
 * script can take it as it is. Anything that went wrong goes to standard error.
 */
final class McpTokenCommand extends Command
{
    protected $signature = 'webx:mcp:token
                            {--name= : What the token is called — the name --revoke-existing looks for}
                            {--scopes= : Comma-separated scopes, e.g. pages:write,media:read; empty or "all" — everything the administrator may do}
                            {--admin= : Sign-in address of the administrator the token acts as; the first super administrator when left out}
                            {--json : Print {"token","type","scopes","expires_at"} instead of the bare token}
                            {--revoke-existing : Revoke this administrator\'s live tokens of the same name first}';

    protected $description = 'Issue a personal access token for the MCP server';

    public function handle(ToolRegistry $tools, ClientRepository $clients): int
    {
        $name = trim((string) $this->option('name'));

        if ($name === '') {
            return $this->refuse('Name the token with --name: it is what the list of connections shows, and what --revoke-existing looks for.');
        }

        $scopes = $this->scopes($tools);

        if (is_string($scopes)) {
            return $this->refuse($scopes);
        }

        $trouble = $this->missingSetup();

        if ($trouble !== null) {
            return $this->refuse($trouble);
        }

        $admin = $this->admin();

        if (is_string($admin)) {
            return $this->refuse($admin);
        }

        try {
            $provider = $admin->getProviderName();
        } catch (LogicException) {
            return $this->refuse('No Passport guard serves the panel\'s administrators, so a token would let nobody in. Check `webx-mcp.guard` and the `auth.guards` it names.');
        }

        $this->ensurePersonalAccessClient($clients, $provider);

        if ($this->option('revoke-existing')) {
            $this->revoke($admin, $name);
        }

        // Passport refuses a scope it has not been told about. Told here rather than for the
        // whole application: offered at large, a module scope would also be one an OAuth
        // client could ask for, and those are given `mcp:use` and nothing else.
        Passport::tokensCan(array_merge(
            Passport::scopes()->pluck('description', 'id')->all(),
            array_combine($scopes, $scopes),
        ));

        $result = $admin->createToken($name, $scopes);
        $expires = $result->getToken()?->getAttribute('expires_at');

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'token' => $result->accessToken,
                'type' => 'Bearer',
                'scopes' => $scopes,
                'expires_at' => $expires instanceof DateTimeInterface ? $expires->format(DATE_ATOM) : null,
            ], JSON_UNESCAPED_SLASHES));
        } else {
            $this->line($result->accessToken);
        }

        return self::SUCCESS;
    }

    /**
     * The scopes asked for, checked against what the installed modules declare — or the
     * reason they cannot be issued.
     *
     * @return list<string>|string
     */
    private function scopes(ToolRegistry $tools): array|string
    {
        $asked = array_values(array_unique(array_filter(
            array_map('trim', explode(',', (string) $this->option('scopes'))),
            static fn (string $scope): bool => $scope !== '',
        )));

        if ($asked === [] || $asked === ['all']) {
            return [Scopes::OAUTH];
        }

        $known = [Scopes::OAUTH, ...$tools->scopes()];
        $unknown = array_values(array_diff($asked, $known));

        if ($unknown !== []) {
            return 'No installed module declares '.implode(', ', $unknown).'. Known scopes: '.implode(', ', $known).'.';
        }

        return $asked;
    }

    /**
     * What a site has to have before Passport can sign anything, said in the words of the
     * command that fixes it. Passport's own failures name a table or a file and not the cure.
     */
    private function missingSetup(): ?string
    {
        foreach ([Passport::client()->getTable(), Passport::token()->getTable()] as $table) {
            if (! Schema::hasTable($table)) {
                return "There is no [{$table}] table yet. Run `php artisan migrate` — `webx:setup` publishes Passport's migrations.";
            }
        }

        $configured = config('passport.private_key');

        if ((! is_string($configured) || $configured === '') && ! is_file(Passport::keyPath('oauth-private.key'))) {
            return 'Passport has no keys to sign a token with. Run `php artisan passport:keys`.';
        }

        return null;
    }

    private function admin(): CmsUser|string
    {
        $email = trim((string) $this->option('admin'));

        if ($email !== '') {
            $admin = CmsUser::query()->where('email', $email)->first();

            if ($admin === null) {
                return "There is no administrator [{$email}].";
            }

            // The door would turn the token away anyway; better to say so than hand out a key
            // that answers 403.
            if (! $admin->is_active) {
                return "The administrator [{$email}] is switched off.";
            }

            return $admin;
        }

        return CmsUser::query()
            ->where('is_super', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->first()
            ?? 'There is no active super administrator to issue the token for. Name one with --admin, or create one with `php artisan webx:admin --super`.';
    }

    /**
     * Passport issues personal tokens through a client of their own, one per user provider,
     * which `passport:client --personal` would create — one more step for a script to know
     * about, and a question it would stop to ask.
     */
    private function ensurePersonalAccessClient(ClientRepository $clients, string $provider): void
    {
        try {
            $clients->personalAccessClient($provider);
        } catch (RuntimeException) {
            $clients->createPersonalAccessGrantClient('WebX personal access', $provider);
        }
    }

    private function revoke(CmsUser $admin, string $name): void
    {
        // Ids first: an update over the relation would carry its `whereHas` into the statement.
        $ids = $admin->tokens()
            ->where('name', $name)
            ->where('revoked', false)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            Passport::token()->newQuery()->whereIn('id', $ids)->update(['revoked' => true]);
        }
    }

    /**
     * To standard error, so that a script reading the token from standard output gets either
     * a token or nothing.
     */
    private function refuse(string $message): int
    {
        $this->getOutput()->getErrorStyle()->writeln("<error>{$message}</error>");

        return self::FAILURE;
    }
}
