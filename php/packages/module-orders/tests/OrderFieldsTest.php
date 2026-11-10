<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Orders\Checkout\FieldPlaces;
use WebxUi\Orders\FieldPlan;
use WebxUi\Orders\FieldRefused;
use WebxUi\Orders\Models\OrderField;
use WebxUi\Orders\OrderFields;

/**
 * The definitions of the checkout's extra fields as the panel and the agent write them
 * (WEBX_UI_ORDER_FIELDS.md §2.1, §3, §7.3): each refusal, what a dry run answers, and the cache
 * that every checkout request reads them through.
 */
final class OrderFieldsTest extends TestCase
{
    #[Test]
    public function a_field_is_made_at_the_end_in_the_default_place(): void
    {
        $this->checkbox('installation');
        $plan = $this->fields()->write(['key' => 'company_name', 'type' => 'text', 'label' => ['en' => ' Company name ', 'ru' => '']]);

        $field = OrderField::query()->where('key', 'company_name')->sole();
        $this->assertSame([FieldPlan::CREATED], $plan->effects);
        $this->assertSame($field->id, $plan->id);
        $this->assertSame(FieldPlaces::DEFAULT, $field->place);
        $this->assertSame(['en' => 'Company name'], $field->getTranslations('label'));
        $this->assertNull($field->getAttributes()['hint']);
        $this->assertNull($field->options);
        $this->assertTrue($field->active);
        $this->assertFalse($field->required);
        $this->assertGreaterThan(OrderField::query()->where('key', 'installation')->value('position'), $field->position);
    }

    #[Test]
    public function a_dry_run_answers_the_same_and_writes_nothing(): void
    {
        $plan = $this->fields()->write($this->input('installation'), dryRun: true);

        $this->assertTrue($plan->dryRun);
        $this->assertSame([FieldPlan::CREATED], $plan->effects);
        $this->assertSame(0, OrderField::query()->count());

        $refused = $this->fields()->write(['key' => '!!!', 'type' => 'text', 'label' => ['en' => 'Bad']], dryRun: true);
        $this->assertSame(FieldRefused::KEY_INVALID, $refused->refusal);
        $this->assertSame('key', $refused->field);
        $this->assertSame(0, OrderField::query()->count());
    }

    #[Test]
    public function the_key_is_brought_to_the_rule_of_a_variable(): void
    {
        // A key is brought to its shape rather than refused for it (#125); only nothing left is refused.
        $this->assertRefused(FieldRefused::KEY_INVALID, ['key' => '!!!', 'type' => 'text', 'label' => ['en' => 'Field']]);

        foreach ([
            'Delivery_Time' => 'delivery_time',
            '1st' => 'field_1st',
            'with-dash' => 'with_dash',
            'Ünïcode' => 'unicode',
            str_repeat('a', 33) => str_repeat('a', 32),
            'Тормозные колодки' => 'tormoznye_kolodki',
        ] as $typed => $key) {
            $this->assertSame($key, $this->fields()->write(['key' => $typed, 'type' => 'text', 'label' => ['en' => 'Field']])->key);
            $this->assertTrue(OrderField::query()->where('key', $key)->exists(), $typed);
        }

        // The same key typed again is taken, however it is typed.
        $this->assertRefused(FieldRefused::KEY_TAKEN, ['key' => 'Tormoznye Kolodki', 'type' => 'text', 'label' => ['en' => 'Field']]);
    }

    #[Test]
    public function a_key_is_taken_once(): void
    {
        $this->checkbox('installation');

        $this->assertRefused(FieldRefused::KEY_TAKEN, $this->input('installation'));
    }

    #[Test]
    public function the_key_and_the_type_never_change(): void
    {
        $field = $this->checkbox('installation');

        $this->assertRefused(FieldRefused::LOCKED, ['key' => 'setup'], $field, 'key');
        $this->assertRefused(FieldRefused::LOCKED, ['type' => 'text'], $field, 'type');

        // The same key and type said again are no change.
        $plan = $this->fields()->write(['key' => 'installation', 'type' => 'checkbox', 'required' => true], $field);
        $this->assertSame([FieldPlan::REQUIRED_ON], $plan->effects);
    }

    #[Test]
    public function the_place_is_one_the_registry_knows(): void
    {
        $this->assertRefused(FieldRefused::PLACE_INVALID, $this->input('installation') + ['place' => 'delivery-after'], field: null, about: 'place');

        $this->app->make(FieldPlaces::class)->register('delivery-after', step: 'delivery', label: 'After the delivery');
        $this->fields()->write($this->input('installation') + ['place' => 'delivery-after']);

        $this->assertSame('delivery-after', OrderField::query()->sole()->place);
    }

