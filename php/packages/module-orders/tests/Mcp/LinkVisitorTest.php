<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Users\Models\User;
use WebxUi\Users\UsersServiceProvider;

/**
 * `orders_link_visitor` (spec §12, §13.3) — there only with the visitors installed, and the same
 * `Orders::linkVisitor()` as the card: a dry run says whom, the real call writes the history.
 */
final class LinkVisitorTest extends McpTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        $providers = parent::getPackageProviders($app);
        array_splice($providers, (int) array_search(OrdersServiceProvider::class, $providers, true), 0, [UsersServiceProvider::class]);

        return $providers;
    }

    #[Test]
    public function the_agent_links_by_id_or_exact_address_and_unlinks(): void
    {
        $anna = User::query()->create(['email' => 'anna@example.test', 'password' => 'correct-horse-battery', 'first_name' => 'Anna']);
        $order = $this->order();

        $this->assertNotNull($this->app->make(ToolRegistry::class)->tool('orders_link_visitor'));

        $plan = $this->ask('orders_link_visitor', ['order' => $order->number, 'visitor' => 'ANNA@example.test', 'dry_run' => true]);
        $this->assertSame('linked', $plan['outcome']);
        $this->assertSame($anna->id, $plan['visitor']['id']);
        $this->assertTrue($plan['dry_run']);
        $this->assertNull($order->fresh()?->user_id);

        $this->ask('orders_link_visitor', ['order' => $order->number, 'visitor' => $anna->id]);
        $this->assertSame($anna->id, $order->fresh()?->user_id);
        $this->assertSame('mcp', StatusChange::query()->where('kind', StatusChange::VISITOR)->value('source'));

        $this->assertSame('unlinked', $this->ask('orders_link_visitor', ['order' => $order->number, 'unlink' => true])['outcome']);
        $this->assertNull($order->fresh()?->user_id);

        // A part of an address is not one: the tool looks up, it does not search.
        $this->agent('orders_link_visitor', ['order' => $order->number, 'visitor' => 'anna@'])->assertHasErrors();
    }
}
