<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Site;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Visitors\Events\VisitorDeleting;
use WebxUi\Orders\Events\OrderDetailsChanged;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Orders;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Orders\Tests\Panel\PanelTestCase;
use WebxUi\Orders\VisitorLink;
use WebxUi\Orders\VisitorRefused;
use WebxUi\Users\Account\AccountMenu;
use WebxUi\Users\Account\DeletionWarnings;
use WebxUi\Users\Models\User;
use WebxUi\Users\UsersServiceProvider;

/**
 * The orders of a visitor with `webx-ui/module-users` installed (spec §13.2, §13.3): «My orders»
 * shows the visitor's own and nobody else's, a manager links a guest's order to an account and
 * back through `Orders`, the card of a visitor gets its tab, and an account that goes leaves its
 * orders to nobody.
 */
final class VisitorOrdersTest extends PanelTestCase
{
    /** @var list<OrderDetailsChanged> */
    private array $heard = [];

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

    private function visitor(string $email, string $first, ?string $last = null): User
    {
        return User::query()->create(['email' => $email, 'password' => 'correct-horse-battery', 'first_name' => $first, 'last_name' => $last]);
    }

    #[Test]
    public function my_orders_are_the_visitors_own_and_another_ones_is_a_404(): void
    {
        $anna = $this->visitor('anna@example.test', 'Anna', 'Petrova');
        $boris = $this->visitor('boris@example.test', 'Boris');
        $mine = $this->order(['userId' => $anna->id]);
        $theirs = $this->order(['userId' => $boris->id]);
        $guest = $this->order();

        $this->get('/account/orders')->assertRedirect();

        $this->actingAs($anna, 'site');
        $list = $this->get('/account/orders')->assertOk();
        $this->assertStringContainsString('no-store', (string) $list->headers->get('Cache-Control'));
        $list->assertSee('Order '.$mine->number)
            ->assertDontSee('Order '.$theirs->number)
            ->assertDontSee('Order '.$guest->number);

        $this->get('/account/orders/'.$mine->number)->assertOk()->assertSee('Item A')->assertSee('Accepted');
        $this->get('/account/orders/'.$theirs->number)->assertNotFound();
        $this->get('/account/orders/'.$guest->number)->assertNotFound();
        $this->get('/ru/account/orders/'.$mine->number)->assertOk()->assertSee('Товар A');
    }

    #[Test]
    public function the_menu_counts_the_orders_still_in_progress(): void
    {
        $anna = $this->visitor('anna@example.test', 'Anna');
        $order = $this->order(['userId' => $anna->id]);
        $this->order(['userId' => $anna->id]);
        $section = $this->app->make(AccountMenu::class)->get('orders');

        $this->assertNotNull($section);
        $this->assertSame(OrderLinks::ACCOUNT, $section->route);
        $this->assertSame('2', ($section->badge)($anna->toVisitor()));

        $this->app->make(Orders::class)->mark($order, Milestone::Received, 'panel');
        $this->assertSame('1', ($section->badge)($anna->toVisitor()));
    }

    #[Test]
    public function linking_writes_the_history_the_journal_and_the_event_and_a_dry_run_nothing(): void
    {
        $anna = $this->visitor('anna@example.test', 'Anna', 'Petrova');
        $boris = $this->visitor('boris@example.test', 'Boris');
        $order = $this->order();
        $orders = $this->app->make(Orders::class);
        Event::listen(OrderDetailsChanged::class, function (OrderDetailsChanged $event): void {
            $this->heard[] = $event;
        });

        $plan = $orders->linkVisitor($order, $anna->id, 'panel', dryRun: true);
        $this->assertSame(VisitorLink::LINKED, $plan->outcome);
        $this->assertNull($plan->previousId);
        $this->assertNull($order->refresh()->user_id);
        $this->assertSame(0, StatusChange::query()->where('kind', StatusChange::VISITOR)->count());
        $this->assertCount(0, $this->heard);

        $orders->linkVisitor($order, $anna->id, 'panel');
        $this->assertSame($anna->id, $order->refresh()->user_id);
        $this->assertSame('linked: Anna Petrova <anna@example.test>', StatusChange::query()->where('kind', StatusChange::VISITOR)->value('note'));
        $this->assertCount(1, $this->heard);
        $this->assertSame(['user_id'], $this->heard[0]->fields);
        $this->assertSame(['user_id' => null], $this->heard[0]->before);
        $this->assertSame($anna->id, $this->heard[0]->order->userId);
        $this->assertSame(1, HistoryEntry::query()->where('subject_id', $order->id)->where('event', 'updated')->count());
        // The contacts stay what the buyer typed.
        $this->assertSame('alex@example.test', $order->refresh()->customer_email);

        // Moved to another visitor in one step, and back to nobody.
        $moved = $orders->linkVisitor($order, $boris->id, 'panel');
        $this->assertSame($anna->id, $moved->previous?->id);
        $this->assertSame(['user_id' => $anna->id], $this->heard[1]->before);
        $orders->unlinkVisitor($order, 'panel');
        $this->assertNull($order->refresh()->user_id);
        $this->assertSame('unlinked: Boris <boris@example.test>', StatusChange::query()->where('kind', StatusChange::VISITOR)->orderByDesc('id')->value('note'));

        // So already: silence.
        $this->assertSame(VisitorLink::UNCHANGED, $orders->unlinkVisitor($order, 'panel')->outcome);
        $this->assertCount(3, $this->heard);

        $this->expectException(VisitorRefused::class);
        $orders->linkVisitor($order, $boris->id + 100, 'panel');
    }

