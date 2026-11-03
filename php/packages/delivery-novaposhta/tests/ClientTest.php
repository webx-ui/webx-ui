<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Delivery\NovaPoshta\Api\ApiError;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Methods;
use WebxUi\Delivery\NovaPoshta\Api\WriteRefused;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Delivery\Sync\SyncFailure;

/**
 * The client (§3.4, §13.2) on draft answers after the documentation: the answer is read by
 * `success`, not by the HTTP code; a 303 to a cached file is followed; failures come in the base's
 * words; read-only mode refuses a writing method before any request; the key reaches nothing but
 * the request body.
 */
final class ClientTest extends TestCase
{
    private function client(bool $readOnly = false): Client
    {
        $this->app['config']->set('webx-delivery-novaposhta.read_only', $readOnly);
        $this->app->forgetInstance(Client::class);

        return $this->app->make(Client::class);
    }

    private function failure(callable $call): CarrierUnavailable
    {
        try {
            $call();
        } catch (CarrierUnavailable $failure) {
            return $failure;
        }

        $this->fail('The call was expected to fail with CarrierUnavailable.');
    }

    #[Test]
    public function a_call_posts_the_model_method_and_properties_to_the_one_address(): void
    {
        Http::fake([self::URL => Http::response(self::answer([['Ref' => 'city-1', 'Description' => 'Місто А']], total: 1))]);

        $answer = $this->client()->call(self::KEY, 'Address', 'getCities', ['Page' => 2, 'Limit' => 500]);

        $this->assertSame([['Ref' => 'city-1', 'Description' => 'Місто А']], $answer->records());
        $this->assertSame(1, $answer->totalCount);

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === self::URL
            && $request['apiKey'] === self::KEY
            && $request['modelName'] === 'Address'
            && $request['calledMethod'] === 'getCities'
            && $request['methodProperties'] === ['Page' => '2', 'Limit' => '500']);

