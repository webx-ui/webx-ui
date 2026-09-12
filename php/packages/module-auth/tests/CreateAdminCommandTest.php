<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
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
    public function a_short_password_is_refused(): void
    {
        $this->artisan('webx:admin', ['--name' => 'Ada', '--email' => 'ada@example.test'])
            ->expectsQuestion('Password', 'short')
            ->assertFailed();

        $this->assertSame(0, CmsUser::query()->count());
    }
}
