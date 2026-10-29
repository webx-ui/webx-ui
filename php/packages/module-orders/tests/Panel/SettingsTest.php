<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\OrderSettings;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * The screen `orders.settings` (spec §4, §5.2, §11.4) on a site with `module-settings`: the number
 * with its trial number, the statuses a new order comes into — and the status chosen there is the
 * one the statuses screen will not let go. The tab «Checkout» (§31.2) behind its own right.
 */
final class SettingsTest extends PanelTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), SettingsServiceProvider::class];
    }

    private function named(string $name): OrderStatus
    {
        return OrderStatus::withTrashed()->get()->firstOrFail(static fn (OrderStatus $status): bool => $status->getTranslation('name', 'en') === $name);
    }

    #[Test]
    public function the_trial_number_is_the_one_the_next_order_gets(): void
    {
        $first = $this->order();
        $this->assertSame('1001', $first->number);
        $this->signIn(['orders.statuses']);

        $this->getJson($this->api('settings'))
            ->assertOk()
            ->assertJsonPath('data.stored', true)
            ->assertJsonPath('data.next_id', $first->id + 1)
            ->assertJsonPath('data.sample', (string) (1001 + $first->id))
            ->assertJsonPath('data.values', [
                'orders.number.prefix' => '',
                'orders.number.start' => 1000,
                'orders.number.pad' => 0,
                'orders.initial_status' => null,
                'orders.initial_status_awaiting_payment' => null,
                // Read from the config: the site has no notifications module, nothing is told.
                'orders.notify.placed' => [],
                'orders.notify.paid' => [],
                'orders.notify.payment_failed' => [],
                'orders.notify.received' => [],
                'orders.notify.cancelled' => [],
                'orders.notify.refunded' => [],
                // The tab «Checkout» (§31.2), nothing saved: the defaults.
                'orders.checkout.fields.last_name' => 'optional',
                'orders.checkout.fields.phone' => 'required',
                'orders.checkout.fields.email' => 'required',
                'orders.checkout.recipient' => 'on',
                'orders.checkout.comment' => 'on',
                'orders.checkout.terms' => 'notice',
                'orders.checkout.terms_page' => null,
                'orders.checkout.privacy_page' => null,
                'orders.checkout.captcha' => 'off',
                'orders.checkout.quick.enabled' => 'off',
                'orders.checkout.quick.message' => null,
            ]);

        $values = ['orders.number.prefix' => 'A-', 'orders.number.start' => 0, 'orders.number.pad' => 6];
        $this->putJson($this->api('settings'), ['values' => $values, 'dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.sample', 'A-'.str_pad((string) ($first->id + 1), 6, '0', STR_PAD_LEFT));
        // A dry run keeps nothing.
        $this->assertSame('', $this->getJson($this->api('settings'))->json('data.values')['orders.number.prefix']);

        $saved = $this->putJson($this->api('settings'), ['values' => $values])->assertOk()->json('data.values');
        $this->assertSame('A-', $saved['orders.number.prefix']);

        $next = $this->order();
        $this->assertSame('A-'.str_pad((string) $next->id, 6, '0', STR_PAD_LEFT), $next->number);
        // Given out already, the first number stays.
        $this->assertSame('1001', $first->refresh()->number);
    }

    #[Test]
    public function a_format_whose_next_number_is_taken_is_refused(): void
    {
        $first = $this->order();
        $this->signIn(['orders.statuses']);

        // Start 999 makes the next order 999 + 2 = 1001 — the number of the first.
        $this->putJson($this->api('settings'), ['values' => ['orders.number.start' => 1000 - $first->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('orders.number.prefix');

        $this->putJson($this->api('settings'), ['values' => ['orders.number.pad' => 21]])->assertUnprocessable()->assertJsonValidationErrors('orders.number.pad');
    }

    #[Test]
    public function the_initial_status_chosen_here_is_where_orders_come_and_what_stays(): void
    {
        $this->signIn(['orders.statuses']);
        $taken = $this->named('Taken into work');

        $this->putJson($this->api('settings'), ['values' => ['orders.initial_status' => $taken->id]])->assertOk();
        $this->assertSame($taken->id, $this->order()->status_id);

        $this->deleteJson($this->api('statuses/internal/'.$taken->id), ['dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.refusal', 'initial');
        // «New» is not initial any more, and may go.
        $this->deleteJson($this->api('statuses/internal/'.$this->named('New')->id), ['dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.refusal', null);

        // A switched-off status is not offered, and the server holds the same list.
        $this->putJson($this->api('statuses/internal/'.$this->named('Being packed')->id), [
            'public_status_id' => $taken->public_status_id, 'name' => ['en' => 'Being packed'], 'color' => 'accent', 'is_active' => false,
        ])->assertOk();
        $this->putJson($this->api('settings'), ['values' => ['orders.initial_status' => $this->named('Being packed')->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('orders.initial_status');
    }

    #[Test]
    public function each_tab_is_behind_its_own_right(): void
    {
        $this->signIn(['orders.checkout.settings']);

        $root = $this->getJson('/api/cms/screens/orders.settings')->assertOk()->json('data.root');
        $this->assertSame(['checkout-tab'], array_column($root[0]['children'], 'id'));

        // The section's keys are not this right's to write: they are dropped, the tab's are kept.
        $values = $this->putJson($this->api('settings'), ['values' => ['orders.number.prefix' => 'X-', 'orders.checkout.terms' => 'checkbox', 'orders.checkout.fields.last_name' => 'hidden']])
            ->assertOk()
            ->json('data.values');
        $this->assertSame('', $values['orders.number.prefix']);
        $this->assertSame('checkbox', $values['orders.checkout.terms']);
        $this->assertSame('hidden', $values['orders.checkout.fields.last_name']);

        $this->putJson($this->api('settings'), ['values' => ['orders.checkout.terms' => 'always']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('orders.checkout.terms');

        $this->signIn(['orders.statuses']);
        $root = $this->getJson('/api/cms/screens/orders.settings')->assertOk()->json('data.root');
        $this->assertSame(['orders-tab'], array_column($root[0]['children'], 'id'));
        $this->putJson($this->api('settings'), ['values' => ['orders.checkout.terms' => 'none']])->assertOk();
        $this->assertSame('checkbox', $this->app->make(OrderSettings::class)->get('orders.checkout.terms'));

        $this->signIn(['orders.view']);
        $this->getJson($this->api('settings'))->assertForbidden();
    }

    #[Test]
    public function the_phone_or_the_email_stays_required(): void
    {
        $this->signIn(['orders.checkout.settings']);

        $this->putJson($this->api('settings'), ['values' => ['orders.checkout.fields.phone' => 'optional']])->assertOk();
        // Both optional — an order could come with neither: refused, and so is hiding both.
        $this->putJson($this->api('settings'), ['values' => ['orders.checkout.fields.email' => 'optional']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('orders.checkout.fields.phone');
        $this->putJson($this->api('settings'), ['values' => ['orders.checkout.fields.phone' => 'hidden', 'orders.checkout.fields.email' => 'hidden']])
            ->assertUnprocessable();
        $this->putJson($this->api('settings'), ['values' => ['orders.checkout.fields.phone' => 'hidden', 'orders.checkout.fields.email' => 'required']])->assertOk();
    }
}
