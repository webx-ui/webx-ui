<?php

declare(strict_types=1);

namespace WebxUi\Auth\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use WebxUi\Auth\Models\CmsUser;

final class CreateAdminCommand extends Command
{
    protected $signature = 'webx:admin
                            {--name= : Display name}
                            {--email= : Sign-in address}
                            {--super : Grant every permission}';

    protected $description = 'Create an administrator for the panel';

    /**
     * Read by the command when there is nobody to ask. Named here rather than passed as an
     * option on purpose — see below.
     */
    public const PASSWORD_VARIABLE = 'WEBX_ADMIN_PASSWORD';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?? $this->ask('Name'));
        $email = (string) ($this->option('email') ?? $this->ask('Email'));

        $password = $this->password();

        if ($password === null) {
            $this->components->error(
                'No password and nobody to ask for one. Run this interactively, or set '
                .self::PASSWORD_VARIABLE.' — provisioning scripts and containers need a way in too.'
            );

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:cms_users,email'],
                'password' => ['required', 'string', 'min:12'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $isSuper = (bool) $this->option('super') || CmsUser::query()->count() === 0;

        $user = CmsUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_super' => $isSuper,
        ]);

        $this->components->info("Administrator [{$user->email}] created.");

        if ($isSuper) {
            // Worth saying rather than leaving to be discovered: the first account has to be
            // able to grant permissions, so it holds all of them.
            $this->components->warn('This account is a super administrator and passes every permission check.');
        }

        return self::SUCCESS;
    }

    /**
     * There is deliberately no `--password` option. An argument lands in the shell history and
     * is visible in the process list for as long as the command runs; an environment variable
     * does neither, and is how a provisioning script or a container entrypoint would pass one.
     */
    private function password(): ?string
    {
        // Not env(): that goes through the configuration repository, which a cached config
        // leaves empty. A provisioning script exports a real variable, and .env puts one in
        // $_SERVER while the config is not cached — both are read here, neither by way of the
        // cache.
        $fromEnvironment = $_SERVER[self::PASSWORD_VARIABLE]
            ?? $_ENV[self::PASSWORD_VARIABLE]
            ?? getenv(self::PASSWORD_VARIABLE);

        if (is_string($fromEnvironment) && $fromEnvironment !== '') {
            return $fromEnvironment;
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        $asked = $this->secret('Password');

        return is_string($asked) && $asked !== '' ? $asked : null;
    }
}
