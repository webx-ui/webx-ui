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

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?? $this->ask('Name'));
        $email = (string) ($this->option('email') ?? $this->ask('Email'));

        // Never as an option: a password on the command line lands in the shell history and in
        // the process list.
        $password = (string) $this->secret('Password');

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
}
