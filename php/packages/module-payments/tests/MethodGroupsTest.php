<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodGroup;
use WebxUi\Payments\MethodGroups;
use WebxUi\Payments\MethodGroupView;
use WebxUi\Payments\MethodOption;
use WebxUi\Payments\Methods;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentGroup;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\Tests\Fakes\DeclaringType;
use WebxUi\Payments\Tests\Fakes\FakeOnlineType;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * The groups of the buyer's list (groups spec §2): the config says which there are, the rows of
 * `payment_groups` how they read and stand, and no change of the config ever loses a method.
 */
final class MethodGroupsTest extends TestCase
{
    use CountsQueries;

    private const array BOX = ['on_receipt', 'online', 'transfer'];

    #[Test]
    public function a_site_without_groups_of_its_own_gets_the_three_of_the_box(): void
    {
        $groups = $this->service()->all();

        $this->assertSame(self::BOX, $this->keysOf($groups));
        $this->assertSame(['Pay on receipt', 'Pay online', 'Bank transfer'], array_map(static fn (MethodGroup $group): string => $group->title('en'), $groups));
        $this->assertSame(['cash', 'card', 'document'], array_map(static fn (MethodGroup $group): ?string => $group->pictogram, $groups));
        $this->assertSame(['При получении', 'Оплата онлайн', 'По реквизитам'], array_map(static fn (MethodGroup $group): string => $group->title('ru'), $groups));
        $this->assertSame(['При отриманні', 'Оплата онлайн', 'За реквізитами'], array_map(static fn (MethodGroup $group): string => $group->title('uk'), $groups));
        $this->assertNull($groups[0]->description('en'));
    }

    #[Test]
    public function the_type_stands_by_its_kind_and_whether_the_order_awaits_payment(): void
    {
        $this->assertSame('online', $this->service()->ofType(new FakeOnlineType));
        $this->assertSame('on_receipt', $this->service()->ofType($this->types()->get(OnReceipt::KEY)));
        $this->assertSame('transfer', $this->service()->ofType($this->types()->get(BankTransfer::KEY)));
    }

    #[Test]
    public function a_type_may_declare_another_group_and_an_unknown_one_goes_by_its_kind(): void
    {
        $this->assertSame('transfer', $this->service()->ofType(new DeclaringType('transfer')));
        $this->assertSame('on_receipt', $this->service()->ofType(new DeclaringType('nowhere')));
    }

    #[Test]
    public function a_method_stands_where_the_manager_moved_it(): void
    {
        $type = $this->types()->get(BankTransfer::KEY);

        $this->assertSame('transfer', $this->service()->of(self::method(), $type));
        $this->assertSame('online', $this->service()->of(self::method(group: 'online'), $type));
    }

    #[Test]
    public function a_move_to_a_group_the_config_lost_waits_in_the_group_of_the_type(): void
    {
        $type = $this->types()->get(BankTransfer::KEY);
        $moved = self::method(group: 'instalments');

        $this->assertSame('transfer', $this->service()->of($moved, $type));

        $this->listGroups(['instalments' => ['title' => 'Instalments'], 'transfer' => ['title' => 'Transfer']]);

        $this->assertSame('instalments', $this->service()->of($moved, $type));
    }

    #[Test]
    public function a_method_whose_group_is_gone_stands_in_the_first_group_of_the_config(): void
    {
        $this->listGroups(['first' => ['title' => 'First'], 'second' => ['title' => 'Second']]);

        $this->assertSame('first', $this->service()->of(self::method(), $this->types()->get(BankTransfer::KEY)));
        $this->assertSame('first', $this->service()->of(self::method(group: 'gone'), $this->types()->get(BankTransfer::KEY)));
    }

    #[Test]
    public function the_site_replaces_the_list_whole_and_its_title_may_be_words(): void
    {
        $this->listGroups(['card_only' => ['title' => 'By card', 'pictogram' => 'wallet']]);

        [$group] = $this->service()->all();

        $this->assertSame(['card_only'], $this->service()->keys());
        $this->assertSame('By card', $group->title('en'));
        $this->assertSame('wallet', $group->pictogram);
    }

