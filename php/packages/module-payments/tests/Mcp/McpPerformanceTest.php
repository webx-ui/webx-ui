<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Payments\Payments;

/**
 * The agent's lists (spec §16, §18), warm, on four orders whose card gateway A declined: the
 * methods, the attempts, the problems and the exchange journal. None repeats a statement per row,
 * and the budget is the same for one order and for four.
 */
final class McpPerformanceTest extends McpCase
{
    use CountsQueries;

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2: int}> */
    public static function lists(): array
    {
        // Each budget counts the server's own line in `mcp_calls`: the methods and the groups are one query, the
        // attempts their page, its count and the orders' numbers, the problems four lists and the
        // methods, the journal its page and the names of its orders, attempts and methods.
        return [
            'methods' => ['payments_methods_list', [], 2],
            'groups' => ['payments_groups_list', [], 2],
            'attempts' => ['payments_list', [], 4],
            'problems' => ['payments_problems', [], 5],
            'exchange' => ['payments_exchange_list', [], 5],
            'exchange of an order' => ['payments_exchange_list', ['errors' => true, 'kind' => 'status'], 5],
        ];
    }

    /** @param  array<string, mixed>  $arguments */
    #[Test]
    #[DataProvider('lists')]
    public function a_list_reads_each_thing_once_whatever_the_rows(string $tool, array $arguments, int $budget): void
    {
        for ($i = 0; $i < 4; $i++) {
            [, $payment] = $this->attemptAtGateway('card');
            $this->server()->act($payment->uuid, 'decline');
            $this->app->make(Payments::class)->check($payment->refresh(), 'cli');
        }

        $user = CmsUser::query()->findOrFail($this->signIn(['payments.view', 'payments.manage', 'orders.manage'])->getKey());
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));
        $call = static fn () => WebxServer::actingAs($user, 'cms')->tool($bound, $arguments)->assertOk();

        $call();
        $this->assertNoRepeatedQueries($call, $tool);
        $this->assertQueriesWithin($budget, $call, $tool);
    }
}
