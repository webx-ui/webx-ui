<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Methods;
use WebxUi\Delivery\NovaPoshta\Recording\Scrubber;

/**
 * `webx:novaposhta:record` (§13.1): reading methods only, read-only mode only, the key from the
 * environment and nowhere but the request; the file is scrubbed and the output has no data of the
 * account.
 */
final class RecordCommandTest extends TestCase
{
    private const string SECRET = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/novaposhta-record-'.bin2hex(random_bytes(6));
        putenv('NOVAPOSHTA_KEY='.self::SECRET);
        $this->readOnly(true);
    }

    protected function tearDown(): void
    {
        putenv('NOVAPOSHTA_KEY');
        array_map('unlink', glob($this->directory.'/*') ?: []);
        @rmdir($this->directory);

        parent::tearDown();
    }

    private function readOnly(bool $on): void
    {
        $this->app['config']->set('webx-delivery-novaposhta.read_only', $on);
        $this->app->forgetInstance(Client::class);
    }

    /** @param  array<string, mixed>  $options */
    private function record(string $model, string $method, array $options = []): int
    {
        return Artisan::call('webx:novaposhta:record', ['model' => $model, 'method' => $method, '--to' => $this->directory] + $options);
    }

    #[Test]
    public function no_writing_method_is_recorded_and_nothing_is_sent(): void
    {
        Http::fake();

        foreach (Methods::WRITE as $name) {
            [$model, $method] = explode('.', $name);

            $this->assertSame(1, $this->record($model, $method));
            $this->assertStringContainsString('not a reading method', Artisan::output());
        }

        Http::assertNothingSent();
        $this->assertSame([], $this->journalEntries());
    }

    #[Test]
    public function without_read_only_mode_nothing_is_sent(): void
    {
        Http::fake();
        $this->readOnly(false);

        $this->assertSame(1, $this->record('Address', 'getCities'));
        $this->assertStringContainsString('NOVAPOSHTA_READ_ONLY', Artisan::output());
        Http::assertNothingSent();
    }

    #[Test]
    public function an_answer_of_the_account_is_written_scrubbed_and_the_key_is_nowhere_but_the_request(): void
    {
        Http::fake([self::URL => Http::response(self::answer([[
            'Ref' => '3b0e7b2c-1d2e-11e9-8b24-005056881c6b',
            'Description' => 'Real Sender LLC',
            'City' => '8d5a980d-391c-11dd-90d9-001a92567626',
            'EDRPOU' => '12345678',
            'Phones' => '380501234567',
            'Email' => 'owner@shop.test',
            'CounterpartyType' => 'Organization',
        ]], total: 1))]);

        $this->assertSame(0, $this->record('Counterparty', 'getCounterparties', ['--case' => 'senders', '--props' => '{"CounterpartyProperty":"Sender","Page":1}']));
        $output = Artisan::output();

        Http::assertSent(static fn (Request $request): bool => $request['apiKey'] === self::SECRET
            && $request['methodProperties'] === ['CounterpartyProperty' => 'Sender', 'Page' => '1']);

        $file = (string) file_get_contents($this->directory.'/Counterparty.getCounterparties.senders.json');
        $record = json_decode($file, true)['data'][0];

        $this->assertSame([
            'Ref' => '00000000-0000-4000-8000-000000000001',
            'Description' => 'Відправник А',
            'City' => '00000000-0000-4000-8000-000000000002',
            'EDRPOU' => Scrubber::CODE,
            'Phones' => Scrubber::PHONE,
            'Email' => Scrubber::EMAIL,
            'CounterpartyType' => 'Organization',
        ], $record);

        foreach ([$file, $output, (string) file_get_contents($this->journal)] as $text) {
            $this->assertStringNotContainsString(self::SECRET, $text);
            $this->assertStringNotContainsString('Real Sender', $text);
            $this->assertStringNotContainsString('380501234567', $text);
        }

        $this->assertStringContainsString('fields: Ref, Description, City, EDRPOU, Phones, Email, CounterpartyType', $output);
        $this->assertSame(['Counterparty', 'getCounterparties', 1, 'ok'], array_values(array_intersect_key($this->journalEntries()[0], array_flip(['model', 'method', 'page', 'outcome']))));
    }

    #[Test]
    public function a_public_directory_keeps_its_data_but_not_phones_and_is_cut_to_a_few_records(): void
    {
        $points = array_map(static fn (int $n): array => ['Ref' => sprintf('1ec09d88-e1c2-11e3-8c4a-0050568002%02d', $n), 'Description' => "Відділення №{$n}", 'Phone' => '0800500609', 'CategoryOfWarehouse' => 'Branch'], range(1, 5));
        Http::fake([self::URL => Http::response(self::answer($points, total: 5))]);

        $this->assertSame(0, $this->record('Address', 'getWarehouses', ['--case' => 'city', '--keep' => 2]));

        $data = json_decode((string) file_get_contents($this->directory.'/Address.getWarehouses.city.json'), true)['data'];

        $this->assertCount(2, $data);
        $this->assertSame(['Ref' => $points[0]['Ref'], 'Description' => 'Відділення №1', 'Phone' => Scrubber::PHONE, 'CategoryOfWarehouse' => 'Branch'], $data[0]);
        $this->assertStringContainsString("CategoryOfWarehouse: 'Branch' ×5", Artisan::output());
    }

    #[Test]
    public function the_refusal_of_a_key_is_recorded_with_a_key_of_zeros(): void
    {
        Http::fake([self::URL => Http::response(self::answer(success: false, errors: ['API key expired']))]);

        $this->assertSame(0, $this->record('Address', 'getWarehouseTypes', ['--case' => 'key-refused', '--fake-key' => true]));

        Http::assertSent(static fn (Request $request): bool => $request['apiKey'] === str_repeat('0', 32));
        $this->assertSame('error', $this->journalEntries()[0]['outcome']);
    }
}
