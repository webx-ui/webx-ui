<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Checkout\FieldPlaces;
use WebxUi\Orders\Models\OrderField;

/**
 * The extra fields of the checkout in the panel (WEBX_UI_ORDER_FIELDS.md §7): the list grouped by
 * the places of the registry, a field written, dragged and deleted through its own calls — a dry
 * run says what the buyer will see, a refusal comes back under the input it is about — and only
 * for `orders.checkout.settings`. The site has no settings module here, and the tab keeps the
 * fields' card all the same.
 */
final class CheckoutFieldsTest extends PanelTestCase
{
    private function fields(string $path = ''): string
    {
        return $this->api('checkout-fields'.$path);
    }

    /** @return array<string, mixed> */
    private function checkbox(string $key, string $label, array $with = []): array
    {
        return ['key' => $key, 'type' => 'checkbox', 'label' => ['en' => $label]] + $with;
    }

    private function id(string $key): int
    {
        return (int) OrderField::query()->where('key', $key)->value('id');
    }

    #[Test]
    public function the_list_is_grouped_by_the_places_of_the_registry(): void
    {
        $this->signIn(['orders.checkout.settings']);

        $this->postJson($this->fields(), $this->checkbox('installation', 'Installation and setup needed'))
            ->assertCreated()
            ->assertJsonPath('data.fields.0.key', 'installation')
            ->assertJsonPath('data.fields.0.place', FieldPlaces::DEFAULT)
            ->assertJsonPath('data.fields.0.place_known', true)
            ->assertJsonPath('data.fields.0.name', 'Installation and setup needed');

        $board = $this->getJson($this->fields())->assertOk()->json('data');
        $this->assertSame([FieldPlaces::CONTACTS, FieldPlaces::BEFORE_COMMENT, FieldPlaces::AFTER_COMMENT], array_column($board['places'], 'key'));
        $this->assertSame('Contacts', $board['places'][0]['label']);
        $this->assertSame(['fields' => 30, 'options' => 50, 'label' => 190, 'hint' => 500], $board['limits']);

        // A place the registry no longer knows: listed, marked, the buyer is not asked it.
        OrderField::query()->whereKey($this->id('installation'))->update(['place' => 'delivery-after']);
        $this->app->make(\WebxUi\Cache\CacheGroups::class)->bump('order-fields');
        $this->getJson($this->fields())->assertOk()->assertJsonPath('data.fields.0.place_known', false);

        // The tab «Checkout» keeps the fields' card without the settings module, and nothing else.
        $root = $this->getJson('/api/cms/screens/orders.settings')->assertOk()->json('data.root');
        $this->assertSame(['checkout-tab'], array_column($root[0]['children'], 'id'));
        $this->assertSame(['checkout-extra-card'], array_column($root[0]['children'][0]['children'], 'id'));
        $this->assertSame('wx-orders-checkout-fields', $root[0]['children'][0]['children'][0]['children'][0]['type']);
    }