    #[Test]
    public function a_field_whose_place_is_gone_is_kept_but_not_saved_again(): void
    {
        $field = $this->checkbox('installation');
        // A module that registered the place is gone: the row stays as it was written.
        OrderField::query()->whereKey($field->id)->update(['place' => 'delivery-after']);

        $this->assertRefused(FieldRefused::PLACE_INVALID, ['required' => true], $field->refresh(), 'place');

        $this->fields()->write(['place' => FieldPlaces::AFTER_COMMENT], $field);
        $this->assertSame(FieldPlaces::AFTER_COMMENT, $field->refresh()->place);
    }

    #[Test]
    public function a_label_in_the_main_language_is_needed(): void
    {
        $this->assertRefused(FieldRefused::NO_LABEL, ['key' => 'installation', 'type' => 'checkbox', 'label' => ['ru' => 'Монтаж']], about: 'label');
        $this->assertRefused(FieldRefused::NO_LABEL, ['key' => 'installation', 'type' => 'checkbox'], about: 'label');
    }

    #[Test]
    public function only_a_list_has_options_and_a_list_needs_them(): void
    {
        $this->assertRefused(FieldRefused::NO_OPTIONS, ['key' => 'delivery_time', 'type' => 'select', 'label' => ['en' => 'Time']], about: 'options');
        $this->assertRefused(FieldRefused::NO_OPTIONS, ['key' => 'company_name', 'type' => 'text', 'label' => ['en' => 'Company'], 'options' => [['value' => 'a', 'label' => ['en' => 'A']]]], about: 'options');
    }

    #[Test]
    public function every_option_has_a_key_of_its_own_and_a_label(): void
    {
        $base = ['key' => 'delivery_time', 'type' => 'select', 'label' => ['en' => 'Time']];

        foreach ([
            [['value' => '!!!', 'label' => ['en' => 'Evening']]],
            [['value' => 'evening', 'label' => ['ru' => 'Вечер']]],
            [['value' => 'evening', 'label' => ['en' => 'Evening']], ['value' => 'Evening!', 'label' => ['en' => 'Late']]],
        ] as $options) {
            $this->assertRefused(FieldRefused::OPTION_INVALID, $base + ['options' => $options], about: 'options');
        }

        $this->fields()->write($base + ['options' => [['value' => ' Morning ', 'label' => ['en' => '9:00–12:00']], ['value' => 'поздно', 'label' => ['en' => '18:00–21:00', 'ru' => '']], ['value' => '2 hours', 'label' => ['en' => 'Two hours']]]]);
        $this->assertSame(
            [['value' => 'morning', 'label' => ['en' => '9:00–12:00']], ['value' => 'pozdno', 'label' => ['en' => '18:00–21:00']], ['value' => 'option_2_hours', 'label' => ['en' => 'Two hours']]],
            OrderField::query()->sole()->options,
        );
    }

    #[Test]
    public function at_most_thirty_fields_and_fifty_options(): void
    {
        $options = array_map(static fn (int $i): array => ['value' => 'v'.$i, 'label' => ['en' => 'V'.$i]], range(1, OrderField::MAX_OPTIONS + 1));
        $this->assertRefused(FieldRefused::TOO_MANY, ['key' => 'delivery_time', 'type' => 'select', 'label' => ['en' => 'Time'], 'options' => $options], about: 'options');

        foreach (range(1, OrderField::MAX_FIELDS) as $i) {
            $this->fields()->write($this->input('field_'.$i));
        }

        $this->assertRefused(FieldRefused::TOO_MANY, $this->input('one_more'), about: 'key');
    }

    #[Test]
    public function a_field_is_shown_behind_a_checkbox_of_its_place_one_level_deep(): void
    {
        $company = $this->checkbox('for_company', FieldPlaces::CONTACTS);
        $this->fields()->write(['key' => 'delivery_time', 'type' => 'select', 'label' => ['en' => 'Time'], 'options' => [['value' => 'evening', 'label' => ['en' => 'Evening']]]]);
        $text = ['type' => 'text', 'label' => ['en' => 'Company name']];

        // Not a checkbox, unknown, another place.
        $this->assertRefused(FieldRefused::PARENT_INVALID, ['key' => 'company_name', 'parent' => 'delivery_time'] + $text, about: 'parent');
        $this->assertRefused(FieldRefused::PARENT_INVALID, ['key' => 'company_name', 'parent' => 'for_compny'] + $text, about: 'parent');
        $this->assertRefused(FieldRefused::PARENT_INVALID, ['key' => 'company_name', 'parent' => 'for_company', 'place' => FieldPlaces::BEFORE_COMMENT] + $text, about: 'parent');

        // Without a place of its own it stands where its checkbox does.
        $this->fields()->write(['key' => 'company_name', 'parent' => 'for_company'] + $text);
        $name = OrderField::query()->where('key', 'company_name')->sole();
        $this->assertSame($company->id, $name->parent_id);
        $this->assertSame(FieldPlaces::CONTACTS, $name->place);

        // A checkbox behind a checkbox may not have fields behind it in turn — one level.
        $this->fields()->write(['key' => 'vat_payer', 'type' => 'checkbox', 'label' => ['en' => 'VAT payer'], 'parent' => 'for_company']);
        $this->assertRefused(FieldRefused::PARENT_INVALID, ['key' => 'vat_number', 'parent' => 'vat_payer'] + $text, about: 'parent');

        // A checkbox with fields behind it is not put behind another, nor behind itself.
        $this->checkbox('gift', FieldPlaces::CONTACTS);
        $this->assertRefused(FieldRefused::PARENT_INVALID, ['parent' => 'gift'], $company->refresh(), 'parent');
        $this->assertRefused(FieldRefused::PARENT_INVALID, ['parent' => 'gift'], OrderField::query()->where('key', 'gift')->sole(), 'parent');
    }

