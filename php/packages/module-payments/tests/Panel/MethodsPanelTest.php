<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Testing\FakeGateway;

/**
 * The methods of the panel (spec §13.2, §13.3, §15.2): the list and a method say only whether a key
 * is set — the value never reaches the browser; a typed key replaces it, a blank field keeps it,
 * «Remove key» clears it; the environment wins and is not offered for replacing; the type's
 * settings are checked by their fields; «Test the connection» is an event of the journal.
 */
final class MethodsPanelTest extends PanelCase
{
    #[Test]
    public function the_list_and_a_method_say_whether_keys_are_set_and_never_what_they_are(): void
    {
        $this->signIn();

        $list = $this->getJson($this->api('methods'))->assertOk();
        $this->assertStringNotContainsString(FakeGateway::SECRET, $list->getContent());

        $rows = $list->collect('data.methods')->keyBy('key');
        $this->assertSame(['set', 'gateway-a', 'test', []], [
            $rows['gateway-a-card']['credentials'], $rows['gateway-a-card']['gateway'], $rows['gateway-a-card']['mode'], $rows['gateway-a-card']['warnings'],
        ]);
        // The shop's own methods keep no keys at all.
        $this->assertNull($rows['bank-transfer']['credentials']);
        $this->assertContains('gateway-a.card', array_column($list->json('data.types'), 'key'));

        $card = PaymentMethod::query()->where('key', 'gateway-a-card')->firstOrFail();
        $show = $this->getJson($this->api('methods/'.$card->id))->assertOk();
        $this->assertStringNotContainsString(FakeGateway::SECRET, $show->getContent());
        $this->assertSame([['name' => 'secret_key', 'label' => 'Secret key', 'multiline' => false, 'set' => true, 'environment' => false]], $show->json('data.credentials'));
        $this->assertSame(['value' => false, 'partial' => true, 'days' => 7], $show->json('data.hold'));
        $this->assertStringEndsWith('/payments/notify/gateway-a-card', (string) $show->json('data.notify_url'));
        $this->assertContains('shop_id', array_column($show->json('data.settings.fields'), 'name'));
    }

    #[Test]
    public function a_typed_key_replaces_a_blank_one_keeps_and_remove_clears(): void
    {
        $this->signIn();
        $card = PaymentMethod::query()->where('key', 'gateway-a-card')->firstOrFail();

        $answer = $this->putJson($this->api('methods/'.$card->id), ['values' => [], 'credentials' => ['secret_key' => '   ']])->assertOk();
        $this->assertSame(['secret_key' => FakeGateway::SECRET], $card->refresh()->credentials, 'Blank is «leave it».');

        $answer = $this->putJson($this->api('methods/'.$card->id), ['values' => [], 'credentials' => ['secret_key' => 'another-fake-key-0002', 'unknown' => 'x-x-x-x-x-x']])->assertOk();
        $this->assertSame(['secret_key' => 'another-fake-key-0002'], $card->refresh()->credentials, 'Only the gateway\'s own fields.');
        $this->assertStringNotContainsString('another-fake-key-0002', $answer->getContent());

        $this->putJson($this->api('methods/'.$card->id), ['values' => [], 'clear' => ['secret_key']])->assertOk()
            ->assertJsonPath('data.method.credentials', 'missing')
            ->assertJsonPath('data.method.warnings', ['no_credentials']);
        $this->assertNull($card->refresh()->getRawOriginal('credentials'));
    }

    #[Test]
    public function a_key_in_the_environment_wins_and_is_not_offered_for_replacing(): void
    {
        $this->signIn();
        putenv('WEBX_PAYMENTS_GATEWAY_A_FORM_SECRET_KEY=fake-env-key-0003');
        $_ENV['WEBX_PAYMENTS_GATEWAY_A_FORM_SECRET_KEY'] = 'fake-env-key-0003';

        try {
            $form = PaymentMethod::query()->where('key', 'gateway-a-form')->firstOrFail();
            $form->forceFill(['credentials' => null])->save();

            $show = $this->getJson($this->api('methods/'.$form->id))->assertOk()
                ->assertJsonPath('data.method.credentials', 'environment')
                ->assertJsonPath('data.credentials.0.environment', true);
            $this->assertStringNotContainsString('fake-env-key-0003', $show->getContent());
        } finally {
            putenv('WEBX_PAYMENTS_GATEWAY_A_FORM_SECRET_KEY');
            unset($_ENV['WEBX_PAYMENTS_GATEWAY_A_FORM_SECRET_KEY']);
        }
    }

