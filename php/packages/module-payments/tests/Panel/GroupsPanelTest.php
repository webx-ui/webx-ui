<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodGroup;
use WebxUi\Payments\MethodGroups;
use WebxUi\Payments\Methods;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentGroup;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\SimilarMethods;
use WebxUi\Payments\Tests\Fakes\MarkedType;

/**
 * The groups in the panel (groups spec §7, §5): an edit goes over the config and never into it, a
 * group is neither created nor removed, the order of the groups and of their methods is one write,
 * the field «Group» of a method keeps only a real move — and two methods the buyer could mistake
 * for one another are warned of, never refused.
 */
final class GroupsPanelTest extends PanelCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(MethodTypes::class)->register(new MarkedType);
    }

    /** @param  list<string>  $marks */
    private function marked(string $key, array $marks, bool $enabled = true, ?string $group = null): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'key' => $key,
            'type' => MarkedType::KEY,
            'title' => ['en' => ucfirst($key)],
            'enabled' => $enabled,
            'position' => 50,
            'group_key' => $group,
            'delivery_rule' => ['mode' => 'any'],
            'settings' => ['marks' => $marks],
        ]);
    }

    private function method(string $key): PaymentMethod
    {
        return PaymentMethod::query()->where('key', $key)->firstOrFail();
    }

    /** @return list<array{key: string, methods: list<int>}> the list as the screen has it */
    private function order(): array
    {
        $rows = $this->getJson($this->api('methods'))->assertOk()->json('data.methods');
        $order = [];

        foreach ($this->app->make(MethodGroups::class)->all() as $group) {
            $order[] = ['key' => $group->key, 'methods' => array_column(array_filter($rows, static fn (array $row): bool => $row['group'] === $group->key), 'id')];
        }

        return $order;
    }

    /** @return array<string, MethodGroup> */
    private function siteGroups(): array
    {
        return collect($this->app->make(MethodGroups::class)->all())->keyBy('key')->all();
    }

    #[Test]
    public function the_screen_reads_the_groups_of_the_config_and_the_set_of_pictograms(): void
    {
        $this->signIn(['payments.view']);

        $answer = $this->getJson($this->api('groups'))->assertOk();

        $this->assertSame(['on_receipt', 'online', 'transfer'], array_column($answer->json('data.groups'), 'key'));
        $this->assertSame(['Pay online', 'Pay online', 'card'], [$answer->json('data.groups.1.title'), $answer->json('data.groups.1.config_title'), $answer->json('data.groups.1.pictogram')]);
        $this->assertSame(MethodGroups::PICTOGRAMS, array_column($answer->json('data.pictograms'), 'name'));
        $this->assertStringContainsString('<rect', $answer->json('data.pictograms.0.svg'));
        $this->assertStringNotContainsString('{{--', $answer->json('data.pictograms.0.svg'));
    }

    #[Test]
    public function an_edit_is_a_row_over_the_config_and_the_site_reads_it(): void
    {
        $this->signIn();
        $config = config('webx-payments.groups');

        $this->assertSame('Pay online', $this->siteGroups()['online']->title('en'), 'Warms the cache the edit has to reset.');

        $this->putJson($this->api('groups/online'), [
            'titles' => ['en' => 'Pay right now', 'uk' => ''],
            'descriptions' => ['en' => 'Cards and wallets'],
            'pictogram' => 'wallet',
        ])->assertOk()->assertJsonPath('data.title', 'Pay right now')->assertJsonPath('data.config_title', 'Pay online');

        $row = PaymentGroup::query()->where('key', 'online')->firstOrFail();
        $this->assertSame([['en' => 'Pay right now'], ['en' => 'Cards and wallets'], 'wallet'], [$row->title, $row->description, $row->pictogram]);
        $this->assertSame($config, config('webx-payments.groups'));

        $group = $this->siteGroups()['online'];
        $this->assertSame(['Pay right now', 'Cards and wallets', 'wallet'], [$group->title('en'), $group->description('en'), $group->pictogram]);
    }

    #[Test]
    public function an_emptied_field_goes_back_to_the_config(): void
    {
        $this->signIn();
        $this->putJson($this->api('groups/online'), ['titles' => ['en' => 'Pay right now'], 'pictogram' => 'wallet'])->assertOk();

        $this->putJson($this->api('groups/online'), ['titles' => ['en' => ' '], 'descriptions' => [], 'pictogram' => 'card'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Pay online')
            ->assertJsonPath('data.pictogram', 'card');

        $row = PaymentGroup::query()->where('key', 'online')->firstOrFail();
        $this->assertSame([null, null, null], [$row->title, $row->description, $row->pictogram], 'The config\'s own pictogram is no edit.');
    }

    #[Test]
    public function a_group_is_neither_created_nor_removed(): void
    {
        $this->signIn();

        $this->putJson($this->api('groups/crypto'), ['titles' => ['en' => 'Crypto']])->assertNotFound();
        $this->postJson($this->api('groups'), ['key' => 'crypto'])->assertMethodNotAllowed();
        $this->deleteJson($this->api('groups/online'))->assertMethodNotAllowed();

        $this->assertSame(0, PaymentGroup::query()->count());
        $this->assertSame(['on_receipt', 'online', 'transfer'], array_keys($this->siteGroups()));
    }

    #[Test]
    public function a_pictogram_outside_the_set_is_refused_in_words(): void
    {
        $this->signIn();

        $this->putJson($this->api('groups/online'), ['pictogram' => 'rocket'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.pictogram.0', 'Unknown pictogram: rocket');

        $this->assertSame(0, PaymentGroup::query()->count());
    }

    #[Test]
    public function the_order_moves_groups_and_methods_in_one_write_and_the_cache_follows(): void
    {
        $this->signIn();
        $methods = $this->app->make(Methods::class);
        $methods->enabled();
        $this->siteGroups();

        $order = $this->order();
        $card = $this->method('gateway-a-card');

        // «Bank transfer» goes first, and the card into it, at the top.
        $transfer = array_values(array_filter($order, static fn (array $group): bool => $group['key'] === 'transfer'))[0];
        $online = array_values(array_filter($order, static fn (array $group): bool => $group['key'] === 'online'))[0];
        $receipt = array_values(array_filter($order, static fn (array $group): bool => $group['key'] === 'on_receipt'))[0];

        $this->putJson($this->api('methods/order'), ['groups' => [
            ['key' => 'transfer', 'methods' => [$card->id, ...$transfer['methods']]],
            $receipt,
            ['key' => 'online', 'methods' => array_values(array_diff($online['methods'], [$card->id]))],
        ]])->assertNoContent();

        $this->assertSame('transfer', $card->refresh()->group_key);
        $this->assertSame(0, $card->position);
        $this->assertSame(['transfer', 'on_receipt', 'online'], array_keys($this->siteGroups()));
        $this->assertSame([0, 1, 2], PaymentGroup::query()->orderBy('position')->pluck('position')->all());

        $enabled = collect($methods->enabled())->keyBy('key');
        $this->assertSame('transfer', $enabled['gateway-a-card']->groupKey, 'The list of the step is cached: the write resets it.');
        $this->assertSame(0, $enabled['gateway-a-card']->position);
    }

    #[Test]
    public function a_method_moved_back_to_the_group_of_its_type_follows_the_type_again(): void
    {
        $this->signIn();
        $card = $this->method('gateway-a-card');
        $card->update(['group_key' => 'transfer']);

        $order = array_map(static function (array $group) use ($card): array {
            $group['methods'] = array_values(array_diff($group['methods'], [$card->id]));

            return $group['key'] === 'online' ? ['key' => 'online', 'methods' => [$card->id, ...$group['methods']]] : $group;
        }, $this->order());

        $this->putJson($this->api('methods/order'), ['groups' => $order])->assertNoContent();

        $this->assertNull($card->refresh()->group_key);
    }

    #[Test]
    public function a_move_of_a_method_does_not_pin_the_order_of_the_groups(): void
    {
        $this->signIn();

        $this->putJson($this->api('methods/order'), ['groups' => $this->order()])->assertNoContent();

        $this->assertSame(0, PaymentGroup::query()->count(), 'A group the config adds later still finds its place.');
    }

    #[Test]
    public function a_drag_keeps_a_move_to_a_group_the_config_lost(): void
    {
        $this->signIn();
        $card = $this->method('gateway-a-card');
        $card->update(['group_key' => 'crypto']);

        $this->putJson($this->api('methods/order'), ['groups' => $this->order()])->assertNoContent();

        $this->assertSame('crypto', $card->refresh()->group_key);
    }

    #[Test]
    public function an_order_that_is_not_the_whole_list_is_refused_and_writes_nothing(): void
    {
        $this->signIn();
        $order = $this->order();
        $positions = PaymentMethod::query()->orderBy('id')->pluck('position')->all();

        $without = $order;
        array_pop($without[1]['methods']);

        foreach ([
            'a method left out' => $without,
            'a group left out' => array_slice($order, 1),
            'a group of its own' => [...$order, ['key' => 'crypto', 'methods' => []]],
            'a group twice' => [...$order, $order[0]],
            'not a list of groups' => 'online',
        ] as $case => $groups) {
            $this->putJson($this->api('methods/order'), ['groups' => $groups])->assertUnprocessable();
            $this->assertSame($positions, PaymentMethod::query()->orderBy('id')->pluck('position')->all(), $case);
        }

        $this->assertSame(0, PaymentGroup::query()->count());
    }

    #[Test]
    public function a_site_without_groups_keeps_the_flat_order(): void
    {
        config(['webx-payments.groups' => []]);
        $this->signIn();

        $ids = PaymentMethod::query()->orderByDesc('position')->pluck('id')->all();

        $this->putJson($this->api('methods/order'), ['ids' => $ids])->assertNoContent();

        $this->assertSame($ids, PaymentMethod::query()->orderBy('position')->pluck('id')->all());
        $this->assertSame([], $this->getJson($this->api('groups'))->assertOk()->json('data.groups'));
        $this->assertSame([], $this->getJson($this->api('methods/'.$ids[0]))->assertOk()->json('data.groups'));
    }

    #[Test]
    public function the_form_offers_the_group_of_the_type_and_the_groups_of_the_site(): void
    {
        $this->signIn();

        $answer = $this->getJson($this->api('methods/'.$this->method('gateway-a-card')->id))->assertOk();

        $this->assertSame([
            ['value' => '', 'label' => 'Default (Pay online)'],
            ['value' => 'on_receipt', 'label' => 'Pay on receipt'],
            ['value' => 'online', 'label' => 'Pay online'],
            ['value' => 'transfer', 'label' => 'Bank transfer'],
        ], $answer->json('data.groups'));
        $this->assertSame('', $answer->json('data.values.group_key'));
        $this->assertSame('Default (Bank transfer)', $this->getJson($this->api('methods/blank?type=store.bank_transfer'))->assertOk()->json('data.groups.0.label'));
    }

    #[Test]
    public function the_field_keeps_a_move_and_the_group_of_the_type_is_none(): void
    {
        $this->signIn();
        $card = $this->method('gateway-a-card');
        $path = $this->api('methods/'.$card->id);

        $this->putJson($path, ['values' => ['group_key' => 'transfer']])
            ->assertOk()
            ->assertJsonPath('data.method.group', 'transfer')
            ->assertJsonPath('data.values.group_key', 'transfer');
        $this->assertSame('transfer', $card->refresh()->group_key);

        $this->putJson($path, ['values' => ['group_key' => 'online']])->assertOk()->assertJsonPath('data.values.group_key', '');
        $this->assertNull($card->refresh()->group_key);

        $card->update(['group_key' => 'transfer']);
        $this->putJson($path, ['values' => ['group_key' => '']])->assertOk()->assertJsonPath('data.method.group', 'online');
        $this->assertNull($card->refresh()->group_key);
    }

    #[Test]
    public function a_method_chosen_into_a_group_goes_to_its_end(): void
    {
        $this->signIn();
        $card = $this->method('gateway-a-card');

        $this->putJson($this->api('methods/'.$card->id), ['values' => ['group_key' => 'transfer']])->assertOk();

        $this->assertSame((int) PaymentMethod::query()->max('position'), $card->refresh()->position);
        $this->assertSame(1, PaymentMethod::query()->where('position', $card->position)->count());

        $position = $card->position;
        $this->putJson($this->api('methods/'.$card->id), ['values' => ['group_key' => 'transfer', 'titles' => ['en' => 'Card']]])->assertOk();
        $this->assertSame($position, $card->refresh()->position, 'Saved again, it stays where it was.');
    }

    #[Test]
    public function a_new_method_may_start_in_a_group(): void
    {
        $this->signIn();

        $this->postJson($this->api('methods'), ['type' => 'gateway-a.card', 'values' => ['titles' => [], 'group_key' => 'transfer']])
            ->assertCreated()
            ->assertJsonPath('data.method.group', 'transfer');
    }

    #[Test]
    public function the_field_refuses_a_group_the_site_does_not_have_and_keeps_a_lost_one(): void
    {
        $this->signIn();
        $card = $this->method('gateway-a-card');
        $path = $this->api('methods/'.$card->id);

        $this->putJson($path, ['values' => ['group_key' => 'crypto']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.group_key.0', 'Unknown group: crypto');
        $this->assertNull($card->refresh()->group_key);

        // Moved before the config lost the group: the form shows the default and saves it back.
        $card->update(['group_key' => 'crypto']);
        $this->getJson($path)->assertOk()->assertJsonPath('data.values.group_key', '');
        $this->putJson($path, ['values' => ['group_key' => '', 'titles' => ['en' => 'Card']]])->assertOk();
        $this->assertSame('crypto', $card->refresh()->group_key);
    }

    #[Test]
    public function two_methods_of_only_google_pay_in_a_group_warn_and_saving_goes_through(): void
    {
        $this->signIn();
        $first = $this->marked('first', ['google-pay']);
        $second = $this->marked('second', ['google-pay'], enabled: false);

        // Nothing while the second is switched off.
        $this->getJson($this->api('methods/'.$first->id))->assertOk()->assertJsonPath('data.warnings', []);

        $answer = $this->putJson($this->api('methods/'.$second->id), ['values' => ['enabled' => true, 'titles' => ['en' => 'Second']]])->assertOk();

        $this->assertTrue($second->refresh()->enabled, 'A warning, never a refusal.');
        $this->assertSame(
            ['This group already has a method with Google Pay: the buyer will see two similar items. Tell them apart by title, for example “Google Pay via monobank”.'],
            $answer->json('data.warnings'),
        );
        $this->assertSame([['key' => 'first', 'title' => 'First']], $answer->json('data.method.similar_to'));

        $rows = $this->getJson($this->api('methods'))->assertOk()->collect('data.methods')->keyBy('key');
        $this->assertSame([['key' => 'second', 'title' => 'Second']], $rows['first']['similar_to']);
        $this->assertSame(['google-pay'], $rows['first']['marks']);
        $this->assertSame([], $rows['gateway-a-card']['similar_to']);
    }

    #[Test]
    public function the_same_set_of_several_marks_warns_in_its_own_words(): void
    {
        $this->signIn();
        $first = $this->marked('first', ['visa', 'mastercard']);
        $this->marked('second', ['mastercard', 'visa']);

        $answer = $this->getJson($this->api('methods/'.$first->id))->assertOk();

        $this->assertStringContainsString('the same payment systems', $answer->json('data.warnings.0'));
    }

    #[Test]
    public function moving_one_of_two_like_methods_to_another_group_ends_the_warning(): void
    {
        $this->signIn();
        $first = $this->marked('first', ['google-pay']);
        $second = $this->marked('second', ['google-pay']);

        $this->getJson($this->api('methods/'.$first->id))->assertOk()->assertJsonCount(1, 'data.warnings');

        $this->putJson($this->api('methods/'.$second->id), ['values' => ['group_key' => 'online']])
            ->assertOk()
            ->assertJsonPath('data.warnings', []);
    }

    #[Test]
    public function similar_means_one_group_and_the_same_marks_of_enabled_methods(): void
    {
        $this->marked('pay', ['google-pay']);
        $this->marked('pay-too', ['google-pay']);
        $this->marked('pay-elsewhere', ['google-pay'], group: 'online');
        $this->marked('pay-off', ['google-pay'], enabled: false);
        $this->marked('card-and-apple', ['visa', 'apple-pay']);
        $this->marked('only-apple', ['apple-pay']);
        $this->marked('no-marks', []);
        $this->marked('no-marks-too', []);

        $all = PaymentMethod::query()->get()->map(Method::of(...))->all();

        $this->assertSame(
            ['pay' => ['pay-too'], 'pay-too' => ['pay']],
            $this->app->make(SimilarMethods::class)->of($all),
        );
    }

    #[Test]
    public function a_method_of_a_type_no_longer_installed_is_listed_and_compares_with_nothing(): void
    {
        $this->signIn();
        $lost = PaymentMethod::query()->create(['key' => 'lost', 'type' => 'gone.card', 'title' => ['en' => 'Lost'], 'enabled' => true, 'position' => 60, 'delivery_rule' => ['mode' => 'any'], 'settings' => []]);

        $row = $this->getJson($this->api('methods'))->assertOk()->collect('data.methods')->firstWhere('key', 'lost');

        $this->assertSame(['on_receipt', [], []], [$row['group'], $row['marks'], $row['similar_to']]);

        $order = array_map(static function (array $group) use ($lost): array {
            $group['methods'] = array_values(array_diff($group['methods'], [$lost->id]));

            return $group['key'] === 'transfer' ? ['key' => 'transfer', 'methods' => [...$group['methods'], $lost->id]] : $group;
        }, $this->order());

        $this->putJson($this->api('methods/order'), ['groups' => $order])->assertNoContent();

        $this->assertSame('transfer', $lost->refresh()->group_key);
    }
}