    #[Test]
    public function a_checkbox_moved_takes_its_fields_with_it(): void
    {
        $company = $this->checkbox('for_company', FieldPlaces::CONTACTS);
        $this->fields()->write(['key' => 'company_name', 'type' => 'text', 'label' => ['en' => 'Company name'], 'parent' => 'for_company']);

        $plan = $this->fields()->write(['place' => FieldPlaces::AFTER_COMMENT], $company, dryRun: true);
        $this->assertSame([FieldPlan::MOVED], $plan->effects);
        $this->assertSame(['company_name'], $plan->dependents);
        $this->assertSame(FieldPlaces::CONTACTS, OrderField::query()->where('key', 'company_name')->value('place'));

        $this->fields()->write(['place' => FieldPlaces::AFTER_COMMENT], $company);
        $this->assertSame([FieldPlaces::AFTER_COMMENT], OrderField::query()->distinct()->pluck('place')->all());

        // The dependent alone may not leave the place of its checkbox.
        $this->assertRefused(FieldRefused::PARENT_INVALID, ['place' => FieldPlaces::CONTACTS], OrderField::query()->where('key', 'company_name')->sole(), 'parent');
    }

    #[Test]
    public function the_plan_says_what_the_buyer_will_see(): void
    {
        $company = $this->checkbox('for_company');
        $this->fields()->write(['key' => 'company_name', 'type' => 'text', 'label' => ['en' => 'Company name'], 'parent' => 'for_company']);

        $plan = $this->fields()->write(['active' => false, 'required' => true], $company);
        $this->assertSame([FieldPlan::REQUIRED_ON, FieldPlan::HIDDEN], $plan->effects);
        // Switching off a checkbox with fields behind it is no refusal: they stop being shown.
        $this->assertSame(['company_name'], $plan->dependents);
        $this->assertFalse($company->refresh()->active);
        $this->assertFalse($plan->toArray()['orders_touched']);

        $list = $this->fields()->write(['key' => 'delivery_time', 'type' => 'select', 'label' => ['en' => 'Time'], 'options' => [
            ['value' => 'morning', 'label' => ['en' => 'Morning']],
            ['value' => 'evening', 'label' => ['en' => 'Evening']],
        ]]);
        $field = OrderField::query()->findOrFail($list->id);
        $plan = $this->fields()->write(['options' => [['value' => 'evening', 'label' => ['en' => 'Evening']]]], $field, dryRun: true);
        $this->assertSame([FieldPlan::OPTIONS_REMOVED], $plan->effects);
        $this->assertSame(['morning'], $plan->removedOptions);
    }

    #[Test]
    public function a_checkbox_with_fields_behind_it_is_not_deleted(): void
    {
        $company = $this->checkbox('for_company');
        $this->fields()->write(['key' => 'company_name', 'type' => 'text', 'label' => ['en' => 'Company name'], 'parent' => 'for_company']);
        $this->fields()->write(['key' => 'company_code', 'type' => 'text', 'label' => ['en' => 'Company code'], 'parent' => 'for_company']);

        $plan = $this->fields()->remove($company, dryRun: true);
        $this->assertSame(FieldRefused::HAS_DEPENDENTS, $plan->refusal);
        $this->assertSame(['company_name', 'company_code'], $plan->dependents);

        try {
            $this->fields()->remove($company);
            $this->fail('A checkbox with fields behind it went.');
        } catch (FieldRefused $refused) {
            $this->assertSame(FieldRefused::HAS_DEPENDENTS, $refused->reason);
            $this->assertStringContainsString('company_name, company_code', $refused->getMessage());
        }

        foreach (OrderField::query()->whereNotNull('parent_id')->get() as $dependent) {
            $this->assertSame([FieldPlan::DELETED], $this->fields()->remove($dependent)->effects);
        }

        $this->fields()->remove($company);
        $this->assertSame(0, OrderField::query()->count());
    }