    #[Test]
    public function the_screen_the_settings_and_the_hold_are_saved_and_checked(): void
    {
        $this->signIn();
        $card = PaymentMethod::query()->where('key', 'gateway-a-card')->firstOrFail();

        $this->putJson($this->api('methods/'.$card->id), [
            'values' => ['titles' => ['en' => 'Card'], 'enabled' => true, 'delivery_mode' => 'except', 'delivery_keys' => 'carrier-a-branch, store-pickup', 'min_amount' => 1.5, 'max_amount' => 900],
            'settings' => ['shop_id' => ' shop-b ', 'expires_minutes' => '30', 'hold' => true, 'stranger' => 'no'],
        ])->assertOk()->assertJsonPath('data.method.hold', true);

        $card->refresh();
        $this->assertSame(['en' => 'Card'], $card->title);
        $this->assertSame(['mode' => 'except', 'methods' => ['carrier-a-branch', 'store-pickup']], $card->delivery_rule);
        $this->assertSame([150, 90000], [$card->min_amount, $card->max_amount]);
        $this->assertSame(['shop-b', 30, true], [$card->settings['shop_id'], $card->settings['expires_minutes'], $card->settings['hold']]);
        $this->assertArrayNotHasKey('stranger', $card->settings);

        $this->putJson($this->api('methods/'.$card->id), ['values' => [], 'settings' => ['mode' => 'live']])
            ->assertUnprocessable()->assertJsonValidationErrors('settings.mode');
        $this->putJson($this->api('methods/'.$card->id), ['values' => ['min_amount' => 10, 'max_amount' => 5]])
            ->assertUnprocessable()->assertJsonValidationErrors('max_amount');

        // A type that cannot capture later has no switch, and nothing is stored for it.
        $transfer = PaymentMethod::query()->where('key', 'bank-transfer')->firstOrFail();
        $this->getJson($this->api('methods/'.$transfer->id))->assertJsonPath('data.hold', null);
        $this->putJson($this->api('methods/'.$transfer->id), ['values' => [], 'settings' => ['hold' => true, 'recipient' => ['en' => 'Shop A Ltd']]])->assertOk();
        $this->assertArrayNotHasKey('hold', $transfer->refresh()->settings);
        $this->assertSame(['en' => 'Shop A Ltd'], $transfer->settings['recipient']);
    }

    #[Test]
    public function a_new_method_takes_its_type_and_a_key_of_its_own(): void
    {
        $this->signIn();

        $this->getJson($this->api('methods/blank?type=gateway-a.card'))->assertOk()->assertJsonPath('data.credentials.0.set', false);
        $made = $this->postJson($this->api('methods'), ['type' => 'gateway-a.card', 'values' => ['titles' => ['en' => 'Second card']], 'credentials' => ['secret_key' => 'fake-new-key-0004']])
            ->assertCreated()
            ->assertJsonPath('data.method.key', 'gateway-a-card-2')
            ->assertJsonPath('data.method.credentials', 'set');
        $this->assertStringNotContainsString('fake-new-key-0004', $made->getContent());

        $this->postJson($this->api('methods'), ['type' => 'nobody.nothing'])->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    #[Test]
    public function the_order_of_the_checkout_is_every_method_once(): void
    {
        $this->signIn();
        $ids = PaymentMethod::query()->orderByDesc('id')->pluck('id')->all();

        $this->putJson($this->api('methods/order'), ['ids' => $ids])->assertNoContent();
        $this->assertSame($ids, PaymentMethod::query()->orderBy('position')->pluck('id')->all());
        $this->putJson($this->api('methods/order'), ['ids' => array_slice($ids, 1)])->assertUnprocessable();
    }

    #[Test]
    public function test_the_connection_asks_the_gateway_and_the_request_is_journaled_masked(): void
    {
        $this->signIn();
        $card = PaymentMethod::query()->where('key', 'gateway-a-card')->firstOrFail();

        $this->postJson($this->api('methods/'.$card->id.'/test'))->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.message', 'Connected to shop '.FakeGateway::SHOP);

        $events = PaymentGatewayEvent::query()->where('kind', 'connection')->get();
        $this->assertNotEmpty($events);
        $this->assertSame(['panel'], $events->pluck('source')->unique()->values()->all());

        foreach (PaymentGatewayEvent::query()->get() as $event) {
            $this->assertStringNotContainsString(FakeGateway::SECRET, (string) $event->request_body.$event->response_body.json_encode($event->request_headers).$event->url);
        }

        // A method without a gateway has nothing to test.
        $transfer = PaymentMethod::query()->where('key', 'bank-transfer')->firstOrFail();
        $this->postJson($this->api('methods/'.$transfer->id.'/test'))->assertOk()->assertJsonPath('data.ok', false);
    }
}
