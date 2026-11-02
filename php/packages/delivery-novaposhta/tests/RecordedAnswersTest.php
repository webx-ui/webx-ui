<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\NovaPoshta\Api\ApiError;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Delivery\Sync\SyncFailure;

/**
 * The client on answers recorded on a real key (stage 3, §13.1): the facts that the stages after
 * build on — numbers as strings, the end of a list, the refusal of a key, the limit of requests.
 */
final class RecordedAnswersTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function recorded(string $name): array
    {
        $body = json_decode((string) file_get_contents(__DIR__.'/Fixtures/api/'.$name.'.json'), true);
        self::assertIsArray($body);

        /** @var array<string, mixed> $body */
        return $body;
    }

    private function client(): Client
    {
        return $this->app->make(Client::class);
    }

    private function failure(string $model, string $method): CarrierUnavailable
    {
        try {
            $this->client()->call(self::KEY, $model, $method, ['Page' => 1]);
        } catch (CarrierUnavailable $failure) {
            return $failure;
        }

        $this->fail('The call was expected to fail.');
    }

    #[Test]
    public function numbers_go_as_strings_which_is_what_the_carrier_takes(): void
    {
        Http::fake([self::URL => Http::response(self::recorded('Address.getCities.page-1'))]);

        $answer = $this->client()->call(self::KEY, 'Address', 'getCities', ['Page' => 1, 'Limit' => 500, 'Nested' => ['Weight' => 0.5]]);

        Http::assertSent(static fn (Request $request): bool => $request['methodProperties'] === ['Page' => '1', 'Limit' => '500', 'Nested' => ['Weight' => '0.5']]);
        $this->assertSame(11232, $answer->totalCount);
        $this->assertSame('8d5a980d-391c-11dd-90d9-001a92567626', self::recorded('Address.getCities.find-by-string')['data'][0]['Ref']);
    }

    #[Test]
    public function a_number_where_the_carrier_wants_a_string_is_its_logical_error(): void
    {
        Http::fake([self::URL => Http::response(self::recorded('Address.getCities.page-as-number'))]);

        $failure = $this->failure('Address', 'getCities');

        $this->assertSame(SyncFailure::NoAnswer, $failure->failure);
        $this->assertInstanceOf(ApiError::class, $failure->getPrevious());
        $this->assertSame(['Page is invalid format'], $failure->getPrevious()->errors);
    }

    #[Test]
    public function a_list_ends_with_an_empty_page_that_still_says_the_total(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(self::recorded('Address.getWarehouses.network-last-page'))
            ->push(self::recorded('Address.getWarehouses.network-past-the-end'))]);

        $last = $this->client()->call(self::KEY, 'Address', 'getWarehouses', ['Page' => 111, 'Limit' => 500]);
        $past = $this->client()->call(self::KEY, 'Address', 'getWarehouses', ['Page' => 112, 'Limit' => 500]);

        $this->assertNotSame([], $last->records());
        $this->assertSame([], $past->records());
        $this->assertSame(55243, $past->totalCount);
    }

    #[Test]
    public function an_empty_search_string_gives_every_street_of_the_locality(): void
    {
        $answer = self::recorded('Address.getStreet.village-empty-find');

        $this->assertTrue($answer['success']);
        $this->assertCount($answer['info']['totalCount'], $answer['data']);
        $this->assertSame(['Description', 'Ref', 'StreetsTypeRef', 'StreetsType'], array_keys($answer['data'][0]));
    }

    #[Test]
    public function an_unknown_key_is_refused_as_a_key(): void
    {
        Http::fake([self::URL => Http::response(self::recorded('Counterparty.getCounterparties.key-refused'))]);

        $failure = $this->failure('Counterparty', 'getCounterparties');

        $this->assertSame(SyncFailure::KeyRejected, $failure->failure);
        $this->assertInstanceOf(ApiError::class, $failure->getPrevious());
        $this->assertSame(['20000201204'], $failure->getPrevious()->codes);
    }

    #[Test]
    public function too_many_requests_is_no_answer_so_the_base_repeats_the_page(): void
    {
        Http::fake([self::URL => Http::response(self::recorded('Address.getWarehouses.rate-limited'))]);

        $failure = $this->failure('Address', 'getWarehouses');

        $this->assertSame(SyncFailure::NoAnswer, $failure->failure);
        $this->assertInstanceOf(ApiError::class, $failure->getPrevious());
        $this->assertSame(['To many requests'], $failure->getPrevious()->errors);
        $this->assertSame(['20000401501'], $failure->getPrevious()->codes);
    }
}