    #[Test]
    public function a_dry_run_says_what_the_buyer_will_see_and_writes_nothing(): void
    {
        $this->signIn(['orders.checkout.settings']);

        $this->postJson($this->fields(), $this->checkbox('installation', 'Installation', ['dry_run' => true]))
            ->assertOk()
            ->assertJsonPath('data.refusal', null)
            ->assertJsonPath('data.effects', ['created'])
            ->assertJsonPath('data.lines', ['Buyers will see the field at the checkout.']);
        $this->assertSame(0, OrderField::query()->count());

        $this->postJson($this->fields(), $this->checkbox('for_company', 'Buying for a company', ['place' => FieldPlaces::CONTACTS]))->assertCreated();
        $this->postJson($this->fields(), ['key' => 'company_name', 'type' => 'text', 'label' => ['en' => 'Company name'], 'parent' => 'for_company'])
            ->assertCreated()
            ->assertJsonPath('data.fields.1.parent', 'for_company')
            ->assertJsonPath('data.fields.1.place', FieldPlaces::CONTACTS);

        // Off, the checkbox takes the field behind it along; moved, it takes it along too.
        $this->putJson($this->fields('/'.$this->id('for_company')), ['active' => false, 'required' => true, 'dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.effects', ['required_on', 'hidden'])
            ->assertJsonPath('data.dependents', ['company_name'])
            ->assertJsonPath('data.lines', [
                'Buyers will not place an order without it.',
                'Buyers will no longer see the field.',
                'Nor the fields behind it: «Company name».',
                'Orders already placed keep what their buyers answered.',
            ]);
        $this->putJson($this->fields('/'.$this->id('for_company')), ['place' => FieldPlaces::AFTER_COMMENT, 'dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.lines', [
                'The field moves to «Additional, after the comment».',
                'The fields behind it move with it: «Company name».',
            ]);
        $this->assertSame(FieldPlaces::CONTACTS, OrderField::query()->findOrFail($this->id('for_company'))->place);

        $this->putJson($this->fields('/'.$this->id('for_company')), ['place' => FieldPlaces::AFTER_COMMENT])
            ->assertOk()
            ->assertJsonPath('data.fields.1.place', FieldPlaces::AFTER_COMMENT);
    }

    #[Test]
    public function a_refusal_comes_back_under_the_input_it_is_about(): void
    {
        $this->signIn(['orders.checkout.settings']);

        $this->postJson($this->fields(), $this->checkbox('installation', 'Installation'))->assertCreated();

        $this->postJson($this->fields(), $this->checkbox('installation', 'Again'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.key.0', 'Another field has this key.');
        $this->postJson($this->fields(), ['key' => 'delivery_time', 'type' => 'select', 'label' => ['en' => 'Time']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.options.0', 'A list needs options, and only a list has them.');
        $this->postJson($this->fields(), ['key' => 'note', 'type' => 'text', 'label' => ['en' => 'Note'], 'place' => 'nowhere'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('place');
        $this->putJson($this->fields('/'.$this->id('installation')), ['type' => 'text'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
        // A dry run answers the refusal instead of throwing it, with the input named.
        $this->postJson($this->fields(), ['key' => 'note', 'type' => 'text', 'label' => ['ru' => 'Заметка'], 'dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.refusal', 'no_label')
            ->assertJsonPath('data.field', 'label')
            ->assertJsonPath('data.lines', []);
        // The length of a label is a mistake of the input, as the service says it.
        $this->postJson($this->fields(), ['key' => 'note', 'type' => 'text', 'label' => ['en' => str_repeat('a', 191)]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('label.en');

        $this->assertSame(1, OrderField::query()->count());
    }

    #[Test]
    public function a_checkbox_with_fields_behind_it_is_not_deleted(): void
    {
        $this->signIn(['orders.checkout.settings']);

        $this->postJson($this->fields(), $this->checkbox('for_company', 'Buying for a company'))->assertCreated();
        $this->postJson($this->fields(), ['key' => 'company_code', 'type' => 'text', 'label' => ['en' => 'Company code'], 'parent' => 'for_company'])->assertCreated();

        $this->deleteJson($this->fields('/'.$this->id('for_company')), ['dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.refusal', 'has_dependents')
            ->assertJsonPath('data.message', 'Fields are shown behind this checkbox: «Company code». Delete them or show them always first.');
        $this->deleteJson($this->fields('/'.$this->id('for_company')))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');

        $this->deleteJson($this->fields('/'.$this->id('company_code')), ['dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.lines', ['The field leaves the checkout.', 'Orders already placed keep what their buyers answered.']);
        $this->deleteJson($this->fields('/'.$this->id('company_code')))->assertOk()->assertJsonCount(1, 'data.fields');
        $this->deleteJson($this->fields('/'.$this->id('for_company')))->assertOk()->assertJsonCount(0, 'data.fields');
    }

    #[Test]
    public function the_list_is_dragged_whole(): void
    {
        $this->signIn(['orders.checkout.settings']);

        foreach (['installation', 'no_callback', 'gift_wrap'] as $key) {
            $this->postJson($this->fields(), $this->checkbox($key, ucfirst($key)))->assertCreated();
        }

        $this->putJson($this->fields('/arrange'), ['keys' => ['gift_wrap', 'installation', 'no_callback']])
            ->assertOk()
            ->assertJsonPath('data.fields.0.key', 'gift_wrap')
            ->assertJsonPath('data.fields.2.key', 'no_callback');

        $this->putJson($this->fields('/arrange'), ['keys' => ['gift_wrap', 'installation']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.keys.0', 'The list changed meanwhile: open the page again.');
    }

    #[Test]
    public function only_the_checkout_settings_open_the_fields(): void
    {
        $this->postJson($this->fields(), $this->checkbox('installation', 'Installation'))->assertUnauthorized();

        foreach ([['orders.statuses'], ['orders.view', 'orders.manage']] as $permissions) {
            $this->signIn($permissions);
            $this->getJson($this->fields())->assertForbidden();
            $this->postJson($this->fields(), $this->checkbox('installation', 'Installation'))->assertForbidden();
            $this->putJson($this->fields('/arrange'), ['keys' => []])->assertForbidden();
        }

        $this->assertSame(0, OrderField::query()->count());
    }
}