    #[Test]
    public function the_card_links_a_visitor_found_by_a_part_of_the_name_or_the_address(): void
    {
        $anna = $this->visitor('alex@example.test', 'Anna', 'Petrova');
        $this->visitor('petrov@example.test', 'Ivan', 'Petrov');
        $order = $this->order();

        // Finding a visitor is reading the customers.
        $this->signIn([OrdersModule::VIEW, OrdersModule::MANAGE]);
        $this->getJson($this->api($order->id.'/visitors?search=petro'))->assertForbidden();
        $card = $this->getJson($this->api($order->id))->assertOk()->json('data.order');
        $this->assertTrue($card['actions']['visitor']);
        $this->assertFalse($card['actions']['visitor_search']);
        $this->assertNull($card['visitor']);

        $this->signIn([OrdersModule::VIEW, OrdersModule::MANAGE, 'users.view']);
        $found = $this->getJson($this->api($order->id.'/visitors?search=petro'))->assertOk()->json('data');
        $this->assertCount(2, $found);
        $this->assertSame([true, false], array_column($found, 'same_email'));
        $this->assertSame($anna->id, $this->getJson($this->api($order->id.'/visitors?search=Anna'))->json('data.0.id'));

        $plan = $this->postJson($this->api($order->id.'/visitor'), ['user_id' => $anna->id, 'dry_run' => true])->assertOk()->json('data');
        $this->assertSame('linked', $plan['outcome']);
        $this->assertNull($order->refresh()->user_id);

        $card = $this->postJson($this->api($order->id.'/visitor'), ['user_id' => $anna->id])->assertOk()->json('data.order');
        $this->assertSame(['id' => $anna->id, 'name' => 'Anna Petrova', 'email' => 'alex@example.test'], $card['visitor']);
        $this->assertContains('visitor-linked', array_column($card['timeline'], 'kind'));

        $this->deleteJson($this->api($order->id.'/visitor'))->assertOk();
        $this->assertNull($order->refresh()->user_id);
        $this->postJson($this->api($order->id.'/visitor'), ['user_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        // Working an order is `orders.manage`.
        $this->signIn([OrdersModule::VIEW, 'users.view']);
        $this->postJson($this->api($order->id.'/visitor'), ['user_id' => $anna->id])->assertForbidden();
    }

    #[Test]
    public function an_account_that_goes_warns_first_and_leaves_its_orders_to_nobody(): void
    {
        $anna = $this->visitor('anna@example.test', 'Anna');
        $active = $this->order(['userId' => $anna->id]);
        $done = $this->order(['userId' => $anna->id]);
        $this->app->make(Orders::class)->mark($done, Milestone::Received, 'panel');

        $warnings = $this->app->make(DeletionWarnings::class)->for($anna->toVisitor());
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('№'.$active->number, $warnings[0]);
        $this->assertStringNotContainsString('№'.$done->number, $warnings[0]);

        Event::dispatch(new VisitorDeleting($anna->id));

        $this->assertSame(0, Order::query()->where('user_id', $anna->id)->count());
        // The contacts are the record of the sale.
        $this->assertSame('alex@example.test', $active->refresh()->customer_email);
        $this->assertSame(2, StatusChange::query()->where('kind', StatusChange::VISITOR)->where('note', 'unlinked: Anna <anna@example.test>')->count());
    }

    #[Test]
    public function the_cards_get_the_orders_of_the_visitor_and_the_account_of_the_order(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);
        $ids = static function (array $nodes) use (&$ids): array {
            $found = [];

            foreach ($nodes as $node) {
                $found[] = $node['id'];
                $found = [...$found, ...$ids($node['children'] ?? [])];
            }

            return $found;
        };

        $user = $ids($screens->render('users.form', static fn (string $permission): bool => $permission === OrdersModule::VIEW));
        $this->assertContains('orders-list', $user);
        $this->assertContains('orders-count', $user);
        $this->assertNotContains('orders-list', $ids($screens->render('users.form', static fn (): bool => false)));
        $this->assertContains('visitor', $ids($screens->render(OrdersModule::SCREEN)));
        $this->assertTrue(Route::has('webx.orders.panel.visitor.link'));
    }
}
