<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Laravel\Passport\Passport;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Setup\Catalogue;

/**
 * What an agent connecting over OAuth needs, and what nothing else notices is missing.
 *
 * Passport is a deployment step rather than an installation one: `passport:keys` writes files
 * into storage, and storage is the one directory a deploy does not carry over. Without the keys
 * the guard cannot be built at all, so a call with no token answers 500 where it should answer
 * 401 — an error that names encryption and not the missing file. The tokens table is the same
 * shape of trouble one layer down: Sanctum 4 only *publishes* its migration, so a site can have
 * `HasApiTokens` on the model and no table under it (CLAUDE.md §4).
 *
 * Nothing here applies to a site without `webx-ui/mcp`, and that is most of them.
 */
final class PassportKeys implements Check
{
    public function __construct(
        private readonly Repository $config,
        private readonly Filesystem $files,
        private readonly DatabaseManager $db,
        private readonly Catalogue $catalogue,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        if (! $this->catalogue->has('webx-ui/mcp') || ! class_exists(Passport::class)) {
            return [];
        }

        if ($this->config->get('webx-mcp.oauth.enabled') === false) {
            return [Diagnosis::ok('Agent access', 'the stdio server only — OAuth is switched off in config/webx-mcp.php.')];
        }

        $found = [];

        foreach (['oauth-private.key', 'oauth-public.key'] as $key) {
            if (! $this->files->exists(Passport::keyPath($key))) {
                $found[] = Diagnosis::fail(
                    'Agent access',
                    "there is no {$key} — run `php artisan passport:keys`. Until then a request without a token answers 500 rather than 401.",
                );
            }
        }

        try {
            $tables = $this->db->connection()->getSchemaBuilder();

            foreach (['oauth_access_tokens', 'oauth_clients'] as $table) {
                if (! $tables->hasTable($table)) {
                    $found[] = Diagnosis::fail(
                        'Agent access',
                        "the {$table} table is not there — run `php artisan vendor:publish --tag=passport-migrations && php artisan migrate`.",
                    );
                }
            }
        } catch (Throwable $failure) {
            $found[] = Diagnosis::warn('Agent access', 'the tables could not be read: '.$failure->getMessage());
        }

        return $found === []
            ? [Diagnosis::ok('Agent access', 'Passport has its keys and its tables — an agent can connect.')]
            : $found;
    }
}
