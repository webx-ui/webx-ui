<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Testing\FakeCarrier;
use WebxUi\Delivery\Tests\Fakes\FakeCarrierB;
use WebxUi\Delivery\Tests\Mcp\CallsTools;
use WebxUi\Delivery\Tests\Mcp\McpTestCase;

/**
 * The options of a carrier's settings from its account (§13.1): the chain sender → contact and
 * town → branch, the key they wait for, the cache and «Refresh lists», a save that takes only a
 * value the account has and keeps its label, a carrier that is down, a value gone from the
 * account, a carrier without options — and the agent's door saying the same.
 */
final class SettingOptionsTest extends McpTestCase
{
    use CallsTools;

    private FakeCarrier $carrier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrier = new FakeCarrier(localities: 6, points: 12);
        $this->app->make(Carriers::class)->register($this->carrier);
        DeliveryCarrier::query()->create(['key' => FakeCarrier::KEY, 'enabled' => true, 'credentials' => ['api_key' => 'secret-123']]);
    }

    #[Test]
    public function the_options_come_down_the_chain_and_wait_for_their_parents(): void
    {
        $this->signIn();

        $this->getJson($this->api('carriers/carrier-a/options/sender'))
            ->assertOk()
            ->assertJsonPath('data.0', ['value' => 'sender-a', 'label' => 'Sender A', 'hint' => 'Tax id 00000001'])
            ->assertJsonCount(2, 'data')
            ->assertDontSee('secret-123');

        // A contact waits for its sender, in words that name the field.
        $this->getJson($this->api('carriers/carrier-a/options/contact'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Choose first: Sender');

        $this->getJson($this->api('carriers/carrier-a/options/contact?values[sender]=sender-b'))
            ->assertOk()
            ->assertJsonPath('data.*.value', ['contact-b1']);

        // A search field: what was typed; the branch waits for its town and is a branch, never a locker.
        $this->getJson($this->api('carriers/carrier-a/options/sender_city?q=Town 2'))
            ->assertOk()
            ->assertJsonPath('data.*.value', ['L2']);
        $this->getJson($this->api('carriers/carrier-a/options/sender_point?values[sender_city]=L2'))
            ->assertOk()
            ->assertJsonPath('data.*.value', ['P2', 'P8']);

        $this->getJson($this->api('carriers/carrier-a/options/weight'))->assertNotFound();
    }

    #[Test]
    public function the_options_wait_for_a_key_and_are_kept_ten_minutes_unless_refreshed(): void
    {
        $this->signIn();
        DeliveryCarrier::query()->whereKey(FakeCarrier::KEY)->update(['credentials' => null]);

        $this->getJson($this->api('carriers/carrier-a/options/sender'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Set the API key and check the connection first.');
        $this->assertSame([], $this->carrier->optionCalls);

        $this->putJson($this->api('carriers/carrier-a'), ['credentials' => ['api_key' => 'secret-123']])->assertOk();
        $this->getJson($this->api('carriers/carrier-a/options/sender'))->assertOk();
        $this->getJson($this->api('carriers/carrier-a/options/sender'))->assertOk();
        $this->assertCount(1, $this->carrier->optionCalls);

        // «Refresh lists» goes past the cache; another key asks anew.
        $this->getJson($this->api('carriers/carrier-a/options/sender?fresh=1'))->assertOk();
        $this->assertCount(2, $this->carrier->optionCalls);
        $this->putJson($this->api('carriers/carrier-a'), ['credentials' => ['api_key' => 'another-456']])->assertOk();
        $this->getJson($this->api('carriers/carrier-a/options/sender'))->assertOk();
        $this->assertCount(3, $this->carrier->optionCalls);
    }

    #[Test]
    public function a_save_takes_only_what_the_account_has_and_keeps_its_label(): void
    {
        $this->signIn();

        $this->putJson($this->api('carriers/carrier-a'), ['settings' => [
            'sender' => 'sender-a', 'contact' => 'contact-a2', 'sender_city' => 'L2', 'sender_point' => 'P8', 'weight' => 1.5,
        ]])
            ->assertOk()
            ->assertJsonPath('data.settings.values._labels', [
                'sender' => 'Sender A', 'contact' => 'Contact A2', 'sender_city' => 'Town 2', 'sender_point' => 'Branch 8',
            ])
            ->assertJsonPath('data.settings.values.weight', 1.5);

        // A contact of another sender, a branch of another town: «Choose from the list», nothing stored.
        $this->putJson($this->api('carriers/carrier-a'), ['settings' => ['contact' => 'contact-b1', 'sender_point' => 'P3']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.contact' => 'Choose from the list.', 'settings.sender_point']);
        $this->assertSame('contact-a2', DeliveryCarrier::query()->find(FakeCarrier::KEY)?->settings['contact']);

        // A new sender with the old contact: the contact is checked again, and is not there.
        $this->putJson($this->api('carriers/carrier-a'), ['settings' => ['sender' => 'sender-b']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.contact']);

        // Emptied — the label goes with the value.
        $this->putJson($this->api('carriers/carrier-a'), ['settings' => ['sender' => 'sender-b', 'contact' => null]])
            ->assertOk()
            ->assertJsonPath('data.settings.values._labels.sender', 'Sender B')
            ->assertJsonMissingPath('data.settings.values._labels.contact');

        // The labels are the base's: a request cannot write them.
        $this->putJson($this->api('carriers/carrier-a'), ['settings' => ['_labels' => ['sender' => 'Forged']]])
            ->assertOk()
            ->assertJsonPath('data.settings.values._labels.sender', 'Sender B');
    }

    #[Test]
    public function a_carrier_down_keeps_the_stored_label_and_the_other_fields_save(): void
    {
        $this->signIn();
        $this->putJson($this->api('carriers/carrier-a'), ['settings' => ['sender' => 'sender-a', 'contact' => 'contact-a1']])->assertOk();
        $this->carrier->failOptions();

        $this->getJson($this->api('carriers/carrier-a/options/contact?values[sender]=sender-a&fresh=1'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Could not get the list from the carrier\'s account: The carrier did not answer')
            ->assertDontSee('HTTP 503');

        // The dialog still has the label to show, and a plain field saves without the carrier.
        $this->getJson($this->api('carriers/carrier-a'))
            ->assertOk()
            ->assertJsonPath('data.settings.values._labels.contact', 'Contact A1');
        $this->putJson($this->api('carriers/carrier-a'), ['settings' => ['weight' => 2, 'contact' => 'contact-a1']])
            ->assertOk()
            ->assertJsonPath('data.settings.values.weight', 2);

        // A field with options cannot change without the carrier to check it against.
        $this->putJson($this->api('carriers/carrier-a'), ['settings' => ['contact' => 'contact-a2']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.contact' => 'Could not get the list from the carrier\'s account: The carrier did not answer']);
    }

    #[Test]
    public function a_value_gone_from_the_account_is_said_and_kept(): void
    {
        $this->signIn();
        $this->putJson($this->api('carriers/carrier-a'), ['settings' => ['sender' => 'sender-a', 'contact' => 'contact-a2']])->assertOk();
        $this->carrier->removeContact('contact-a2');
        $this->getJson($this->api('carriers/carrier-a/options/contact?values[sender]=sender-a&fresh=1'))->assertOk();

        $this->getJson($this->api('carriers/carrier-a'))
            ->assertOk()
            ->assertJsonPath('data.settings.missing', ['contact' => 'Contact A2'])
            ->assertJsonPath('data.settings.values.contact', 'contact-a2');
        // The screen of the carriers says it from what was last asked — asking the carrier nothing.
        $calls = count($this->carrier->optionCalls);
        $this->getJson($this->api('carriers'))->assertOk()->assertJsonPath('data.0.missing', ['Contact A2']);
        $this->assertCount($calls, $this->carrier->optionCalls);

        $this->assertSame(['contact' => 'Contact A2'], $this->use('delivery_carriers_list')['carriers'][0]['settings']['missing']);
    }

    #[Test]
    public function a_carrier_without_options_keeps_its_plain_fields(): void
    {
        $this->app->make(Carriers::class)->register(new FakeCarrierB);
        DeliveryCarrier::query()->create(['key' => 'carrier-b', 'enabled' => true]);
        $this->signIn();

        $this->getJson($this->api('carriers/carrier-b/options/sender'))
            ->assertNotFound()
            ->assertJsonPath('message', 'Carrier carrier-b has no options from its account.');
        $this->putJson($this->api('carriers/carrier-b'), ['settings' => ['sender' => 'Typed by hand']])
            ->assertOk()
            ->assertJsonPath('data.settings.values', ['sender' => 'Typed by hand'])
            ->assertJsonPath('data.settings.missing', []);
        $this->agent('delivery_carriers_options', ['carrier' => 'carrier-b', 'field' => 'sender'])
            ->assertHasErrors(['Carrier carrier-b has no options from its account.']);
    }

    #[Test]
    public function the_options_are_for_whoever_keeps_the_settings(): void
    {
        $this->signIn(['delivery.view']);
        $this->getJson($this->api('carriers/carrier-a/options/sender'))->assertForbidden();

        $reader = $this->signIn(['delivery.view']);
        $this->agent('delivery_carriers_options', ['carrier' => FakeCarrier::KEY, 'field' => 'sender'], $reader)
            // A tool behind a permission the administrator lacks is not offered at all.
            ->assertHasErrors(['Tool [delivery_carriers_options] not found.']);
    }

    #[Test]
    public function an_agent_asks_and_saves_as_the_panel_does(): void
    {
        $listed = $this->use('delivery_carriers_options', ['carrier' => FakeCarrier::KEY, 'field' => 'contact', 'values' => ['sender' => 'sender-a']]);
        $this->assertSame(['contact-a1', 'contact-a2'], array_column($listed['options'], 'value'));
        $this->assertSame('+800 1234 5678', $listed['options'][0]['hint']);

        $this->agent('delivery_carriers_options', ['carrier' => FakeCarrier::KEY, 'field' => 'contact'])
            ->assertHasErrors(['Choose first: Sender']);

        // A dry run checks with the carrier too, and writes nothing.
        $this->agent('delivery_carriers_update', ['carrier' => FakeCarrier::KEY, 'settings' => ['sender' => 'sender-a', 'contact' => 'contact-b1'], 'dry_run' => true])
            ->assertHasErrors(['contact: Choose from the list.']);
        $dry = $this->use('delivery_carriers_update', ['carrier' => FakeCarrier::KEY, 'settings' => ['sender' => 'sender-a', 'contact' => 'contact-a1'], 'dry_run' => true]);
        $this->assertSame('Contact A1', $dry['carrier']['settings']['_labels']['contact']);
        $this->assertNull(DeliveryCarrier::query()->find(FakeCarrier::KEY)?->settings);

        $saved = $this->use('delivery_carriers_update', ['carrier' => FakeCarrier::KEY, 'settings' => ['sender' => 'sender-a', 'contact' => 'contact-a1']]);
        $this->assertSame(['sender' => 'Sender A', 'contact' => 'Contact A1'], $saved['carrier']['settings']['_labels']);

        $this->carrier->failOptions();
        $this->agent('delivery_carriers_options', ['carrier' => FakeCarrier::KEY, 'field' => 'sender', 'fresh' => true])
            ->assertHasErrors(['Could not get the list from the carrier\'s account: The carrier did not answer'])
            ->assertDontSee('secret-123');
    }

    /** «Clear» of the key sends the settings on screen too: unchanged, they are not asked again. */
    #[Test]
    public function the_key_is_cleared_with_the_senders_settings_kept(): void
    {
        $this->signIn();
        $values = ['sender' => 'sender-a', 'contact' => 'contact-a2', 'sender_city' => 'L2', 'sender_point' => 'P8', 'weight' => 1.5];
        $this->putJson($this->api('carriers/carrier-a'), ['settings' => $values])->assertOk();

        $this->putJson($this->api('carriers/carrier-a'), ['settings' => $values, 'credentials' => [], 'clear' => ['api_key']])
            ->assertOk()
            ->assertJsonPath('data.credentials.0.set', false);

        $this->assertNull(DeliveryCarrier::query()->find(FakeCarrier::KEY)?->credentials);
        $this->assertSame('sender-a', DeliveryCarrier::query()->find(FakeCarrier::KEY)?->settings['sender']);
    }
}
