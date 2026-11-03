<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Recording;

/**
 * Makes a recorded answer of the carrier safe to keep in the repository (§13.1).
 *
 * Everywhere: no `apiKey`, every phone becomes `+800 1234 5678`, every e-mail
 * `sender@example.com`, the list of records is cut to a few.
 *
 * Answers of the account (senders, contacts, waybills, tracking) belong to the client: there every
 * string of `data` is replaced — ids by fake UUIDs that keep their links inside the file, names by
 * «Відправник А» / «Контакт А», codes by `00000000`, addresses by «вул. Прикладна, 1», anything
 * else by «Приклад». Only a short list of the carrier's own enumerations stays as it came.
 *
 * Public directories (localities, points, streets) stay as they came apart from phones: they are
 * the carrier's published data, and the parsing of the next stages is checked on them.
 */
final class Scrubber
{
    public const string PHONE = '+800 1234 5678';

    public const string EMAIL = 'sender@example.com';

    public const string CODE = '00000000';

    public const string ADDRESS = 'вул. Прикладна, 1';

    public const string CITY = 'Місто А';

    public const string OTHER = 'Приклад';

    /** Models whose answers are the data of the account behind the key. */
    public const array ACCOUNT_MODELS = ['Counterparty', 'ContactPerson', 'InternetDocument', 'TrackingDocument'];

    /** The fake range of ids: `00000000-0000-4000-8000-` and twelve digits. */
    public const string FAKE_UUID = '/^00000000-0000-4000-8000-\d{12}$/';

    public const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /** The carrier's own words that say nothing about the account. */
    private const array ENUMERATIONS = [
        'CounterpartyType',
        'CounterpartyProperty',
        'OwnershipFormDescription',
        'TrustedCounterpartyType',
    ];

    /** @var array<string, string> */
    private array $ids = [];

    /** @var array<string, array<string, string>> */
    private array $names = [];

    /**
     * @param  int|null  $keep  how many records of `data` to keep; null — all
     */
    public function scrub(string $model, string $method, mixed $body, ?int $keep = 20): mixed
    {
        $this->ids = [];
        $this->names = [];

        if (! is_array($body)) {
            return $body;
        }

        if (isset($body['data']) && is_array($body['data']) && array_is_list($body['data']) && $keep !== null) {
            $body['data'] = array_slice($body['data'], 0, $keep);
        }

        if (in_array($model, self::ACCOUNT_MODELS, true) && isset($body['data']) && is_array($body['data'])) {
            $body['data'] = $this->account($body['data'], $method);
        }

        return $this->everywhere($body);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function everywhere(array $value): array
    {
        $clean = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && strcasecmp($key, 'apiKey') === 0) {
                continue;
            }

            if (is_array($item)) {
                $clean[$key] = $this->everywhere($item);
            } elseif (is_string($item) && $item !== '') {
                $clean[$key] = $this->text(is_string($key) ? $key : '', $item);
            } else {
                $clean[$key] = $item;
            }
        }

        return $clean;
    }

    private function text(string $key, string $value): string
    {
        if (preg_match('/phone/i', $key) === 1) {
            return self::PHONE;
        }

        if (preg_match('/e-?mail/i', $key) === 1 || filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            return self::EMAIL;
        }

        // A phone inside a text: +380 and nine digits, with or without separators.
        return (string) preg_replace('/\+?\s?38\s?\(?0\d{2}\)?[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2}/', self::PHONE, $value);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function account(array $value, string $method, string $parent = ''): array
    {
        $clean = [];

        foreach ($value as $key => $item) {
            $name = is_string($key) ? $key : $parent;

            if (is_array($item)) {
                $clean[$key] = $this->account($item, $method, $name);
            } elseif (is_string($item) && $item !== '') {
                $clean[$key] = $this->accountText($name, $item, $method);
            } else {
                $clean[$key] = $item;
            }
        }

        return $clean;
    }

    private function accountText(string $key, string $value, string $method): string
    {
        if (preg_match(self::UUID, $value) === 1) {
            return $this->ids[strtolower($value)] ??= sprintf('00000000-0000-4000-8000-%012d', count($this->ids) + 1);
        }

        if (in_array($key, self::ENUMERATIONS, true)) {
            return $value;
        }

        if (preg_match('/phone/i', $key) === 1) {
            return self::PHONE;
        }

        if (preg_match('/e-?mail/i', $key) === 1) {
            return self::EMAIL;
        }

        if (preg_match('/edrpou|okpo|ipn|inn$|code/i', $key) === 1) {
            return self::CODE;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$|^\d{2}\.\d{2}\.\d{4}( \d{2}:\d{2}(:\d{2})?)?$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^(Description|FullName|LastName|FirstName|MiddleName|Name)$/i', $key) === 1) {
            return $this->name($key, $value, $method);
        }

        if (preg_match('/address|street|building|flat|house/i', $key) === 1) {
            return self::ADDRESS;
        }

        if (preg_match('/city|settlement|area|region/i', $key) === 1) {
            return self::CITY;
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $value) === 1 && strlen($value) < 8) {
            return $value;
        }

        return self::OTHER;
    }

    /** The same name gets the same letter inside one file: «Відправник А», «Контакт Б». */
    private function name(string $key, string $value, string $method): string
    {
        $who = match (true) {
            strcasecmp($key, 'LastName') === 0 => 'Прізвище',
            strcasecmp($key, 'FirstName') === 0 => 'Імʼя',
            strcasecmp($key, 'MiddleName') === 0 => 'По батькові',
            str_contains($method, 'ContactPerson') => 'Контакт',
            str_contains($method, 'Counterpart') => 'Відправник',
            default => 'Одержувач',
        };
        $letters = ['А', 'Б', 'В', 'Г', 'Д', 'Е', 'Є', 'Ж', 'З', 'И', 'І', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т'];
        $known = $this->names[$who] ?? [];

        if (! isset($known[$value])) {
            $known[$value] = $who.' '.($letters[count($known)] ?? (string) (count($known) + 1));
            $this->names[$who] = $known;
        }

        return $known[$value];
    }
}
