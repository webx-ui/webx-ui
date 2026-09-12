<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Console\CreateAdminCommand;
use WebxUi\Auth\Models\CmsUser;

final class CreateAdminCommandTest extends TestCase
{
    #[Test]
    public function the_first_administrator_is_a_super_administrator(): void
    {
        $this->artisan('webx:admin', ['--name' => 'Ada', '--email' => 'ada@example.test'])
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->assertSuccessful();

        $user = CmsUser::query()->firstOrFail();

        $this->assertSame('ada@example.test', $user->email);
        // Nobody could grant them anything otherwise.
        $this->assertTrue($user->is_super);
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));
    }

    #[Test]
    public function the_second_one_is_not(): void
    {
        $this->admin('first@example.test');

        $this->artisan('webx:admin', ['--name' => 'Grace', '--email' => 'grace@example.test'])
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->assertSuccessful();

        $this->assertFalse(CmsUser::query()->where('email', 'grace@example.test')->firstOrFail()->is_super);
    }

    #[Test]
    public function super_can_be_asked_for(): void
    {
        $this->admin('first@example.test');

        $this->artisan('webx:admin', [
            '--name' => 'Grace',
            '--email' => 'grace@example.test',
            '--super' => true,
        ])
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->assertSuccessful();

        $this->assertTrue(CmsUser::query()->where('email', 'grace@example.test')->firstOrFail()->is_super);
    }

    #[Test]
    public function an_address_is_not_reused(): void
    {
        $this->admin('taken@example.test');

        $this->artisan('webx:admin', ['--name' => 'Ada', '--email' => 'taken@example.test'])
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->assertFailed();

        $this->assertSame(1, CmsUser::query()->count());
    }

    #[Test]
    public function a_provisioning_script_passes_the_password_in_the_environment(): void
    {
        // No prompt to answer: this is how a deploy or a container entrypoint creates the first
        // administrator, and why there is no --password option to put it in the process list.
        putenv(CreateAdminCommand::PASSWORD_VARIABLE.'=correct-horse-battery');

        try {
            $this->artisan('webx:admin', ['--name' => 'Ada', '--email' => 'ada@example.test'])
                ->assertSuccessful();
        } finally {
            putenv(CreateAdminCommand::PASSWORD_VARIABLE);
        }

        $this->assertTrue(
            Hash::check('correct-horse-battery', CmsUser::query()->firstOrFail()->password),
        );
    }

    #[Test]
    public function with_no_password_and_nobody_to_ask_it_says_what_to_do(): void
    {
        $this->artisan('webx:admin', [
            '--name' => 'Ada',
            '--email' => 'ada@example.test',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain(CreateAdminCommand::PASSWORD_VARIABLE)
            ->assertFailed();

        $this->assertSame(0, CmsUser::query()->count());
    }

    #[Test]
    public function a_short_password_is_refused(): void
    {
        $this->artisan('webx:admin', ['--name' => 'Ada', '--email' => 'ada@example.test'])
            ->expectsQuestion('Password', 'short')
            ->assertFailed();

        $this->assertSame(0, CmsUser::query()->count());
    }
}