    #[Test]
    public function a_pictogram_outside_the_set_leaves_the_group_without_one(): void
    {
        $this->listGroups(['a' => ['title' => 'A', 'pictogram' => 'rocket'], 'b' => ['title' => 'B']]);

        $this->assertSame([null, null], array_map(static fn (MethodGroup $group): ?string => $group->pictogram, $this->service()->all()));
    }

    #[Test]
    public function an_edit_goes_over_the_config_field_by_field(): void
    {
        PaymentGroup::query()->create([
            'key' => 'online',
            'title' => ['en' => 'Pay right now', 'de' => ' '],
            'description' => ['en' => 'Card or wallet'],
            'pictogram' => 'wallet',
        ]);
        PaymentGroup::query()->create(['key' => 'transfer']);

        $groups = $this->byKey();

        $this->assertSame('Pay right now', $groups['online']->title('en'));
        $this->assertSame('Card or wallet', $groups['online']->description('en'));
        $this->assertSame('wallet', $groups['online']->pictogram);

        // A language the manager did not write reads the config's, not the manager's other one.
        $this->assertSame('Оплата онлайн', $groups['online']->title('ru'));
        $this->assertSame('Online bezahlen', $groups['online']->title('de'));

        // An empty row changes nothing.
        $this->assertSame('Bank transfer', $groups['transfer']->title('en'));
        $this->assertSame('document', $groups['transfer']->pictogram);
    }

    #[Test]
    public function the_groups_go_by_position_then_as_in_the_config(): void
    {
        PaymentGroup::query()->create(['key' => 'transfer', 'position' => 1]);
        PaymentGroup::query()->create(['key' => 'online', 'position' => 2]);

        $this->assertSame(['transfer', 'online', 'on_receipt'], $this->keysOf($this->service()->all()));

        PaymentGroup::query()->where('key', 'online')->first()?->update(['position' => null]);

        $this->assertSame(['transfer', 'on_receipt', 'online'], $this->keysOf($this->service()->all()));
    }

    #[Test]
    public function a_row_of_a_group_gone_from_the_config_is_ignored_until_the_key_comes_back(): void
    {
        PaymentGroup::query()->create(['key' => 'transfer', 'title' => ['en' => 'By invoice'], 'position' => 1]);

        $this->listGroups(['on_receipt' => ['title' => 'On receipt'], 'online' => ['title' => 'Online']]);

        $this->assertSame(['on_receipt', 'online'], $this->keysOf($this->service()->all()));
        $this->assertSame(1, PaymentGroup::query()->where('key', 'transfer')->count());

        $this->listGroups(['online' => ['title' => 'Online'], 'transfer' => ['title' => 'Transfer']]);

        $this->assertSame(['transfer', 'online'], $this->keysOf($this->service()->all()));
        $this->assertSame('By invoice', $this->byKey()['transfer']->title('en'));
    }

    #[Test]
    public function an_option_says_its_group(): void
    {
        $option = $this->option(BankTransfer::METHOD);

        $this->assertSame('transfer', $option->group);
        $this->assertSame('transfer', $option->toArray()['group']);
    }

    #[Test]
    public function an_option_on_a_site_without_groups_has_none(): void
    {
        $this->listGroups([]);

        $this->assertNull($this->option(BankTransfer::METHOD)->group);
    }

    #[Test]
    public function the_list_is_grouped_and_a_group_with_nothing_to_offer_is_left_out(): void
    {
        $views = $this->methods()->grouped([$this->option(BankTransfer::METHOD), $this->option(OnReceipt::METHOD)]);

        $this->assertSame(['on_receipt', 'transfer'], array_map(static fn (MethodGroupView $view): string => $view->key(), $views));
        $this->assertSame([[OnReceipt::METHOD], [BankTransfer::METHOD]], $this->optionKeys($views));
    }