    #[Test]
    public function the_shape_of_the_input_is_a_validation_error(): void
    {
        $this->expectException(ValidationException::class);

        $this->fields()->write(['key' => 'installation', 'type' => 'checkbox', 'label' => ['en' => str_repeat('a', OrderField::MAX_LABEL + 1)]]);
    }

    #[Test]
    public function a_type_is_one_of_four(): void
    {
        $this->expectException(ValidationException::class);

        $this->fields()->write(['key' => 'when', 'type' => 'date', 'label' => ['en' => 'When']]);
    }

    #[Test]
    public function the_order_names_every_field_once(): void
    {
        foreach (['installation', 'for_company', 'no_callback'] as $key) {
            $this->checkbox($key);
        }

        foreach ([['installation', 'for_company'], ['installation', 'for_company', 'no_callback', 'no_callback'], ['installation', 'for_company', 'gift']] as $keys) {
            $plan = $this->fields()->arrange($keys, dryRun: true);
            $this->assertSame(FieldRefused::ARRANGEMENT, $plan->refusal);

            try {
                $this->fields()->arrange($keys);
                $this->fail('An order that does not name every field was taken.');
            } catch (FieldRefused $refused) {
                $this->assertSame(FieldRefused::ARRANGEMENT, $refused->reason);
            }
        }

        $this->assertSame([FieldPlan::REORDERED], $this->fields()->arrange(['no_callback', 'installation', 'for_company'])->effects);
        $this->assertSame(['no_callback', 'installation', 'for_company'], OrderField::query()->orderBy('position')->pluck('key')->all());
    }

    #[Test]
    public function the_cache_is_fresh_after_every_write(): void
    {
        $this->checkbox('installation');
        $field = $this->checkbox('no_callback');
        $this->assertSame(['installation', 'no_callback'], $this->keys());

        // Warm now: a second read asks the database nothing.
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->keys();
        $this->assertSame([], array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'order_fields')));

        // A mass update fires no event: arrange moves the group itself.
        $this->fields()->arrange(['no_callback', 'installation']);
        $this->assertSame(['no_callback', 'installation'], $this->keys());

        $this->fields()->write(['label' => ['en' => 'Do not call back']], $field);
        $this->assertSame('Do not call back', $this->fields()->all()->firstWhere('key', 'no_callback')?->getTranslation('label', 'en'));

        $this->fields()->remove($field);
        $this->assertSame(['installation'], $this->keys());
    }

    #[Test]
    public function every_write_of_a_definition_is_in_the_journal(): void
    {
        $field = $this->checkbox('installation');
        $this->fields()->write(['required' => true], $field);
        $this->fields()->remove($field);

        $this->assertSame(
            [HistoryEntry::CREATED, HistoryEntry::UPDATED, HistoryEntry::DELETED],
            HistoryEntry::query()->where('subject_type', OrderField::TYPE)->orderBy('id')->pluck('event')->all(),
        );
    }

    private function fields(): OrderFields
    {
        return $this->app->make(OrderFields::class);
    }

    /** @return array<string, mixed> */
    private function input(string $key): array
    {
        return ['key' => $key, 'type' => 'checkbox', 'label' => ['en' => ucfirst(str_replace('_', ' ', $key))]];
    }

    private function checkbox(string $key, string $place = FieldPlaces::DEFAULT): OrderField
    {
        $plan = $this->fields()->write($this->input($key) + ['place' => $place]);

        return OrderField::query()->findOrFail($plan->id);
    }

    /** @return list<string> */
    private function keys(): array
    {
        return array_values(array_map(strval(...), $this->fields()->all()->pluck('key')->all()));
    }

    /** @param  array<string, mixed>  $input */
    private function assertRefused(string $code, array $input, ?OrderField $field = null, string $about = 'key'): void
    {
        $count = OrderField::query()->count();
        $before = $field?->refresh()->getAttributes();

        $plan = $this->fields()->write($input, $field, dryRun: true);
        $this->assertSame($code, $plan->refusal, 'dry run of '.json_encode($input));
        $this->assertSame($about, $plan->field);

        try {
            $this->fields()->write($input, $field);
            $this->fail("Not refused with {$code}: ".json_encode($input));
        } catch (FieldRefused $refused) {
            $this->assertSame($code, $refused->reason);
            $this->assertSame($about, $refused->plan->field);
        }

        $this->assertSame($count, OrderField::query()->count());

        if ($field !== null) {
            $this->assertSame($before, $field->refresh()->getAttributes());
        }
    }
}
