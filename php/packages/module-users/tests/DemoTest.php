<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Users\Models\Group;
use WebxUi\Users\Models\Identity;
use WebxUi\Users\Models\User;

/**
 * The demo customers (spec §18): two groups, five visitors with fake data and no passwords, one
 * linked to Google — and `--remove` takes every row of it back, the links and the pivot included.
 */
final class DemoTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_seeds_two_groups_and_five_visitors_without_passwords(): void
    {
        $this->artisan('webx:demo', ['--module' => 'users'])->assertSuccessful();

        $this->assertSame(['club', 'wholesale'], Group::query()->orderBy('code')->pluck('code')->all());
        $this->assertSame(5, User::query()->count());
        $this->assertSame(0, User::query()->whereNotNull('password')->count());
        $this->assertSame(1, User::query()->whereNull('email_verified_at')->count());
        $this->assertSame(0, User::query()->where('email', 'not like', '%@example.com')->count());
        $this->assertSame(4, User::query()->where('phone', 'like', '+800%')->count());

        $lena = User::query()->where('email', 'lena.demo@example.com')->firstOrFail();
        $this->assertEqualsCanonicalizing(['wholesale', 'club'], $lena->groups()->pluck('code')->all());

        $identity = Identity::query()->sole();
        $this->assertSame('google', $identity->provider);
        $this->assertSame('iris.demo@example.com', $identity->user?->email);
    }

    #[Test]
    public function a_site_with_visitors_of_its_own_gets_none(): void
    {
        $this->visitor(['email' => 'real@example.test']);

        $this->artisan('webx:demo', ['--module' => 'users'])->assertSuccessful();

        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, Group::query()->count());
    }

    #[Test]
    public function the_removal_leaves_nothing_behind(): void
    {
        $this->artisan('webx:demo', ['--module' => 'users'])->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Group::query()->count());
        $this->assertSame(0, Identity::query()->count());
        $this->assertSame(0, $this->app['db']->table('users_group_user')->count());
    }
}