    #[Test]
    public function inside_a_group_the_methods_go_by_their_position(): void
    {
        PaymentMethod::query()->where('key', OnReceipt::METHOD)->update(['group_key' => 'transfer', 'position' => 30]);

        $views = $this->methods()->grouped([$this->option(OnReceipt::METHOD), $this->option(BankTransfer::METHOD)]);

        $this->assertSame([[BankTransfer::METHOD, OnReceipt::METHOD]], $this->optionKeys($views));
    }

    #[Test]
    public function methods_of_one_group_stand_together_whatever_stood_between_them(): void
    {
        PaymentMethod::query()->where('key', OnReceipt::METHOD)->update(['position' => 2]);
        PaymentMethod::query()->where('key', BankTransfer::METHOD)->update(['position' => 1]);
        $this->copy(BankTransfer::METHOD, 'second-transfer', 3);

        $views = $this->methods()->grouped([$this->option(BankTransfer::METHOD), $this->option(OnReceipt::METHOD), $this->option('second-transfer')]);

        $this->assertSame([[OnReceipt::METHOD], [BankTransfer::METHOD, 'second-transfer']], $this->optionKeys($views));
    }

    #[Test]
    public function an_empty_list_gives_no_groups(): void
    {
        $this->assertSame([], $this->methods()->grouped([]));
    }

    #[Test]
    public function an_empty_config_is_a_flat_list_in_the_order_of_the_methods(): void
    {
        $this->listGroups([]);

        $views = $this->methods()->grouped([$this->option(BankTransfer::METHOD), $this->option(OnReceipt::METHOD)]);

        $this->assertSame([[OnReceipt::METHOD, BankTransfer::METHOD]], $this->optionKeys($views));
        $this->assertSame([], $this->service()->all());
    }

    #[Test]
    public function no_change_of_the_config_loses_a_method(): void
    {
        foreach ([[], ['only' => ['title' => 'Only']], ['online' => ['title' => 'Online']], ['transfer' => ['title' => 'Transfer'], 'other' => ['title' => 'Other']]] as $config) {
            $this->listGroups($config);

            $views = $this->methods()->grouped([$this->option(BankTransfer::METHOD), $this->option(OnReceipt::METHOD)]);
            $all = array_merge(...$this->optionKeys($views));
            sort($all);

            $this->assertSame([BankTransfer::METHOD, OnReceipt::METHOD], $all);
        }
    }

    #[Test]
    public function a_warm_read_costs_no_query_and_only_the_rows_are_cached(): void
    {
        PaymentGroup::query()->create(['key' => 'online', 'position' => 1]);
        $this->service()->all();

        $this->assertQueriesWithin(0, fn (): array => $this->service()->all());

        // The config is read past the cache: a new deploy needs no reset.
        $this->listGroups(['online' => ['title' => 'Fresh words']]);

        $this->assertSame('Fresh words', $this->assertQueriesWithin(0, fn (): array => $this->service()->all())[0]->title('en'));
    }

    #[Test]
    public function which_group_a_method_is_in_asks_nothing_of_the_database(): void
    {
        $type = $this->types()->get(BankTransfer::KEY);

        $this->assertQueriesWithin(0, fn (): ?string => $this->service()->of(self::method(), $type));
    }

    #[Test]
    public function the_step_pays_no_query_for_the_groups_on_a_warm_cache(): void
    {
        PaymentMethod::query()->where('key', BankTransfer::METHOD)->update(['enabled' => true]);
        $context = new PaymentContext(4999, 'EUR', 'en', null, Purpose::Checkout);
        $this->methods()->grouped($this->methods()->available($context));

        $views = $this->assertQueriesWithin(0, fn (): array => $this->methods()->grouped($this->methods()->available($context)));

        $this->assertSame([[BankTransfer::METHOD]], $this->optionKeys($views));
    }

    #[Test]
    public function an_edit_is_seen_at_once(): void
    {
        $row = PaymentGroup::query()->create(['key' => 'online', 'title' => ['en' => 'Before']]);
        $this->assertSame('Before', $this->byKey()['online']->title('en'));

        $row->update(['title' => ['en' => 'After']]);
        $this->assertSame('After', $this->byKey()['online']->title('en'));

        $row->delete();
        $this->assertSame('Pay online', $this->byKey()['online']->title('en'));
    }

