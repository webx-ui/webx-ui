<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Recording;

use Illuminate\Console\Command;
use Illuminate\Support\Env;
use JsonException;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Methods;
use WebxUi\Delivery\Sync\CarrierUnavailable;

/**
 * Records one answer of the carrier into the package's fixtures, scrubbed (§13.1, stage 3).
 *
 * Only reading methods, and only with the client in read-only mode: the key may belong to a
 * client's account, and nothing in it may change. The key comes from the environment
 * (`NOVAPOSHTA_KEY`) and goes nowhere but the request — not to the file, the output or the log.
 *
 * The output is the shape of the answer, not its data: how many records, the total, the fields,
 * and — for public directories only — the values of fields with few of them.
 */
final class RecordCommand extends Command
{
    protected $signature = 'webx:novaposhta:record
        {model : Address, Counterparty, …}
        {method : getCities, getWarehouses, …}
        {--case= : the case in the file name: <Model>.<method>.<case>.json}
        {--props= : methodProperties as JSON (ASCII; for other letters --props-file)}
        {--props-file= : a file with methodProperties as JSON}
        {--keep=20 : how many records to keep in the file; 0 — write no file}
        {--fake-key : send a key of zeros instead of NOVAPOSHTA_KEY — to record the refusal}
        {--first-sender : put the Ref of the account\'s first sender into the properties (it is never shown)}
        {--to= : the directory of fixtures; the package\'s tests/Fixtures/api by default}';

    protected $description = 'Record a scrubbed answer of a reading method of Nova Poshta (development only)';

    public function handle(Client $client, Scrubber $scrubber): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('Recording answers is for a development copy, not production.');

            return self::FAILURE;
        }

        $model = (string) $this->argument('model');
        $method = (string) $this->argument('method');

        // Its own guard, whatever the client would allow: recording never writes.
        if (! Methods::reads($model, $method)) {
            $this->error(Methods::name($model, $method).' is not a reading method; only Api\\Methods::READ is recorded.');

            return self::FAILURE;
        }

        if (! $client->readOnly()) {
            $this->error('Turn read-only mode on first: NOVAPOSHTA_READ_ONLY=true.');

            return self::FAILURE;
        }

        $key = $this->option('fake-key') ? str_repeat('0', 32) : (string) Env::get('NOVAPOSHTA_KEY', '');

        if ($key === '') {
            $this->error('NOVAPOSHTA_KEY is not set.');

            return self::FAILURE;
        }

        try {
            $properties = $this->properties();
        } catch (JsonException) {
            $this->error('methodProperties are not a JSON object.');

            return self::FAILURE;
        }

        try {
            if ($this->option('first-sender')) {
                $senders = $client->call($key, 'Counterparty', 'getCounterparties', ['CounterpartyProperty' => 'Sender', 'Page' => '1']);
                $properties['Ref'] = $senders->records()[0]['Ref'] ?? '';
            }

            $answer = $client->raw($key, $model, $method, $properties);
        } catch (CarrierUnavailable $failure) {
            $this->error($failure->getMessage());

            return self::FAILURE;
        }

        $this->summary($model, $answer);

        $keep = (int) $this->option('keep');

        if ($keep > 0) {
            $case = (string) ($this->option('case') ?: 'default');
            $directory = (string) ($this->option('to') ?: dirname(__DIR__, 2).'/tests/Fixtures/api');
            $path = $directory.'/'.Methods::name($model, $method).'.'.$case.'.json';

            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $clean = $scrubber->scrub($model, $method, $answer['body'], $keep);
            file_put_contents($path, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            $this->line('written: '.basename($path));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function properties(): array
    {
        $file = $this->option('props-file');
        $json = is_string($file) && $file !== '' ? (string) file_get_contents($file) : (string) ($this->option('props') ?: '{}');
        $properties = json_decode($json, true, 16, JSON_THROW_ON_ERROR);

        if (! is_array($properties)) {
            throw new JsonException('not an object');
        }

        /** @var array<string, mixed> $properties */
        return $properties;
    }

    /**
     * The shape of the answer. Values only of public directories, and only of fields with few.
     *
     * @param  array{status: int, redirected: bool, body: mixed}  $answer
     */
    private function summary(string $model, array $answer): void
    {
        $body = is_array($answer['body']) ? $answer['body'] : [];
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $info = is_array($body['info'] ?? null) ? $body['info'] : [];
        $public = ! in_array($model, Scrubber::ACCOUNT_MODELS, true);

        $this->line('status: '.$answer['status'].($answer['redirected'] ? ' (redirected)' : ''));
        $this->line('top-level keys: '.implode(', ', array_keys($body)));
        $this->line('success: '.json_encode($body['success'] ?? null).', records: '.count($data).', info: '.json_encode($info));

        foreach (['errors', 'warnings', 'errorCodes', 'warningCodes', 'infoCodes', 'messageCodes'] as $list) {
            if (! empty($body[$list])) {
                $this->line($list.': '.json_encode($body[$list], JSON_UNESCAPED_UNICODE));
            }
        }

        /** @var array<string, array<string, int>> $values */
        $values = [];

        foreach ($data as $record) {
            if (! is_array($record)) {
                continue;
            }

            foreach ($record as $field => $value) {
                $values[(string) $field] ??= [];

                if ($public) {
                    $text = is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value, JSON_UNESCAPED_UNICODE);
                    $values[(string) $field][(string) $text] = ($values[(string) $field][(string) $text] ?? 0) + 1;
                }
            }
        }

        $this->line('fields: '.implode(', ', array_keys($values)));

        if ($public) {
            $this->localities($data);
        }

        foreach ($values as $field => $counts) {
            if ($counts !== [] && count($counts) <= 12) {
                arsort($counts);
                $this->line('  '.$field.': '.implode(' | ', array_map(static fn (string $value, int $count): string => mb_strimwidth($value, 0, 160, '…').' ×'.$count, array_keys($counts), $counts)));
            }
        }
    }

    /**
     * What stage 3 has to count on localities (§6.2, §6.4): how many have any `Delivery1…7` on,
     * and how many share their name with another one of the same area (decision 11).
     *
     * @param  array<mixed>  $data
     */
    private function localities(array $data): void
    {
        if ($data === []) {
            return;
        }

        $delivery = 0;
        $remarks = 0;
        $names = [];

        foreach ($data as $record) {
            if (! is_array($record) || ! array_key_exists('Delivery1', $record)) {
                return;
            }

            foreach (range(1, 7) as $day) {
                if ((string) ($record['Delivery'.$day] ?? '0') === '1') {
                    $delivery++;

                    break;
                }
            }

            // The carrier tells namesakes apart by a remark in brackets; the record's name goes without it (§6.2).
            $bare = (string) preg_replace('/\s*\(.*\)\s*$/u', '', (string) ($record['Description'] ?? ''));
            $remarks += $bare !== ($record['Description'] ?? '') ? 1 : 0;
            $name = ($record['AreaDescription'] ?? '').'|'.$bare;
            $names[$name] = ($names[$name] ?? 0) + 1;
        }

        $same = array_sum(array_filter($names, static fn (int $count): bool => $count > 1));
        $this->line("localities: any Delivery1…7 = 1 — {$delivery}; with a remark in brackets — {$remarks}; sharing the bare name and area with another — {$same}");
    }
}
