<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\LoginRecord;
use WebxUi\Mcp\Registry\ToolRegistry;

final class AuthMcpToolsTest extends TestCase
{
    private function tool(string $name): callable
    {
        return $this->app->make(ToolRegistry::class)->tool($name)->tool->handler;
    }

    #[Test]
    public function the_module_offers_its_tools_with_read_audit_and_write_scopes(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            [
                'users_list_users',
                'users_list_roles',
                'users_grant_role',
                'users_revoke_role',
                'users_recent_sign_ins',
                'users_failed_sign_in_bursts',
            ],
            array_map(fn ($tool) => $tool->fullName(), $registry->tools()),
        );

        $this->assertSame(['users:audit', 'users:read', 'users:write'], $registry->scopes());
    }

    #[Test]
    public function nothing_on_offer_touches_a_password_or_mints_a_token(): void
    {
        $names = array_map(
            fn ($tool) => $tool->fullName(),
            $this->app->make(ToolRegistry::class)->tools(),
        );

        foreach (['password', 'token', 'create_user', 'delete_user'] as $forbidden) {
            $this->assertEmpty(
                array_filter($names, static fn (string $name): bool => str_contains($name, $forbidden)),
                "A tool touching [{$forbidden}] is on offer to agents.",
            );
        }
    }

    #[Test]
    public function it_lists_administrators_with_their_roles(): void
    {
        $admin = $this->admin();
        $admin->roles()->attach($this->role('editor', ['pages.view']));

        $rows = ($this->tool('users_list_users'))([]);

        $this->assertCount(1, $rows);
        $this->assertSame('admin@example.test', $rows[0]['email']);
        $this->assertSame(['editor'], $rows[0]['roles']);
    }

    #[Test]
    public function granting_a_role_can_be_asked_about_before_it_happens(): void
    {
        $admin = $this->admin();
        $this->role('editor', ['pages.view']);

        $preview = ($this->tool('users_grant_role'))([
            'email' => 'admin@example.test',
            'role' => 'editor',
            'dry_run' => true,
        ]);

        $this->assertTrue($preview['would_change']);
        $this->assertFalse($preview['applied']);
        $this->assertSame(0, $admin->roles()->count());

        $applied = ($this->tool('users_grant_role'))([
            'email' => 'admin@example.test',
            'role' => 'editor',
        ]);

        $this->assertTrue($applied['applied']);
        $this->assertSame(1, $admin->roles()->count());
    }

    #[Test]
    public function revoking_is_the_same_in_reverse(): void
    {
        $admin = $this->admin();
        $admin->roles()->attach($this->role('editor', ['pages.view']));

        ($this->tool('users_revoke_role'))(['email' => 'admin@example.test', 'role' => 'editor']);

        $this->assertSame(0, $admin->roles()->count());
    }

    #[Test]
    public function a_role_change_that_would_do_nothing_says_so(): void
    {
        $this->admin();
        $this->role('editor', ['pages.view']);

        $result = ($this->tool('users_revoke_role'))([
            'email' => 'admin@example.test',
            'role' => 'editor',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['would_change']);
    }

    #[Test]
    public function an_unknown_address_or_role_is_reported_rather_than_thrown(): void
    {
        $this->role('editor', ['pages.view']);

        $this->assertFalse(($this->tool('users_grant_role'))([
            'email' => 'ghost@example.test',
            'role' => 'editor',
        ])['ok']);

        $this->admin();

        $this->assertFalse(($this->tool('users_grant_role'))([
            'email' => 'admin@example.test',
            'role' => 'nope',
        ])['ok']);
    }

    #[Test]
    public function it_finds_bursts_of_failed_sign_ins(): void
    {
        foreach (range(1, 6) as $i) {
            LoginRecord::query()->create([
                'email' => 'target@example.test',
                'successful' => false,
                'ip' => '10.0.0.1',
                'created_at' => Carbon::now()->subMinutes($i),
            ]);
        }

        LoginRecord::query()->create([
            'email' => 'quiet@example.test',
            'successful' => false,
            'created_at' => Carbon::now(),
        ]);

        // Old noise must not count towards the window.
        LoginRecord::query()->create([
            'email' => 'target@example.test',
            'successful' => false,
            'created_at' => Carbon::now()->subDays(30),
        ]);

        $bursts = ($this->tool('users_failed_sign_in_bursts'))([]);

        $this->assertCount(1, $bursts);
        $this->assertSame('target@example.test', $bursts[0]['email']);
        $this->assertSame(6, $bursts[0]['attempts']);
    }

    #[Test]
    public function recent_sign_ins_are_capped(): void
    {
        foreach (range(1, 5) as $i) {
            LoginRecord::query()->create([
                'email' => "person{$i}@example.test",
                'successful' => true,
                'created_at' => Carbon::now()->subMinutes($i),
            ]);
        }

        $this->assertCount(2, ($this->tool('users_recent_sign_ins'))(['limit' => 2]));
        $this->assertCount(5, ($this->tool('users_recent_sign_ins'))([]));
    }
}