        $entry = $this->journalEntries()[0];
        $this->assertSame(['Address', 'getCities', 2, 'ok'], [$entry['model'], $entry['method'], $entry['page'], $entry['outcome']]);
    }

    #[Test]
    public function no_properties_go_as_an_object(): void
    {
        Http::fake([self::URL => Http::response(self::answer([['Ref' => 'type-1']]))]);

        $this->client()->call(self::KEY, 'Address', 'getWarehouseTypes');

        Http::assertSent(static fn (Request $request): bool => str_contains($request->body(), '"methodProperties":{}'));
    }

    #[Test]
    public function success_false_at_http_200_is_a_failure_with_the_carriers_words(): void
    {
        Http::fake([self::URL => Http::response(self::answer(success: false, errors: ['Document number is not correct']))]);

        $failure = $this->failure(fn () => $this->client()->call(self::KEY, 'TrackingDocument', 'getStatusDocuments', ['Documents' => [['DocumentNumber' => '20000000000001']]]));

        $this->assertSame(SyncFailure::NoAnswer, $failure->failure);
        $this->assertInstanceOf(ApiError::class, $failure->getPrevious());
        $this->assertSame(['Document number is not correct'], $failure->getPrevious()->errors);
        $this->assertSame('error', $this->journalEntries()[0]['outcome']);
    }

    #[Test]
    public function errors_with_success_true_are_a_failure_too(): void
    {
        Http::fake([self::URL => Http::response(self::answer(success: true, errors: ['Something went wrong']))]);

        $failure = $this->failure(fn () => $this->client()->call(self::KEY, 'Address', 'getCities', ['Page' => 1]));

        $this->assertSame(SyncFailure::NoAnswer, $failure->failure);
    }

    #[Test]
    public function a_refused_key_is_key_rejected(): void
    {
        Http::fake([self::URL => Http::response(self::answer(success: false, errors: ['API key expired']))]);

        $failure = $this->failure(fn () => $this->client()->call(self::KEY, 'Counterparty', 'getCounterparties', ['CounterpartyProperty' => 'Sender', 'Page' => 1]));

        $this->assertSame(SyncFailure::KeyRejected, $failure->failure);
        $this->assertSame('key', $this->journalEntries()[0]['outcome']);
    }

    #[Test]
    public function an_empty_key_is_refused_without_a_request(): void
    {
        Http::fake();

        $failure = $this->failure(fn () => $this->client()->call('  ', 'Address', 'getCities'));

        $this->assertSame(SyncFailure::KeyRejected, $failure->failure);
        Http::assertNothingSent();
    }

    #[Test]
    public function a_303_to_a_cached_file_is_followed_and_read_the_same(): void
    {
        $file = 'https://cache.novaposhta.test/warehouses/city-1.json';

        Http::fake([
            self::URL => Http::response('', 303, ['Location' => $file]),
            $file => Http::response(self::answer([['Ref' => 'wh-1', 'Number' => '1']])),
        ]);

        $answer = $this->client()->call(self::KEY, 'Address', 'getWarehouses', ['Page' => 1, 'Limit' => 500]);

        $this->assertSame('wh-1', $answer->records()[0]['Ref']);
        Http::assertSentCount(2);
    }

    #[Test]
    public function a_timeout_is_no_answer(): void
    {
        Http::fake(static fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $failure = $this->failure(fn () => $this->client()->call(self::KEY, 'Address', 'getCities', ['Page' => 1]));

        $this->assertSame(SyncFailure::NoAnswer, $failure->failure);
        $this->assertSame('no_answer', $this->journalEntries()[0]['outcome']);
    }

    #[Test]
    public function a_server_error_is_no_answer(): void
    {
        Http::fake([self::URL => Http::response('Bad gateway', 502)]);

        $this->assertSame(SyncFailure::NoAnswer, $this->failure(fn () => $this->client()->call(self::KEY, 'Address', 'getCities'))->failure);
    }

    #[Test]
    public function an_answer_of_another_shape_is_bad_response(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push('<html>maintenance</html>', 200)
            ->push(['success' => true])]);

        $client = $this->client();

        $html = $this->failure(fn () => $client->call(self::KEY, 'Address', 'getCities'));
        $noData = $this->failure(fn () => $client->call(self::KEY, 'Address', 'getCities'));

        $this->assertSame([SyncFailure::BadResponse, SyncFailure::BadResponse], [$html->failure, $noData->failure]);
    }

    #[Test]
    public function in_read_only_mode_every_writing_method_is_refused_before_a_request(): void
    {
        Http::fake();
        $client = $this->client(readOnly: true);

        $this->assertTrue($client->readOnly());

        foreach (Methods::WRITE as $name) {
            [$model, $method] = explode('.', $name);

            try {
                $client->call(self::KEY, $model, $method, ['Page' => 1]);
                $this->fail("{$name} went through in read-only mode.");
            } catch (WriteRefused $refused) {
                $this->assertStringContainsString($name, $refused->getMessage());
            }
        }

        Http::assertNothingSent();
        $this->assertCount(count(Methods::WRITE), $this->journalEntries());
        $this->assertSame(['refused'], array_values(array_unique(array_column($this->journalEntries(), 'outcome'))));
    }

    #[Test]
    public function in_read_only_mode_reading_still_works(): void
    {
        Http::fake([self::URL => Http::response(self::answer([['Ref' => 'sender-1']]))]);

        $answer = $this->client(readOnly: true)->call(self::KEY, 'Counterparty', 'getCounterparties', ['CounterpartyProperty' => 'Sender', 'Page' => 1]);

        $this->assertCount(1, $answer->records());
        Http::assertSentCount(1);
    }

    #[Test]
    public function without_read_only_a_writing_method_goes_to_the_carrier(): void
    {
        Http::fake([self::URL => Http::response(self::answer([['Ref' => '00000000-0000-4000-8000-000000000001', 'IntDocNumber' => '20000000000001']]))]);

        $answer = $this->client()->call(self::KEY, 'InternetDocument', 'save', ['PayerType' => 'Recipient']);

        $this->assertSame('20000000000001', $answer->records()[0]['IntDocNumber']);
        Http::assertSentCount(1);
    }

    #[Test]
    public function a_method_on_neither_list_is_refused(): void
    {
        Http::fake();

        $this->expectException(WriteRefused::class);

        try {
            $this->client()->call(self::KEY, 'ScanSheet', 'insertDocuments');
        } finally {
            Http::assertNothingSent();
        }
    }

    #[Test]
    public function the_lists_do_not_overlap(): void
    {
        $this->assertSame([], array_values(array_intersect(Methods::READ, Methods::WRITE)));
    }

    #[Test]
    public function the_key_is_in_no_log_journal_or_exception(): void
    {
        $key = 'secret-key-0123456789abcdef';
        $logged = [];
        Event::listen(MessageLogged::class, static function (MessageLogged $message) use (&$logged): void {
            $logged[] = $message->message.' '.json_encode($message->context);
        });

        $answers = [
            Http::response(self::answer([['Ref' => 'city-1']])),
            Http::response(self::answer(success: false, errors: ['API key expired'])),
            Http::response(self::answer(success: false, errors: ['Something went wrong'])),
            Http::response('Bad gateway', 502),
            Http::response('<html></html>', 200),
            null,
        ];
        Http::fake(static function () use (&$answers) {
            return array_shift($answers) ?? throw new ConnectionException('timed out');
        });

        $client = $this->client();
        $messages = [];

        for ($call = 0; $call < 6; $call++) {
            try {
                $client->call($key, 'Address', 'getCities', ['Page' => $call + 1]);
            } catch (Throwable $failure) {
                for ($one = $failure; $one !== null; $one = $one->getPrevious()) {
                    $messages[] = $one->getMessage();
                }
            }
        }

        $this->assertNotSame([], $logged);
        $this->assertNotSame([], $messages);

        foreach ([...$logged, ...$messages, (string) file_get_contents($this->journal)] as $text) {
            $this->assertStringNotContainsString($key, $text);
        }
    }
}
