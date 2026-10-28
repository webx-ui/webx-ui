<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests\Mcp;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Users\Models\Group;
use WebxUi\Users\Models\Identity;
use WebxUi\Users\Models\User;
use WebxUi\Users\Panel\UsersModule;

/**
 * The query budget of the agent's lists (spec §18, WEBX_UI_PERFORMANCE.md): a page of customers
 * asks for its groups and its links once each, so ten rows and fifty cost the same; the groups
 * are counted in the one query that lists them.
 *
 * No cache group: a customer is personal data that changes with every sign-in, and a list of
 * them read by an agent now and then is not worth a copy of it kept somewhere else. Measured
 * warm, the way the panel's lists are: the caller and their roles are read once a request.
 */
final class PerformanceTest extends McpTestCase
{
    use CountsQueries;

    #[Test]
    public function the_agents_lists_cost_the_same_with_ten_customers_and_with_fifty(): void
    {
        $admin = $this->administrator([UsersModule::VIEW]);
        $groups = [Group::query()->create(['code' => 'club', 'name' => 'Club'])->id, Group::query()->create(['code' => 'wholesale', 'name' => 'Wholesale'])->id];

        foreach ([10, 50] as $count) {
            for ($i = $this->countVisitors(); $i < $count; $i++) {
                $user = $this->visitor();
                $user->groups()->attach($groups);
                Identity::query()->create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'g-'.$i]);
            }

            // [tool, arguments, budget] — every budget has the journal's line of the call (mcp_calls) in it.
            foreach ([
                // The caller's roles, the providers' rows, the count, the page, its groups, its links.
                ['users_list', ['per_page' => $count], 7],
                ['users_list', ['per_page' => $count, 'group' => 'club', 'search' => 'example'], 8],
                // The groups with their counts in one query.
                ['users_groups_list', [], 3],
            ] as [$tool, $arguments, $budget]) {
                $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));
                $call = static fn () => WebxServer::actingAs($admin, 'cms')->tool($bound, $arguments)->assertOk();
                $call();

                $this->assertQueriesWithin($budget, $call, "{$tool}, {$count} customers");
                $this->assertNoRepeatedQueries($call, "{$tool}, {$count} customers");
            }
        }
    }

    private function countVisitors(): int
    {
        return User::query()->count();
    }
}
