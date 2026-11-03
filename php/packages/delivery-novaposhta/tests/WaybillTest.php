<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\CarrierLocalities;
use WebxUi\Delivery\Contracts\CreatesWaybills;
use WebxUi\Delivery\Contracts\DescribesWaybill;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\NovaPoshta\Api\AccountKey;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Journal;
use WebxUi\Delivery\NovaPoshta\Api\Pace;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\NovaPoshta\Types\BranchType;
use WebxUi\Delivery\NovaPoshta\Types\CourierType;
use WebxUi\Delivery\NovaPoshta\Types\LockerType;
use WebxUi\Delivery\NovaPoshta\Waybills\Phone;
use WebxUi\Delivery\NovaPoshta\Waybills\RecipientName;
use WebxUi\Delivery\Shipping\Shipment;
use WebxUi\Delivery\Shipping\ShipmentStatus;
use WebxUi\Delivery\Shipping\WaybillRefused;
use WebxUi\Delivery\Shipping\WaybillRequest;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\SyncFailure;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Localization\Locales;

/**
 * The waybill of Nova Poshta (§8, decisions 4–5, 15, 17) and the options of its sender (§4.3, §5).
 *
 * The writing methods — `Counterparty.save`, `ContactPerson.save`, `Address.save`,
 * `InternetDocument.save` and `.delete` — run only on answers written after the documentation
 * (`*.documented-*.json`): they are never called on a client's key (decision 19). The sender and
 * its contacts are the answers recorded on the key (stage 3), the town and the branch of sending
 * are the recorded directory after a night.
 */
final class WaybillTest extends TestCase
{
    private const string SENDER = '00000000-0000-4000-8000-000000000001';

    private const string CONTACT = '00000000-0000-4000-8000-000000000001';

    /** Шумськ and its one branch, from the recorded directory (stage 3). */
    private const string TOWN = '00065646-4079-11de-b509-001d92f78698';

    private const string BRANCH = '1ec09d5d-e1c2-11e3-8c4a-0050568002cf';

    private const string LOCKER = '1b8378ea-d1ad-11ef-98f8-d4f5ef0df2b9';

    private const string STREET = '00000000-0000-4000-8000-000000000201';

    /** @var list<array{model: string, method: string, properties: array<string, mixed>}> */
    private array $sent = [];

    /** While true the carrier answers with the recorded directory: a night ({@see night()}). */
    private bool $directory = false;

