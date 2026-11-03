<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\NovaPoshta\Types\BranchType;
use WebxUi\Delivery\NovaPoshta\Types\CourierType;
use WebxUi\Delivery\NovaPoshta\Types\LockerType;
use WebxUi\Delivery\QuoteKind;

/**
 * The carrier and its types in the base's registries (§4, §7): named in words, its mark from the
 * package, the check of the connection by reading the account's senders, and the three methods
 * the migration adds switched off.
 */
final class CarrierTest extends TestCase
{
    private function carrier(): NovaPoshtaCarrier
    {
        $carrier = $this->app->make(Carriers::class)->get(NovaPoshtaCarrier::KEY);
        $this->assertInstanceOf(NovaPoshtaCarrier::class, $carrier);

        return $carrier;
    }

    #[Test]
    public function the_carrier_joins_the_registry_with_its_name_mark_and_country(): void
    {
        $carrier = $this->carrier();

        $this->assertSame('Nova Poshta', $carrier->title('en'));
        $this->assertSame('Nova Poshta', $carrier->title('de'));
        $this->assertSame('Нова пошта', $carrier->title('uk'));
        $this->assertSame(['UA'], $carrier->countries());
        $this->assertStringStartsWith('data:image/svg+xml;base64,', (string) $carrier->icon());
        $this->assertStringContainsString('<svg', base64_decode(substr((string) $carrier->icon(), 26)));
    }

    #[Test]
    public function the_key_is_the_only_secret_and_every_field_is_named_in_ten_languages(): void
    {
        $carrier = $this->carrier();

        $this->assertSame(['api_key'], array_column($carrier->credentials(), 'name'));
        $this->assertSame('secret', $carrier->credentials()[0]['type']);
        $this->assertSame(['sender', 'contact', 'sender_city', 'sender_point', 'payment_method', 'cargo_type', 'weight', 'description', 'locker_width', 'locker_height', 'locker_length'], array_column($carrier->settings(), 'name'));

        foreach ([...$carrier->settings(), ...$carrier->credentials()] as $field) {
            $this->assertSame(NovaPoshtaCarrier::LOCALES, array_keys($field['label']));
        }

        $cargo = $carrier->settings()[5];
        $this->assertSame(['Parcel', 'Cargo', 'Documents'], array_column($cargo['options'], 'value'));
    }

    #[Test]
    public function the_three_types_are_the_carriers_and_priced_by_its_rates(): void
    {
        $types = $this->app->make(MethodTypes::class);

        foreach ([BranchType::KEY => ['branch'], LockerType::KEY => ['locker'], CourierType::KEY => []] as $key => $kinds) {
            $type = $types->get($key);
            $this->assertSame(NovaPoshtaCarrier::KEY, $type->carrier());
            $this->assertSame($kinds, $type->pointKinds());
            $this->assertSame(['UA'], $type->countries());
        }

        $branch = $types->get(BranchType::KEY);
        $this->assertInstanceOf(BranchType::class, $branch);
        $this->assertSame('Nova Poshta branch', $branch->title('en'));
        $this->assertSame('Відділення Нової пошти', $branch->title('uk'));
        $this->assertSame('Поштомат Нової пошти', (new LockerType)->title('uk'));
        $this->assertSame("Кур'єр Нової пошти", (new CourierType)->title('uk'));
        $this->assertNotNull($branch->description('en'));
    }

    #[Test]
    public function a_method_without_a_price_is_by_the_carriers_rates_and_asks_no_api(): void
    {
        Http::fake();
        $method = Method::of(DeliveryMethod::query()->where('key', 'novaposhta-branch')->firstOrFail());
        // The quote reads nothing of the cart or the town: no context to build.
        $context = (new ReflectionClass(DeliveryContext::class))->newInstanceWithoutConstructor();

        $quote = (new BranchType)->quote($method, $context);

        $this->assertSame(QuoteKind::CarrierRates, $quote->kind);
        $this->assertNull($quote->amount);

        $priced = Method::of(DeliveryMethod::query()->where('key', 'novaposhta-branch')->firstOrFail()->fill(['price' => 7000]));
        $this->assertSame(7000, (new BranchType)->quote($priced, $context)->amount);
        Http::assertNothingSent();
    }

    #[Test]
    public function the_migration_adds_three_methods_switched_off_once(): void
    {
        $methods = DeliveryMethod::query()->where('key', 'like', 'novaposhta-%')->orderBy('position')->get();

        $this->assertSame(['novaposhta-branch', 'novaposhta-locker', 'novaposhta-courier'], $methods->pluck('key')->all());
        $this->assertSame([BranchType::KEY, LockerType::KEY, CourierType::KEY], $methods->pluck('type')->all());
        $this->assertSame(['branch', 'locker', null], $methods->pluck('point_kind')->all());

        foreach ($methods as $method) {
            $this->assertFalse($method->enabled);
            $this->assertNull($method->price);
            $this->assertTrue($method->honours_paid_by_store);
            $this->assertSame(['UA'], $method->countries);
            $this->assertSame(['en', 'uk'], array_keys($method->titles));
        }

        $this->assertSame('Nova Poshta branch', $methods[0]->titles['en']);

        // A second run adds nothing.
        $migration = require __DIR__.'/../database/migrations/2026_11_02_000000_add_novaposhta_methods.php';
        $migration->up();
        $this->assertSame(3, DeliveryMethod::query()->where('key', 'like', 'novaposhta-%')->count());
    }

    #[Test]
    public function the_connection_is_checked_by_reading_the_accounts_senders(): void
    {
        Http::fake([self::URL => Http::response(self::answer([['Ref' => 'sender-1'], ['Ref' => 'sender-2']], total: 2))]);

        $result = $this->carrier()->testConnection(['api_key' => self::KEY]);

        $this->assertTrue($result->ok);
        $this->assertSame('Senders in the account: 2', $result->message);
        Http::assertSent(static fn (Request $request): bool => $request['modelName'] === 'Counterparty'
            && $request['calledMethod'] === 'getCounterparties'
            && $request['methodProperties']['CounterpartyProperty'] === 'Sender');
    }

    #[Test]
    public function the_connection_fails_in_words(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(self::answer(success: false, errors: ['API key expired']))
            ->push('Bad gateway', 502)
            ->push(self::answer([], total: 0))]);

        $carrier = $this->carrier();

        $this->assertSame('The carrier did not accept the API key.', $carrier->testConnection(['api_key' => self::KEY])->message);
        $this->assertSame('The carrier did not answer.', $carrier->testConnection(['api_key' => self::KEY])->message);
        $this->assertSame("The carrier's account has no sender.", $carrier->testConnection(['api_key' => self::KEY])->message);
        $this->assertSame('Enter the API key first.', $carrier->testConnection([])->message);
        Http::assertSentCount(3);
    }

    #[Test]
    public function the_connection_check_works_in_read_only_mode(): void
    {
        $this->app['config']->set('webx-delivery-novaposhta.read_only', true);
        $this->app->forgetInstance(Client::class);
        $this->app->forgetInstance(NovaPoshtaCarrier::class);
        Http::fake([self::URL => Http::response(self::answer([['Ref' => 'sender-1']]))]);

        $this->assertTrue($this->app->make(NovaPoshtaCarrier::class)->testConnection(['api_key' => self::KEY])->ok);
    }
}
