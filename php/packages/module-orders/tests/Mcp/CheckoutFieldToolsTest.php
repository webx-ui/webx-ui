<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Orders\Checkout\FieldPlaces;
use WebxUi\Orders\FieldRefused;
use WebxUi\Orders\Models\OrderField;

/**
 * The extra fields of the checkout for an agent (WEBX_UI_ORDER_FIELDS.md §8.1, §8.3): the list is
 * the panel's, a dry run answers the panel dialog's lines and writes nothing, every refusal of §7.3
 * is a `ToolFailure` that says what to do next, the writes are `orders.checkout.settings` and the
 * list is for `orders.view` too; `orders://checkout` reads the places, the types and the codes.
 */
final class CheckoutFieldToolsTest extends McpTestCase
{
    private ?CmsUser $keeper = null;

    private function keeper(): CmsUser
    {
        return $this->keeper ??= $this->manager(['orders.checkout.settings']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function save(array $arguments): array
    {
        return $this->ask('orders_checkout_fields_save', $arguments, $this->keeper());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $errors
     */
    private function refused(string $tool, array $arguments, array $errors): void
    {
        $before = OrderField::query()->orderBy('id')->get()->map->getAttributes()->all();

        $this->agent($tool, $arguments, $this->keeper())->assertHasErrors($errors);
        $this->assertSame($before, OrderField::query()->orderBy('id')->get()->map->getAttributes()->all(), "{$tool} wrote nothing");
    }

    /** The demo of §10: a checkbox in the contacts with two fields behind it, a list before the comment. */
    private function demo(): void
    {
        $this->save(['key' => 'for_company', 'type' => 'checkbox', 'label' => ['en' => 'Buying for a company'], 'place' => FieldPlaces::CONTACTS]);
        $this->save(['key' => 'company_name', 'type' => 'text', 'label' => ['en' => 'Company name'], 'parent' => 'for_company']);
        $this->save(['key' => 'company_code', 'type' => 'text', 'label' => ['en' => 'Company code'], 'parent' => 'for_company']);
        $this->save(['key' => 'delivery_time', 'type' => 'select', 'label' => ['en' => 'Convenient delivery time'], 'options' => [
            ['value' => 'morning', 'label' => ['en' => '9:00–12:00']],
            ['value' => 'evening', 'label' => ['en' => '18:00–21:00']],
        ]]);
    }

    #[Test]
    public function the_list_is_the_panel_s_and_the_reader_sees_it(): void
    {
        $this->demo();
        $this->signIn(['orders.checkout.settings']);

        $panel = $this->getJson($this->api('checkout-fields'))->assertOk()->json('data');
        $agent = $this->ask('orders_checkout_fields_list', [], $this->manager(['orders.view']));

        $this->assertSame($panel, $agent);
        $this->assertSame(['for_company', 'company_name', 'company_code', 'delivery_time'], array_column($agent['fields'], 'key'));
        $this->assertSame([null, 'for_company', 'for_company', null], array_column($agent['fields'], 'parent'));
        $this->assertSame([FieldPlaces::CONTACTS, FieldPlaces::BEFORE_COMMENT, FieldPlaces::AFTER_COMMENT], array_column($agent['places'], 'key'));

    }

    #[Test]
    public function reading_is_not_writing(): void
    {
        $this->demo();

        // The writes are the tab's own permission, not the statuses'.
        $reader = $this->manager(['orders.view', 'orders.statuses']);
        $this->agent('orders_checkout_fields_save', ['key' => 'gift', 'type' => 'checkbox', 'label' => ['en' => 'Gift'], 'dry_run' => true], $reader)
            ->assertHasErrors(['[orders.checkout.settings]']);
        $this->agent('orders_checkout_fields_delete', ['key' => 'delivery_time'], $reader)->assertHasErrors(['[orders.checkout.settings]']);
        $this->agent('orders_checkout_fields_reorder', ['keys' => ['delivery_time']], $reader)->assertHasErrors(['[orders.checkout.settings]']);
        $this->assertSame(4, OrderField::query()->count());
    }

    #[Test]
    public function a_dry_run_answers_the_panel_s_lines_and_writes_nothing(): void
    {
        $this->demo();
        $this->signIn(['orders.checkout.settings']);
        $id = (int) OrderField::query()->where('key', 'for_company')->value('id');

        // Off, required and moved: the same lines as the panel's dialog, the fields behind it named.
        $change = ['active' => false, 'required' => true, 'place' => FieldPlaces::BEFORE_COMMENT];
        $panel = $this->putJson($this->api('checkout-fields/'.$id), [...$change, 'dry_run' => true])->assertOk()->json('data');
        $before = OrderField::query()->orderBy('id')->get()->map->getAttributes()->all();
        $agent = $this->save(['key' => 'for_company', ...$change, 'dry_run' => true]);

        foreach ($panel as $key => $value) {
            $this->assertSame($value, $agent[$key], "orders_checkout_fields_save says {$key} as the panel does");
        }

        $this->assertSame(['required_on', 'hidden', 'moved'], $agent['effects']);
        $this->assertContains('The fields behind it move with it: «Company name», «Company code».', $agent['lines']);
        $this->assertSame([true, false, false, null], [$agent['dry_run'], $agent['created'], $agent['orders_touched'], $agent['definition']]);
        $this->assertSame($before, OrderField::query()->orderBy('id')->get()->map->getAttributes()->all());

        // For real: the checkbox moves, and the fields behind it with it.
        $moved = $this->save(['key' => 'for_company', 'place' => FieldPlaces::BEFORE_COMMENT]);
        $this->assertFalse($moved['dry_run']);
        $this->assertSame(FieldPlaces::BEFORE_COMMENT, $moved['definition']['place']);
        $this->assertSame([FieldPlaces::BEFORE_COMMENT], OrderField::query()->whereIn('key', ['company_name', 'company_code'])->distinct()->pluck('place')->all());

        // A new one, its key made of the label's language; the dry run keeps nothing.
        $dry = $this->save(['key' => 'Время доставки', 'type' => 'text', 'label' => ['en' => 'Time'], 'dry_run' => true]);
        $key = (string) OrderField::codeFor('key', 'Время доставки');
        $this->assertSame(['created', 'vremia_dostavki', true], [$dry['effects'][0], $key, $dry['created']]);
        $this->assertSame($key, $dry['key']);
        $this->assertFalse(OrderField::query()->where('key', $key)->exists());

        // Options taken away: named, and the orders keep theirs.
        $dry = $this->save(['key' => 'delivery_time', 'options' => [['value' => 'evening', 'label' => ['en' => '18:00–21:00']]], 'dry_run' => true]);
        $this->assertSame(['options_removed'], $dry['effects']);
        $this->assertSame(['Options taken away: «9:00–12:00».', 'Orders already placed keep what their buyers answered.'], $dry['lines']);
    }

    #[Test]
    public function every_refusal_says_what_to_do_next_and_writes_nothing(): void
    {
        $this->demo();

        foreach ([true, false] as $dryRun) {
            $dry = ['dry_run' => $dryRun];

            // The type never changes.
            $this->refused('orders_checkout_fields_save', ['key' => 'company_code', 'type' => 'select', ...$dry], ['`type`: ', 'delete „company_code“ and add another']);
            // A checkbox with fields behind it is not deleted, and they are named.
            $this->refused('orders_checkout_fields_delete', ['key' => 'for_company', ...$dry], ['The field „for_company“ has fields shown behind it: company_name, company_code']);
            // A parent that is not there.
            $this->refused('orders_checkout_fields_save', ['key' => 'vat', 'type' => 'text', 'label' => ['en' => 'VAT'], 'parent' => 'for_firm', ...$dry], ['Unknown parent „for_firm“: orders_checkout_fields_list lists the fields.']);
            // A parent that is not a checkbox, or of another place.
            $this->refused('orders_checkout_fields_save', ['key' => 'vat', 'type' => 'text', 'label' => ['en' => 'VAT'], 'parent' => 'delivery_time', ...$dry], ['`parent`: ']);
            $this->refused('orders_checkout_fields_save', ['key' => 'vat', 'type' => 'text', 'label' => ['en' => 'VAT'], 'parent' => 'for_company', 'place' => FieldPlaces::AFTER_COMMENT, ...$dry], ['`parent`: ']);
            // A place the registry does not know: the places are listed.
            $this->refused('orders_checkout_fields_save', ['key' => 'vat', 'type' => 'text', 'label' => ['en' => 'VAT'], 'place' => 'delivery-after', ...$dry], ['`place`: ', 'The places: contacts, extra-before-comment, extra-after-comment.']);
            // No label in the main language; a list without options; options on a line.
            $this->refused('orders_checkout_fields_save', ['key' => 'vat', 'type' => 'text', 'label' => ['ru' => 'НДС'], ...$dry], ['`label`: ']);
            $this->refused('orders_checkout_fields_save', ['key' => 'vat', 'type' => 'select', 'label' => ['en' => 'VAT'], ...$dry], ['`options`: ']);
            $this->refused('orders_checkout_fields_save', ['key' => 'company_name', 'options' => [['value' => 'a', 'label' => ['en' => 'A']]], ...$dry], ['`options`: ']);
            // Two options of one key.
            $this->refused('orders_checkout_fields_save', ['key' => 'delivery_time', 'options' => [['value' => 'a', 'label' => ['en' => 'A']], ['value' => 'a', 'label' => ['en' => 'B']]], ...$dry], ['`options`: ']);
            // A key with nothing to make one of.
            $this->refused('orders_checkout_fields_save', ['key' => '!!!', 'type' => 'text', 'label' => ['en' => 'VAT'], ...$dry], ['`key`: ']);
            // An order that leaves one out, names one twice and one that is not there.
            $this->refused('orders_checkout_fields_reorder', ['keys' => ['delivery_time', 'for_company', 'for_company', 'vat'], ...$dry], [
                '`keys`: ', 'Left out: company_name, company_code.', 'Not a field: vat.', 'Named twice: for_company.',
            ]);
            // A field that is not there.
            $this->refused('orders_checkout_fields_delete', ['key' => 'compny', ...$dry], ['No field „compny“: orders_checkout_fields_list lists the fields.']);
            // The shape of an argument: the validator's words under the input.
            $this->refused('orders_checkout_fields_save', ['key' => 'vat', 'type' => 'text', 'label' => ['en' => str_repeat('a', 191)], ...$dry], ['Not accepted — label.en']);
        }

        // Thirty at most.
        foreach (range(5, OrderField::MAX_FIELDS) as $n) {
            OrderField::query()->create(['key' => "f{$n}", 'type' => 'text', 'place' => FieldPlaces::DEFAULT, 'label' => ['en' => "F{$n}"], 'position' => $n]);
        }

        $this->refused('orders_checkout_fields_save', ['key' => 'vat', 'type' => 'text', 'label' => ['en' => 'VAT']], ['`key`: ']);
        $this->assertCount(11, FieldRefused::CODES);
    }

    #[Test]
    public function a_field_is_deleted_and_the_list_reordered(): void
    {
        $this->demo();

        $dry = $this->ask('orders_checkout_fields_delete', ['key' => 'delivery_time', 'dry_run' => true], $this->keeper());
        $this->assertSame(['deleted'], $dry['effects']);
        $this->assertSame(['The field leaves the checkout.', 'Orders already placed keep what their buyers answered.'], $dry['lines']);
        $this->assertFalse($dry['orders_touched']);
        $this->assertTrue(OrderField::query()->where('key', 'delivery_time')->exists());

        $this->ask('orders_checkout_fields_delete', ['key' => 'delivery_time'], $this->keeper());
        $this->assertFalse(OrderField::query()->where('key', 'delivery_time')->exists());

        $keys = ['for_company', 'company_code', 'company_name'];
        $dry = $this->ask('orders_checkout_fields_reorder', ['keys' => $keys, 'dry_run' => true], $this->keeper());
        $this->assertSame([['reordered'], null], [$dry['effects'], $dry['fields']]);
        $this->assertSame(['for_company', 'company_name', 'company_code'], OrderField::query()->orderBy('position')->orderBy('id')->pluck('key')->all());

        $done = $this->ask('orders_checkout_fields_reorder', ['keys' => $keys], $this->keeper());
        $this->assertSame($keys, $done['fields']);
    }

    #[Test]
    public function the_resource_reads_the_places_the_types_and_the_codes(): void
    {
        $this->demo();
        $this->app->make(FieldPlaces::class)->register('payment-after', step: 'payment', label: 'After the payment');

        $catalog = ($this->resource('orders://checkout')->handler)();
        $fields = $catalog['fields'];

        $this->assertSame([FieldPlaces::CONTACTS, FieldPlaces::BEFORE_COMMENT, FieldPlaces::AFTER_COMMENT, 'payment-after'], array_column($fields['places'], 'key'));
        $this->assertSame(['contacts', 'comment', 'comment', 'payment'], array_column($fields['places'], 'step'));
        $this->assertSame([false, true, false, false], array_column($fields['places'], 'default'));
        $this->assertSame(OrderField::TYPES, array_keys($fields['types']));
        $this->assertSame(['fields.required', 'fields.invalid', 'fields.option_gone'], array_keys($fields['error_codes']));
        $this->assertSame(FieldRefused::CODES, $fields['refusals']);
        $this->assertSame(['for_company', 'company_name', 'company_code', 'delivery_time'], array_column($fields['definitions'], 'key'));
        $this->assertSame(OrderField::MAX_FIELDS, $fields['limits']['fields']);

        // The step says the places in its form.
        $steps = array_column($catalog['steps'], 'field_places', 'key');
        $this->assertSame([FieldPlaces::CONTACTS], $steps['contacts']);
        $this->assertSame([FieldPlaces::BEFORE_COMMENT, FieldPlaces::AFTER_COMMENT], $steps['comment']);
    }
}