    #[Test]
    public function a_move_is_seen_at_once_through_the_cached_methods(): void
    {
        PaymentMethod::query()->where('key', BankTransfer::METHOD)->update(['enabled' => true]);
        $this->assertSame('transfer', $this->option(BankTransfer::METHOD)->group);

        PaymentMethod::query()->where('key', BankTransfer::METHOD)->first()?->update(['group_key' => 'online']);

        $method = $this->methods()->find(BankTransfer::METHOD);

        $this->assertSame('online', $method?->groupKey);
    }

    #[Test]
    public function an_entry_cached_before_the_column_came_reads_as_no_move(): void
    {
        $row = PaymentMethod::query()->where('key', BankTransfer::METHOD)->firstOrFail();
        $cached = Method::row($row);
        unset($cached['group_key']);

        $this->assertNull(Method::fromArray($cached)->groupKey);
    }

    #[Test]
    public function every_pictogram_of_the_set_is_drawn(): void
    {
        foreach (MethodGroups::PICTOGRAMS as $name) {
            $html = Blade::render('<x-webx-payments::pictogram :name="$name" />', ['name' => $name]);

            $this->assertStringContainsString('class="webx-payments__pictogram"', $html, $name);
            $this->assertStringContainsString('stroke="currentColor"', $html, $name);
            $this->assertStringContainsString('aria-hidden="true"', $html, $name);
            $this->assertMatchesRegularExpression('/<(path|rect|circle) /', $html, $name);
        }
    }

    #[Test]
    public function no_pictogram_draws_nothing(): void
    {
        $this->assertSame('', trim(Blade::render('<x-webx-payments::pictogram :name="$name" />', ['name' => 'rocket'])));
        $this->assertSame('', trim(Blade::render('<x-webx-payments::pictogram :name="$name" />', ['name' => null])));
    }

    private static function method(?string $group = null): Method
    {
        return new Method('bank-transfer', BankTransfer::KEY, [], [], null, true, 1, ['mode' => 'any'], null, null, [], false, $group);
    }

    /** @param  array<string, array<string, string>>  $groups */
    private function listGroups(array $groups): void
    {
        $this->app['config']->set('webx-payments.groups', $groups);
    }

    private function service(): MethodGroups
    {
        return $this->app->make(MethodGroups::class);
    }

    private function methods(): Methods
    {
        return $this->app->make(Methods::class);
    }

    private function types(): MethodTypes
    {
        return $this->app->make(MethodTypes::class);
    }

    /** An option of a method whether or not it is switched on: the list takes what it is given. */
    private function option(string $key): MethodOption
    {
        $method = $this->methods()->any($key);
        $this->assertInstanceOf(Method::class, $method);

        return $this->methods()->option($method, new PaymentContext(4999, 'EUR', 'en', null, Purpose::Checkout));
    }

    private function copy(string $key, string $as, int $position): void
    {
        $row = PaymentMethod::query()->where('key', $key)->firstOrFail()->replicate();
        $row->key = $as;
        $row->position = $position;
        $row->save();
    }

    /** @return array<string, MethodGroup> */
    private function byKey(): array
    {
        $groups = [];

        foreach ($this->service()->all() as $group) {
            $groups[$group->key] = $group;
        }

        return $groups;
    }

    /**
     * @param  list<MethodGroup>  $groups
     * @return list<string>
     */
    private function keysOf(array $groups): array
    {
        return array_map(static fn (MethodGroup $group): string => $group->key, $groups);
    }

    /**
     * @param  list<MethodGroupView>  $views
     * @return list<list<string>>
     */
    private function optionKeys(array $views): array
    {
        return array_map(static fn (MethodGroupView $view): array => array_map(static fn (MethodOption $option): string => $option->key(), $view->options), $views);
    }
}