    /** @var array<string, string> «Model.method» → the fixture it answers with */
    private array $answers = [
        'Counterparty.getCounterparties' => 'Counterparty.getCounterparties.senders',
        'Counterparty.getCounterpartyContactPersons' => 'Counterparty.getCounterpartyContactPersons.contacts',
        'Counterparty.save' => 'Counterparty.save.documented-recipient',
        'ContactPerson.save' => 'ContactPerson.save.documented-contact',
        'Address.save' => 'Address.save.documented-address',
        'InternetDocument.save' => 'InternetDocument.save.documented-waybill',
        'InternetDocument.delete' => 'InternetDocument.delete.documented-cancelled',
    ];

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('webx-localization.locales', [['code' => 'uk', 'default' => true], ['code' => 'en']]);
        // The panel in Ukrainian: the refusals are read in its words.
        $app['config']->set('app.locale', 'uk');
    }

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 10:00', 'Europe/Kyiv'));
        DeliveryCarrier::query()->create(['key' => NovaPoshtaCarrier::KEY, 'enabled' => true, 'credentials' => ['api_key' => self::KEY]]);
        // One fake for the whole test: a later `Http::fake()` would not win over this one.
        Http::fake([self::URL => fn (Request $request) => $this->reply($request)]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** Forget the calls so far. */
    private function fakeCarrier(): void
    {
        $this->sent = [];
    }

    /** Every call is noted; each method answers with its fixture, one without a fixture with HTTP 500. */
    private function reply(Request $request): mixed
    {
        if ($this->directory) {
            $page = (string) ($request['methodProperties']['Page'] ?? '');
            $towns = ['1' => 'Address.getCities.page-1', '2' => 'Address.getCities.page-2', '3' => 'Address.getCities.last-page'];

            if ($request['calledMethod'] === 'getWarehouses') {
                return Http::response(self::fixture($page === '1' ? 'Address.getWarehouses.small-city' : 'Address.getWarehouses.network-past-the-end'));
            }

            return Http::response(self::fixture($towns[$page] ?? 'Address.getCities.past-the-end'));
        }

        $name = $request['modelName'].'.'.$request['calledMethod'];
        $this->sent[] = ['model' => (string) $request['modelName'], 'method' => (string) $request['calledMethod'], 'properties' => (array) $request['methodProperties']];
        $answer = $this->answers[$name] ?? null;

        return $answer === null ? Http::response('', 500) : Http::response(self::fixture($answer));
    }

    /** @return list<string> */
    private function calls(): array
    {
        return array_map(static fn (array $call): string => $call['model'].'.'.$call['method'], $this->sent);
    }

    /** @return array<string, mixed> */
    private function sentOf(string $name): array
    {
        foreach ($this->sent as $call) {
            if ($name === $call['model'].'.'.$call['method']) {
                return $call['properties'];
            }
        }

        $this->fail("{$name} was not sent.");
    }

    private function type(string $key): CreatesWaybills&DescribesWaybill
    {
        $type = $this->app->make(MethodTypes::class)->get($key);
        $this->assertInstanceOf(CreatesWaybills::class, $type);
        $this->assertInstanceOf(DescribesWaybill::class, $type);

        return $type;
    }

    /**
     * @param  array<string, mixed>  $delivery
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $sender
     */
    private static function request(
        string $type = BranchType::KEY,
        array $delivery = [],
        string $first = 'Олена',
        string $last = 'Петрик-Мельник',
        ?string $phone = '+38 (050) 123-45-67',
        string $payer = WaybillRequest::RECIPIENT,
        ?int $cod = null,
        array $extra = [],
        array $sender = [],
        ?string $town = self::TOWN,
    ): WaybillRequest {
        return new WaybillRequest(
            orderId: 7,
            orderNumber: 'A-0007',
            delivery: [
                'carrier' => NovaPoshtaCarrier::KEY,
                'type' => $type,
                'method' => str_replace('.', '-', $type),
                'payer' => $payer,
                'point' => $type === CourierType::KEY ? null : ['external_id' => $type === LockerType::KEY ? self::LOCKER : self::BRANCH],
                'address' => $type === CourierType::KEY ? ['street' => 'Прикладна', 'street_id' => self::STREET, 'house' => '1', 'apartment' => '2'] : null,
                ...$delivery,
            ],
            recipientFirstName: $first,
            recipientLastName: $last,
            recipientPhone: $phone,
            recipientEmail: 'buyer@example.com',
            sender: [
                'sender' => self::SENDER,
                'contact' => self::CONTACT,
                'sender_city' => self::TOWN,
                'sender_point' => self::BRANCH,
                'payment_method' => 'NonCash',
                ...$sender,
            ],
            lines: [['name' => 'Item A', 'sku' => 'A-1', 'qty' => 1, 'price' => 125050]],
            declaredValue: 125050,
            currency: 'UAH',
            payer: $payer,
            cod: $cod,
            carrierLocality: $town,
            extra: [
                'weight' => 1.5,
                'seats' => 1,
                'cargo_type' => 'Parcel',
                'description' => 'Товари',
                ...($type === LockerType::KEY ? ['locker_width' => 30, 'locker_height' => 20, 'locker_length' => 40] : []),
                ...$extra,
            ],
        );
    }

    /** @param  callable(): mixed  $call */
    private function refused(callable $call, ?string $field = null): WaybillRefused
    {
        try {
            $call();
        } catch (WaybillRefused $refusal) {
            $this->assertSame($field, $refusal->field, $refusal->getMessage());

            return $refusal;
        }

        $this->fail('The waybill was not refused.');
    }

    /** The towns and the points of Шумськ: what the town and the branch of sending are chosen from. */
    private function night(): void
    {
        $this->app->instance(NovaPoshtaCarrier::class, new NovaPoshtaCarrier(
            $this->app->make(Client::class),
            new AccountKey,
            new Pace(0),
            $this->app->make(Locales::class),
            pageSize: 20,
        ));
        $this->directory = true;
        $sync = $this->app->make(Synchronizer::class);
        $this->assertSame(RunStatus::Succeeded, $sync->run(NovaPoshtaCarrier::KEY, Directory::Localities, Trigger::Command)->status);
        $this->assertSame(RunStatus::Succeeded, $sync->run(NovaPoshtaCarrier::KEY, Directory::Points, Trigger::Command)->status);
        $this->directory = false;
        $this->fakeCarrier();
    }

    private function carrier(): NovaPoshtaCarrier
    {
        return $this->app->make(NovaPoshtaCarrier::class);
    }

    // The sender's options (§4.3, §5)

    #[Test]
    public function the_sender_and_its_contact_persons_come_from_the_account_by_reading(): void
    {
        $senders = $this->carrier()->settingOptions('sender', [], ['api_key' => self::KEY]);

        $this->assertCount(1, $senders);
        $this->assertSame([self::SENDER, 'ФОП Відправник А', '00000000'], [$senders[0]->value, $senders[0]->label, $senders[0]->hint]);

        // The contact persons depend on the sender: none before it is chosen, and no request.
        $this->assertSame([], $this->carrier()->settingOptions('contact', [], ['api_key' => self::KEY]));
        $contacts = $this->carrier()->settingOptions('contact', ['sender' => self::SENDER], ['api_key' => self::KEY]);
        $this->assertSame(['Імʼя А Прізвище А', 'Імʼя А Прізвище А'], array_map(static fn ($option): string => $option->label, $contacts));
        $this->assertSame('+800 1234 5678', $contacts[0]->hint);
        $this->assertSame(['Ref' => self::SENDER, 'Page' => '1'], $this->sentOf('Counterparty.getCounterpartyContactPersons'));

        $this->assertSame(['Counterparty.getCounterparties', 'Counterparty.getCounterpartyContactPersons'], $this->calls());
        $this->assertSame(['ok', 'ok'], array_column($this->journalEntries(), 'outcome'));
    }

    #[Test]
    public function without_a_key_the_account_is_not_asked(): void
    {
        try {
            $this->carrier()->settingOptions('sender', [], []);
            $this->fail('No key, yet the options came.');
        } catch (CarrierUnavailable $failure) {
            $this->assertSame(SyncFailure::KeyRejected, $failure->failure);
        }

        $this->assertSame([], $this->calls());
    }

    #[Test]
    public function the_town_and_the_branch_of_sending_come_from_the_base_not_from_the_carrier(): void
    {
        $this->night();
        $carrier = $this->carrier();

        $towns = $carrier->settingOptions('sender_city', [], ['api_key' => self::KEY], 'Шумс');
        $this->assertSame([self::TOWN], array_map(static fn ($option): string => $option->value, $towns));
        $this->assertStringStartsWith('Шумськ', $towns[0]->label);

        // The base checks a stored value by asking with it.
        $this->assertSame([self::TOWN], array_map(static fn ($option): string => $option->value, $carrier->settingOptions('sender_city', [], [], self::TOWN)));
        $this->assertSame([], $carrier->settingOptions('sender_city', [], [], '00000000-0000-4000-8000-000000000999'));

        // A branch of the town; its lockers take no parcel in (decision 14).
        $this->assertSame([], $carrier->settingOptions('sender_point', [], [], ''));
        $branches = $carrier->settingOptions('sender_point', ['sender_city' => self::TOWN], [], '');
        $this->assertSame([self::BRANCH], array_map(static fn ($option): string => $option->value, $branches));
        // The name says the street already: no address repeated after it (NamesPointLines).
        $this->assertStringContainsString(':', $branches[0]->label);
        $this->assertNull($branches[0]->hint);
        $this->assertSame([self::BRANCH], array_map(static fn ($option): string => $option->value, $carrier->settingOptions('sender_point', ['sender_city' => self::TOWN], [], self::BRANCH)));
        $this->assertSame([], $carrier->settingOptions('sender_point', ['sender_city' => self::TOWN], [], self::LOCKER));

        $this->assertSame([], $this->calls(), 'The town and the branch never ask the carrier.');
    }

    #[Test]
    public function the_settings_ask_the_sender_chain_and_the_lockers_seat(): void
    {
        $settings = $this->carrier()->settings();
        $byName = array_column($settings, null, 'name');

        $this->assertSame(['sender', 'contact', 'sender_city', 'sender_point'], array_slice(array_column($settings, 'name'), 0, 4));
        $this->assertSame(['sender'], $byName['contact']['depends']);
        $this->assertSame(['sender_city'], $byName['sender_point']['depends']);
        $this->assertTrue($byName['sender_point']['search']);
        $this->assertSame([40, 30, 60], [$byName['locker_width']['max'], $byName['locker_height']['max'], $byName['locker_length']['max']]);
    }

    // The fields of the dialog (§8.1)

    #[Test]
    public function the_dialog_has_no_cash_on_delivery_and_a_locker_asks_its_seat(): void
    {
        foreach ([BranchType::KEY, LockerType::KEY, CourierType::KEY] as $key) {
            $type = $this->type($key);
            $this->assertFalse($type->supportsCod(), $key);

            foreach ($type->waybillFields() as $field) {
                $this->assertSame(NovaPoshtaCarrier::LOCALES, array_keys($field['label']), $key.' '.$field['name']);
                $this->assertNotSame('cod', $field['name']);
            }
        }

        $branch = array_column($this->type(BranchType::KEY)->waybillFields(), null, 'name');
        $this->assertSame(['weight', 'seats', 'cargo_type', 'description', 'date'], array_keys($branch));
        $this->assertSame(['Parcel', 'Cargo', 'Documents'], array_column($branch['cargo_type']['options'], 'value'));

        $locker = array_column($this->type(LockerType::KEY)->waybillFields(), null, 'name');
        $this->assertSame(['weight', 'cargo_type', 'locker_width', 'locker_height', 'locker_length', 'description', 'date'], array_keys($locker));
        $this->assertSame(['Parcel', 'Documents'], array_column($locker['cargo_type']['options'], 'value'));
        $this->assertSame(60, $locker['locker_length']['max']);
        $this->assertTrue($locker['locker_length']['required']);
    }

    #[Test]
    public function there_is_no_label_to_print(): void
    {
        $shipment = new Shipment(1, 7, NovaPoshtaCarrier::KEY, BranchType::KEY, 'novaposhta-branch', '20450000000001', 'recipient', ShipmentStatus::Created, ['ref' => 'x']);

        $this->assertNull($this->type(BranchType::KEY)->label($shipment));
        $this->assertSame([], $this->calls());
    }

    // The waybill (§8.2–§8.3)

    #[Test]
    public function a_branch_paid_by_the_recipient_goes_warehouse_to_warehouse_in_cash(): void
    {
        $waybill = $this->type(BranchType::KEY)->createWaybill(self::request());

        $this->assertSame(['Counterparty.getCounterpartyContactPersons', 'Counterparty.save', 'InternetDocument.save'], $this->calls());

        // The recipient as the order keeps the names: apart, as they are, no patronymic (decision 15).
        $this->assertSame([
            'FirstName' => 'Олена',
            'MiddleName' => '',
            'LastName' => 'Петрик-Мельник',
            'Phone' => '380501234567',
            'Email' => 'buyer@example.com',
            'CounterpartyType' => 'PrivatePerson',
            'CounterpartyProperty' => 'Recipient',
        ], $this->sentOf('Counterparty.save'));

        $document = $this->sentOf('InternetDocument.save');
        $this->assertSame('Recipient', $document['PayerType']);
        $this->assertSame('Cash', $document['PaymentMethod']);
        $this->assertSame('WarehouseWarehouse', $document['ServiceType']);
        $this->assertSame('02.11.2026', $document['DateTime']);
        $this->assertSame(['Parcel', '1.5', '1', 'Товари', '1251'], [$document['CargoType'], $document['Weight'], $document['SeatsAmount'], $document['Description'], $document['Cost']]);
        $this->assertSame([self::TOWN, self::SENDER, self::BRANCH, self::CONTACT, '80012345678'], [$document['CitySender'], $document['Sender'], $document['SenderAddress'], $document['ContactSender'], $document['SendersPhone']]);
        $this->assertSame([self::TOWN, '00000000-0000-4000-8000-000000000101', self::BRANCH, '00000000-0000-4000-8000-000000000103', '380501234567'], [$document['CityRecipient'], $document['Recipient'], $document['RecipientAddress'], $document['ContactRecipient'], $document['RecipientsPhone']]);
        $this->assertArrayNotHasKey('OptionsSeat', $document);
        $this->assertArrayNotHasKey('BackwardDeliveryData', $document);
        $this->assertArrayNotHasKey('AfterpaymentOnGoodsCost', $document);

        $this->assertSame('20450000000001', $waybill->number);
        $this->assertSame(7500, $waybill->cost);
        $this->assertSame(ShipmentStatus::Created, $waybill->status);
        $this->assertSame(['ref' => '00000000-0000-4000-8000-000000000106', 'estimated_at' => '2026-11-05'], $waybill->meta);
    }

    #[Test]
    public function the_shop_paying_sends_its_own_payment_method(): void
    {
        $this->type(BranchType::KEY)->createWaybill(self::request(payer: WaybillRequest::SENDER));

        $document = $this->sentOf('InternetDocument.save');
        $this->assertSame(['Sender', 'NonCash'], [$document['PayerType'], $document['PaymentMethod']]);
    }

    #[Test]
    public function a_courier_saves_the_address_and_goes_warehouse_to_doors(): void
    {
        $this->type(CourierType::KEY)->createWaybill(self::request(CourierType::KEY));

        $this->assertSame(['Counterparty.getCounterpartyContactPersons', 'Counterparty.save', 'Address.save', 'InternetDocument.save'], $this->calls());
        $this->assertSame([
            'CounterpartyRef' => '00000000-0000-4000-8000-000000000101',
            'StreetRef' => self::STREET,
            'BuildingNumber' => '1',
            'Flat' => '2',
            'Note' => '',
        ], $this->sentOf('Address.save'));
        $document = $this->sentOf('InternetDocument.save');
        $this->assertSame(['WarehouseDoors', '00000000-0000-4000-8000-000000000105'], [$document['ServiceType'], $document['RecipientAddress']]);
    }

    #[Test]
    public function a_locker_takes_one_seat_of_its_sizes(): void
    {
        $this->type(LockerType::KEY)->createWaybill(self::request(LockerType::KEY, extra: ['seats' => 3]));

        $document = $this->sentOf('InternetDocument.save');
        $this->assertSame(['WarehouseWarehouse', self::LOCKER, '1'], [$document['ServiceType'], $document['RecipientAddress'], $document['SeatsAmount']]);
        $this->assertSame([[
            'volumetricWidth' => '30',
            'volumetricHeight' => '20',
            'volumetricLength' => '40',
            'volumetricVolume' => '0.024',
            'weight' => '1.5',
        ]], $document['OptionsSeat']);
    }

    #[Test]
    public function a_locker_refuses_what_it_cannot_take_before_a_request(): void
    {
        $locker = $this->type(LockerType::KEY);

        $this->refused(fn () => $locker->createWaybill(self::request(LockerType::KEY, extra: ['locker_length' => 61])), 'extra.locker_length');
        $this->refused(fn () => $locker->createWaybill(self::request(LockerType::KEY, extra: ['locker_width' => null])), 'extra.locker_width');
        $this->refused(fn () => $locker->createWaybill(self::request(LockerType::KEY, extra: ['cargo_type' => 'Cargo'])), 'extra.cargo_type');
        $this->refused(fn () => $locker->createWaybill(self::request(LockerType::KEY, extra: ['weight' => 0])), 'extra.weight');

        $this->assertSame([], $this->calls());
    }

    #[Test]
    public function a_name_in_other_letters_is_marked_in_the_dialog_and_nothing_is_sent(): void
    {
        $branch = $this->type(BranchType::KEY);

        $refusal = $this->refused(fn () => $branch->createWaybill(self::request(first: 'Olena')), 'recipient_first_name');
        $this->assertSame('Служба приймає ім\'я лише українськими літерами.', $refusal->getMessage());
        // Russian letters are not Ukrainian ones.
        $this->refused(fn () => $branch->createWaybill(self::request(last: 'Мельникы')), 'recipient_last_name');
        $this->refused(fn () => $branch->createWaybill(self::request(last: '')), 'recipient_last_name');
        $this->refused(fn () => $branch->createWaybill(self::request(phone: '12345')), 'recipient_phone');
        $this->refused(fn () => $branch->createWaybill(self::request(phone: null)), 'recipient_phone');

        $this->assertSame([], $this->calls());

        // An apostrophe in any spelling, a hyphen, a double name.
        foreach (["Мар'яна", 'Марʼяна', 'Мар’яна', 'Ганна-Марія', 'Ольга Марія', 'Ґудзь', 'Їжак'] as $name) {
            $this->assertTrue(RecipientName::valid($name), $name);
        }

        foreach (['Olena', 'Пётр', 'Эмма', 'Ім1', '-Ім', "Ім''я"] as $name) {
            $this->assertFalse(RecipientName::valid($name), $name);
        }

        $this->assertSame('380501234567', Phone::normalise('0501234567'));
        $this->assertSame('380501234567', Phone::normalise('+380 50 123 45 67'));
        $this->assertNull(Phone::normalise('+800 1234 5678'));
    }

    #[Test]
    public function cash_on_delivery_is_refused_not_dropped(): void
    {
        $refusal = $this->refused(fn () => $this->type(BranchType::KEY)->createWaybill(self::request(cod: 1000)), 'cod');

        $this->assertSame('Накладений платіж у цій службі поки не підтримується.', $refusal->getMessage());
        $this->assertSame([], $this->calls());
    }

    #[Test]
    public function what_the_order_or_the_settings_lack_is_said_before_a_request(): void
    {
        $branch = $this->type(BranchType::KEY);

        $this->assertStringContainsString('відправника', $this->refused(fn () => $branch->createWaybill(self::request(sender: ['contact' => null])))->getMessage());
        $this->assertStringContainsString('міста замовлення', $this->refused(fn () => $branch->createWaybill(self::request(town: null)))->getMessage());
        $this->refused(fn () => $branch->createWaybill(self::request(delivery: ['point' => null])));
        $this->refused(fn () => $this->type(CourierType::KEY)->createWaybill(self::request(CourierType::KEY, delivery: ['address' => ['street_id' => null, 'house' => '1']])));
        $this->refused(fn () => $branch->createWaybill(self::request(extra: ['date' => '2026-11-01'])), 'extra.date');
        $this->refused(fn () => $branch->createWaybill(self::request(extra: ['date' => '2027-02-03'])), 'extra.date');
        $this->refused(fn () => $branch->createWaybill(self::request(extra: ['seats' => 1.5])), 'extra.seats');

        $this->assertSame([], $this->calls());

        // A date typed either way, up to three months ahead.
        $branch->createWaybill(self::request(extra: ['date' => '02.02.2027']));
        $this->assertSame('02.02.2027', $this->sentOf('InternetDocument.save')['DateTime']);
    }

    #[Test]
    public function a_recipient_without_a_contact_person_gets_one(): void
    {
        $this->answers['Counterparty.save'] = 'Counterparty.save.documented-recipient-without-contact';

        $this->type(BranchType::KEY)->createWaybill(self::request());

        $this->assertSame(['Counterparty.getCounterpartyContactPersons', 'Counterparty.save', 'ContactPerson.save', 'InternetDocument.save'], $this->calls());
        $this->assertSame('00000000-0000-4000-8000-000000000101', $this->sentOf('ContactPerson.save')['CounterpartyRef']);
        $this->assertSame('00000000-0000-4000-8000-000000000104', $this->sentOf('InternetDocument.save')['ContactRecipient']);
    }

    // Errors in words (§8.7)

    #[Test]
    public function the_carriers_refusals_are_said_in_words_with_its_text_and_code(): void
    {
        $branch = $this->type(BranchType::KEY);

        // A frequent one: the dictionary's words over the carrier's, the field marked.
        $this->answers['InternetDocument.save'] = 'InternetDocument.save.documented-phone-refused';
        $refusal = $this->refused(fn () => $branch->createWaybill(self::request()), 'recipient_phone');
        $this->assertSame('Служба приймає український мобільний номер: +380 і дев\'ять цифр. RecipientsPhone invalid format (20000900751)', $refusal->getMessage());

        // Any other: the carrier's own text and code, nothing hidden.
        $this->answers['InternetDocument.save'] = 'InternetDocument.save.documented-sender-refused';
        $refusal = $this->refused(fn () => $branch->createWaybill(self::request()));
        $this->assertSame('Служба відмовила: ContactSender not selected (20000200522)', $refusal->getMessage());

        // The recipient's name refused by the carrier itself marks the name.
        $this->answers['Counterparty.save'] = 'Counterparty.save.documented-name-refused';
        $this->refused(fn () => $branch->createWaybill(self::request()), 'recipient_first_name');
    }

    #[Test]
    public function no_answer_stays_the_bases_failure_and_a_contact_gone_is_said(): void
    {
        $branch = $this->type(BranchType::KEY);

        // HTTP 500: the base words it «the carrier did not answer».
        unset($this->answers['InternetDocument.save']);

        try {
            $branch->createWaybill(self::request());
            $this->fail('No answer, yet a waybill.');
        } catch (CarrierUnavailable $failure) {
            $this->assertSame(SyncFailure::NoAnswer, $failure->failure);
        }

        $this->fakeCarrier();
        $refusal = $this->refused(fn () => $branch->createWaybill(self::request(sender: ['contact' => '00000000-0000-4000-8000-000000000999'])));
        $this->assertStringContainsString('Контактної особи', $refusal->getMessage());
        $this->assertSame(['Counterparty.getCounterpartyContactPersons'], $this->calls());
    }

    // Cancelling (§8.6)

    #[Test]
    public function a_waybill_is_cancelled_by_its_ref_and_a_refusal_is_said(): void
    {
        $branch = $this->type(BranchType::KEY);
        $shipment = new Shipment(1, 7, NovaPoshtaCarrier::KEY, BranchType::KEY, 'novaposhta-branch', '20450000000001', 'recipient', ShipmentStatus::Created, ['ref' => '00000000-0000-4000-8000-000000000106']);

        $branch->cancelWaybill($shipment);
        $this->assertSame(['DocumentRefs' => '00000000-0000-4000-8000-000000000106'], $this->sentOf('InternetDocument.delete'));

        $this->answers['InternetDocument.delete'] = 'InternetDocument.delete.documented-refused';
        $this->assertSame('Служба відмовила: Document can not be deleted (20000100039)', $this->refused(fn () => $branch->cancelWaybill($shipment))->getMessage());

        $this->fakeCarrier();
        $this->refused(fn () => $branch->cancelWaybill(new Shipment(1, 7, NovaPoshtaCarrier::KEY, BranchType::KEY, 'novaposhta-branch', '1', 'recipient', ShipmentStatus::Created)));
        $this->assertSame([], $this->calls());
    }

    // A client's key (decision 19)

    #[Test]
    public function a_read_only_key_makes_and_cancels_nothing_and_sends_not_a_single_request(): void
    {
        $this->app->instance(Client::class, new Client($this->app->make(Factory::class), $this->app->make(Journal::class), self::URL, readOnly: true));
        $shipment = new Shipment(1, 7, NovaPoshtaCarrier::KEY, BranchType::KEY, 'novaposhta-branch', '20450000000001', 'recipient', ShipmentStatus::Created, ['ref' => '00000000-0000-4000-8000-000000000106']);

        foreach ([BranchType::KEY, LockerType::KEY, CourierType::KEY] as $key) {
            $refusal = $this->refused(fn () => $this->type($key)->createWaybill(self::request($key)));
            $this->assertSame('Цей сайт працює з ключем служби лише на читання: накладні вимкнено.', $refusal->getMessage());
            $this->refused(fn () => $this->type($key)->cancelWaybill($shipment));
        }

        // The sender's options still read.
        $this->assertCount(1, $this->carrier()->settingOptions('sender', [], ['api_key' => self::KEY]));

        $this->assertSame(['Counterparty.getCounterparties'], $this->calls());
        $this->assertSame([], array_filter(array_column($this->journalEntries(), 'method'), static fn (string $method): bool => in_array($method, ['save', 'update', 'delete'], true)));
    }

    #[Test]
    public function the_town_of_the_order_is_the_carriers_from_the_base(): void
    {
        $this->night();
        $locality = (int) DeliveryCarrierLocality::query()->where('carrier', NovaPoshtaCarrier::KEY)->where('external_id', self::TOWN)->value('locality_id');

        $this->assertSame(self::TOWN, $this->app->make(CarrierLocalities::class)->externalId(NovaPoshtaCarrier::KEY, $locality));
        $this->assertSame($locality, $this->app->make(CarrierLocalities::class)->localityId(NovaPoshtaCarrier::KEY, self::TOWN));
        $this->assertSame([$locality => self::TOWN], $this->app->make(CarrierLocalities::class)->forLocalities(NovaPoshtaCarrier::KEY, [$locality, 999999]));
    }
}
