<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Console;

use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use WebxUi\Mcp\Registry\ToolRegistry;

/**
 * A token for an agent, in an administrator's name.
 *
 * The panel has no screen for this yet, and it should not be the first thing the panel grows:
 * a token is a key to everything its scopes name, and handing one out is a decision for the
 * person who can already run artisan on the server. The token is a Sanctum personal access
 * token of the administrator, the scopes are its abilities, and it is shown exactly once.
 */
final class IssueTokenCommand extends Command
{
    protected $signature = 'webx:mcp:token
        {email : The administrator the agent acts as}
        {--name=agent : What the token is called in the administrator\'s list}
        {--scopes= : Comma-separated scopes; every scope on offer when omitted}
        {--days= : Days until the token expires; never when omitted}';

    protected $description = 'Issue an MCP token to an administrator, with the scopes as its abilities';

    public function handle(AuthFactory $auth, Config $config, ToolRegistry $registry, Router $router, UrlGenerator $url, DatabaseManager $db): int
    {
        $schema = $db->connection()->getSchemaBuilder();
        $email = (string) $this->argument('email');
        $guard = $auth->guard((string) $config->get('webx-auth.guard', 'cms'));

        if (! method_exists($guard, 'getProvider')) {
            $this->components->error('The panel guard has no user provider to look the administrator up in.');

            return self::FAILURE;
        }

        $user = $guard->getProvider()->retrieveByCredentials(['email' => $email]);

        if ($user === null) {
            $this->components->error("No administrator has the email [{$email}].");

            return self::FAILURE;
        }

        if (! method_exists($user, 'createToken') || ! method_exists($user, 'tokens')) {
            $this->components->error('The administrator model does not issue tokens: it needs Sanctum\'s HasApiTokens.');

            return self::FAILURE;
        }

        // Sanctum only publishes its migration; a site that never ran it has no table to keep
        // the token in, and the plain error from the database says less than this.
        $table = $user->tokens()->getRelated()->getTable();

        if (! $schema->hasTable($table)) {
            $this->components->error(
                "There is no [{$table}] table. Publish Sanctum's migration and run it first: "
                .'php artisan vendor:publish --tag=sanctum-migrations && php artisan migrate'
            );

            return self::FAILURE;
        }

        $offered = $registry->scopes();
        $scopes = $this->scopes($offered);

        if ($scopes === null) {
            return self::FAILURE;
        }

        $token = $user->createToken((string) $this->option('name'), $scopes, $this->expiresAt());
        $plain = (string) $token->plainTextToken;

        $this->components->info("Token issued to {$email} with scopes: ".implode(', ', $scopes).'.');
        $this->newLine();
        $this->line($plain);
        $this->newLine();

        if ($router->has('webx.mcp')) {
            $endpoint = $url->route('webx.mcp');

            $this->components->twoColumnDetail('Endpoint', $endpoint);
            $this->line("  claude mcp add --transport http webx {$endpoint} --header \"Authorization: Bearer {$plain}\"");
            $this->newLine();
        }

        $this->components->warn('Shown once. Anyone holding it acts as this administrator within these scopes.');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $offered
     * @return list<string>|null
     */
    private function scopes(array $offered): ?array
    {
        $option = $this->option('scopes');

        if (! is_string($option) || trim($option) === '') {
            if ($offered === []) {
                $this->components->error('No installed module offers MCP tools, so there is no scope to grant.');

                return null;
            }

            return $offered;
        }

        $scopes = array_values(array_unique(array_filter(array_map('trim', explode(',', $option)))));
        $unknown = array_diff($scopes, $offered);

        if ($unknown !== []) {
            $this->components->error('Unknown scope(s): '.implode(', ', $unknown).'. On offer: '.implode(', ', $offered).'.');

            return null;
        }

        return $scopes;
    }

    private function expiresAt(): ?DateTimeInterface
    {
        $days = $this->option('days');

        return is_string($days) && ctype_digit($days) && (int) $days > 0
            ? Carbon::now()->addDays((int) $days)
            : null;
    }
}
