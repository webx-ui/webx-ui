<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * The screen `orders.settings` (spec §4, §5.2, §11.4) on a site with `module-settings`: the number
 * with its trial number, the statuses a new order comes into — and the status chosen there is the
 * one the statuses screen will not let go.
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
}
