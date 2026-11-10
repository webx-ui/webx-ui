<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Foundation\Application;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Cart\AddResult;
use WebxUi\Orders\Checkout\FieldPlaces;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Checkout\Steps\Step;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderField;
use WebxUi\Orders\OrderFields;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * The extra fields on the way to an order (WEBX_UI_ORDER_FIELDS.md §3–§4): asked in the three
 * places, kept in the draft by the step of their place, checked against the definitions as they
 * are at the confirmation, and frozen into `orders.fields` in the order of the page.
 */
final class CheckoutExtraFieldsTest extends CheckoutTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), SettingsServiceProvider::class];
    }

    #[Test]
    public function the_three_places_are_asked_and_the_snapshot_keeps_the_order_of_the_page(): void
    {
        $this->demo();
        $cart = $this->cart();

        $page = (string) $this->visit($cart, '/checkout')->assertOk()
            ->assertSeeInOrder([
                'name="customer[first_name]"',
                'webx-orders-checkout__fields webx-orders-checkout__fields--contacts',
                'webx-orders-checkout__field-group',
                'name="fields[for_company]"',
                'webx-orders-checkout__field-when',
                'webx-orders-checkout__field webx-orders-checkout__field--company_name',
                'id="checkout-step-comment"', '2. Additional',
                'webx-orders-checkout__fields--extra-before-comment',
                'name="fields[installation]"',
                'name="fields[delivery_time]"', '18:00–21:00',
                'name="comment"',
                'webx-orders-checkout__fields--extra-after-comment',
                'name="fields[no_callback]"',
            ], false)
            ->getContent();

        // Behind a checkbox, required or not — no `required` of the browser, no star (decision 3).
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="fields\[company_name\]"[^>]*required/', $page);
        // Shown always and required — the browser's `required`, as the contacts.
        $this->assertMatchesRegularExpression('/<select[^>]*name="fields\[delivery_time\]"[^>]*required/', $page);

        $this->visit($cart, '/checkout/steps/contacts', [
            'customer' => ['first_name' => 'Buyer', 'phone' => '+800 1234 5678', 'email' => 'buyer@example.test'],
            'fields' => ['for_company' => '1', 'company_name' => '  Company A  '],
        ])->assertRedirect();
        $this->visit($cart, '/checkout/steps/comment', [
            'comment' => 'Ring twice',
            'fields' => ['installation' => '1', 'delivery_time' => 'evening', 'no_callback' => '1'],
        ])->assertRedirect();

        $this->assertSame(
            ['for_company' => true, 'company_name' => 'Company A', 'installation' => true, 'delivery_time' => 'evening', 'no_callback' => true],
            CheckoutDraft::query()->sole()->steps['fields'] ?? null,
        );
        $this->visit($cart, '/checkout')->assertOk()
            ->assertSee('Installation and setup needed, Convenient delivery time, Ring twice, Do not call...');

        $this->confirm($cart)->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame([
            ['key' => 'for_company', 'type' => 'checkbox', 'label' => 'Buying for a company', 'value' => true, 'text' => 'Yes'],
            ['key' => 'company_name', 'type' => 'text', 'label' => 'Company name', 'value' => 'Company A', 'text' => 'Company A'],
            ['key' => 'installation', 'type' => 'checkbox', 'label' => 'Installation and setup needed', 'value' => true, 'text' => 'Yes'],
            ['key' => 'delivery_time', 'type' => 'select', 'label' => 'Convenient delivery time', 'value' => 'evening', 'text' => '18:00–21:00'],
            ['key' => 'no_callback', 'type' => 'checkbox', 'label' => 'Do not call back', 'value' => true, 'text' => 'Yes'],
        ], $order->fields);
        $this->assertSame('Ring twice', $order->comment);
        $this->assertSame($order->fields, OrderData::of($order)->fields);
    }

    #[Test]
    public function a_field_behind_a_checkbox_counts_only_while_it_is_ticked(): void
    {
        $this->demo();
        $cart = $this->cart();
        $contacts = ['first_name' => 'Buyer', 'phone' => '+800 1234 5678', 'email' => 'buyer@example.test'];

        // Ticked and empty — required, under its field.
        $this->visit($cart, '/checkout/steps/contacts', ['customer' => $contacts, 'fields' => ['for_company' => '1']], json: true)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.company_name' => 'Fill in «Company name».']);

        // Not ticked — what came for the field behind it is neither checked nor kept.
        $this->visit($cart, '/checkout/steps/contacts', ['customer' => $contacts, 'fields' => ['company_name' => str_repeat('x', 500)]])->assertRedirect();
        $this->assertNull(CheckoutDraft::query()->sole()->steps);

        // Ticked, filled, then unticked — the answer behind it goes.
        $this->visit($cart, '/checkout/steps/contacts', ['customer' => $contacts, 'fields' => ['for_company' => '1', 'company_name' => 'Company A']])->assertRedirect();
        $this->visit($cart, '/checkout/steps/contacts', ['customer' => $contacts, 'fields' => ['company_name' => 'Company A']])->assertRedirect();
        $this->assertNull(CheckoutDraft::query()->sole()->steps);

        // A checkbox switched off hides the fields behind it as well.
        $this->visit($cart, '/checkout/steps/contacts', ['customer' => $contacts, 'fields' => ['for_company' => '1', 'company_name' => 'Company A']])->assertRedirect();
        $this->switchOff('for_company');
        $this->visit($cart, '/checkout')->assertOk()->assertDontSee('fields[company_name]', false);
        $this->visit($cart, '/checkout/steps/comment', ['fields' => ['delivery_time' => 'evening']])->assertRedirect();
        $this->confirm($cart)->assertRedirect();
        $this->assertSame(['delivery_time'], array_column(Order::query()->sole()->fields ?? [], 'key'));
    }

    #[Test]
    public function a_step_keeps_the_answers_of_its_places_alone(): void
    {
        $this->demo();
        $cart = $this->cart();

        $this->visit($cart, '/checkout/steps/comment', ['fields' => ['installation' => '1', 'delivery_time' => 'morning']])->assertRedirect();
        $draft = CheckoutDraft::query()->sole();
        $draft->steps = [...($draft->steps ?? []), 'project-step' => ['gift' => true]];
        $draft->save();

        $this->visit($cart, '/checkout/steps/contacts', [
            'customer' => ['first_name' => 'Buyer', 'phone' => '+800 1234 5678', 'email' => 'buyer@example.test'],
            'fields' => ['for_company' => '1', 'company_name' => 'Company A', 'installation' => '', 'delivery_time' => 'evening'],
        ])->assertRedirect();

        $this->assertSame(
            ['fields' => ['installation' => true, 'delivery_time' => 'morning', 'for_company' => true, 'company_name' => 'Company A'], 'project-step' => ['gift' => true]],
            CheckoutDraft::query()->sole()->steps,
        );

        // And the other way round: the block «Additional» leaves the contacts' fields as they were.
        $this->visit($cart, '/checkout/steps/comment', ['fields' => ['delivery_time' => 'evening']])->assertRedirect();
        $this->assertSame(
            ['for_company' => true, 'company_name' => 'Company A', 'delivery_time' => 'evening'],
            CheckoutDraft::query()->sole()->steps['fields'] ?? null,
        );
    }

    #[Test]
    public function the_answers_are_checked_as_the_field_says(): void
    {
        $this->demo();
        $cart = $this->cart();

        $this->visit($cart, '/checkout/steps/comment', [
            'comment' => str_repeat('x', 2001),
            'fields' => ['delivery_time' => 'midnight', 'installation' => ['1']],
        ], json: true)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'comment',
                'fields.delivery_time' => 'The option chosen in «Convenient delivery time» is no longer offered: choose another.',
                'fields.installation' => 'Check «Installation and setup needed».',
            ]);
        $this->assertSame(0, CheckoutDraft::query()->count());

        $this->write(['key' => 'note', 'type' => 'text', 'label' => ['en' => 'Note']]);
        $this->visit($cart, '/checkout/steps/comment', ['fields' => ['delivery_time' => 'evening', 'note' => str_repeat('n', 191)]], json: true)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.note' => '«Note» is too long: at most 190 characters.'])
            ->assertJsonMissingValidationErrors(['fields.delivery_time']);
    }

    #[Test]
    public function with_the_comment_switched_off_the_block_stays_for_its_fields(): void
    {
        $this->app->make(Settings::class)->save(['orders.checkout.comment' => 'off']);
        $this->write(['key' => 'no_callback', 'type' => 'checkbox', 'label' => ['en' => 'Do not call back'], 'place' => FieldPlaces::AFTER_COMMENT]);
        $cart = $this->cart();

        $this->visit($cart, '/checkout')->assertOk()
            ->assertSee('2. Additional')
            ->assertSee('name="fields[no_callback]"', false)
            ->assertDontSee('name="comment"', false);

        $this->fill($cart);
        $this->visit($cart, '/checkout/steps/comment', ['comment' => 'Kept out', 'fields' => ['no_callback' => '1']])->assertRedirect();
        $this->assertNull(CheckoutDraft::query()->sole()->comment);

        $this->confirm($cart)->assertRedirect();
        $order = Order::query()->sole();
        $this->assertNull($order->comment);
        $this->assertSame(['no_callback'], array_column($order->fields ?? [], 'key'));
    }

    #[Test]
    public function with_neither_a_comment_nor_a_field_there_is_no_block(): void
    {
        $this->app->make(Settings::class)->save(['orders.checkout.comment' => 'off']);
        // A field switched off is no field of the page.
        $field = $this->write(['key' => 'installation', 'type' => 'checkbox', 'label' => ['en' => 'Installation'], 'active' => false]);
        $cart = $this->cart();

        $this->visit($cart, '/checkout')->assertOk()->assertDontSee('checkout-step-comment', false);
        $this->visit($cart, '/checkout/steps/comment', ['fields' => ['installation' => '1']])->assertNotFound();

        // With the comment and no field it is «Comment», as it always was.
        $this->app->make(Settings::class)->save(['orders.checkout.comment' => 'on']);
        $this->visit($cart, '/checkout')->assertOk()->assertSee('2. Comment')->assertDontSee('webx-orders-checkout__fields', false);

        $this->app->make(OrderFields::class)->write(['active' => true], $field);
        $this->visit($cart, '/checkout')->assertOk()->assertSee('2. Additional');
    }

    #[Test]
    public function a_place_whose_step_is_not_shown_or_that_is_gone_asks_nothing(): void
    {
        // A place in a step that is not on this site (no delivery), and a place no module registers.
        $this->app->make(FieldPlaces::class)->register('delivery-after', step: 'delivery', label: 'After the delivery');
        $this->write(['key' => 'floor', 'type' => 'text', 'label' => ['en' => 'Floor'], 'required' => true, 'place' => 'delivery-after']);
        OrderField::query()->create(['key' => 'gone', 'type' => 'text', 'label' => ['en' => 'Gone'], 'place' => 'removed-place', 'required' => true, 'position' => 9]);
        $cart = $this->cart();

        $this->visit($cart, '/checkout')->assertOk()
            ->assertDontSee('fields[floor]', false)
            ->assertDontSee('fields[gone]', false)
            ->assertDontSee('webx-orders-checkout__fields', false);

        $this->fill($cart);
        $this->confirm($cart)->assertRedirect();
        $this->assertNull(Order::query()->sole()->fields);
    }

    #[Test]
    public function the_confirmation_reads_the_definitions_as_they_are_now(): void
    {
        $this->demo();
        $cart = $this->cart();
        $this->fill($cart);
        $this->visit($cart, '/checkout/steps/comment', ['fields' => ['installation' => '1', 'delivery_time' => 'evening']])->assertRedirect();

        // The option chosen is taken away while the buyer thinks.
        $this->app->make(OrderFields::class)->write(['options' => [['value' => 'morning', 'label' => ['en' => '9:00–12:00']]]], $this->field('delivery_time'));
        $this->confirm($cart, json: true)->assertStatus(422)
            ->assertJsonValidationErrors(['fields.delivery_time' => 'The option chosen in «Convenient delivery time» is no longer offered: choose another.']);

        // Chosen again — and a field made required meanwhile is wanted too.
        $this->visit($cart, '/checkout/steps/comment', ['fields' => ['installation' => '1', 'delivery_time' => 'morning']])->assertRedirect();
        $this->app->make(OrderFields::class)->write(['required' => true], $this->field('no_callback'));
        $this->confirm($cart, json: true)->assertStatus(422)
            ->assertJsonValidationErrors(['fields.no_callback' => 'Fill in «Do not call back».']);
        $this->assertSame(0, Order::query()->count());

        // A field deleted with an answer in the draft is neither an error nor a row of the order.
        $this->app->make(OrderFields::class)->remove($this->field('no_callback'));
        $this->app->make(OrderFields::class)->write(['label' => ['en' => 'Installation, please']], $this->field('installation'));
        $this->confirm($cart)->assertRedirect();

        $this->assertSame([
            ['key' => 'installation', 'type' => 'checkbox', 'label' => 'Installation, please', 'value' => true, 'text' => 'Yes'],
            ['key' => 'delivery_time', 'type' => 'select', 'label' => 'Convenient delivery time', 'value' => 'morning', 'text' => '9:00–12:00'],
        ], Order::query()->sole()->fields);
    }

    #[Test]
    public function no_step_may_take_the_key_of_the_answers(): void
    {
        $step = $this->createStub(Step::class);
        $step->method('key')->willReturn('fields');

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(CheckoutSteps::class)->register($step, 500);
    }

    #[Test]
    public function an_order_from_code_keeps_rows_of_the_right_shape_only(): void
    {
        $rows = [['key' => 'gift_wrap', 'type' => 'checkbox', 'label' => 'Gift wrap', 'value' => true, 'text' => 'Yes']];

        $this->assertSame($rows, $this->place($this->cart(), ['fields' => $rows])->fields);
        $this->assertNull($this->place($this->cart())->fields);

        foreach ([
            [['key' => 'Bad-Key', 'type' => 'text', 'label' => 'Bad', 'value' => 'x', 'text' => 'x']],
            [['key' => 'note', 'type' => 'date', 'label' => 'Note', 'value' => 'x', 'text' => 'x']],
            [['key' => 'note', 'type' => 'text', 'label' => 'Note', 'value' => false, 'text' => 'x']],
            [...$rows, ...$rows],
        ] as $wrong) {
            try {
                $this->place($this->cart(), ['fields' => $wrong]);
                $this->fail('A row of another shape went into an order: '.json_encode($wrong));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(2, Order::query()->count());
    }

    /**
     * The fields of the demo (§10, stage 8): a checkbox with two fields behind it in the contacts,
     * a checkbox and a required list before the comment, a checkbox after it.
     */
    private function demo(): void
    {
        $this->write(['key' => 'for_company', 'type' => 'checkbox', 'label' => ['en' => 'Buying for a company'], 'place' => FieldPlaces::CONTACTS]);
        $this->write(['key' => 'company_name', 'type' => 'text', 'label' => ['en' => 'Company name'], 'required' => true, 'parent' => 'for_company']);
        $this->write(['key' => 'company_code', 'type' => 'text', 'label' => ['en' => 'Company code'], 'parent' => 'for_company']);
        $this->write(['key' => 'installation', 'type' => 'checkbox', 'label' => ['en' => 'Installation and setup needed']]);
        $this->write(['key' => 'delivery_time', 'type' => 'select', 'label' => ['en' => 'Convenient delivery time'], 'required' => true, 'options' => [
            ['value' => 'morning', 'label' => ['en' => '9:00–12:00']],
            ['value' => 'evening', 'label' => ['en' => '18:00–21:00']],
        ]]);
        $this->write(['key' => 'no_callback', 'type' => 'checkbox', 'label' => ['en' => 'Do not call back'], 'place' => FieldPlaces::AFTER_COMMENT]);
    }

    /** @param  array<string, mixed>  $input */
    private function write(array $input): OrderField
    {
        return OrderField::query()->findOrFail($this->app->make(OrderFields::class)->write($input)->id);
    }

    private function field(string $key): OrderField
    {
        return OrderField::query()->where('key', $key)->sole();
    }

    private function switchOff(string $key): void
    {
        $this->app->make(OrderFields::class)->write(['active' => false], $this->field($key));
    }

    /** @return TestResponse<Response> */
    private function confirm(AddResult $cart, bool $json = false): TestResponse
    {
        return $this->submit($cart, ['expected_payable' => '4999'], $json);
    }
}
