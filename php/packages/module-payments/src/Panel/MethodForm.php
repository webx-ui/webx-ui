<?php

declare(strict_types=1);

namespace WebxUi\Payments\Panel;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Cart\Contracts\CurrencySource;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Delivery\DeliveryServiceProvider;
use WebxUi\Delivery\Methods as DeliveryMethods;
use WebxUi\Localization\Locales;
use WebxUi\Payments\ConnectionResult;
use WebxUi\Payments\Contracts\CapturesPayments;
use WebxUi\Payments\Contracts\ChargesOnline;
use WebxUi\Payments\Contracts\Gateway;
use WebxUi\Payments\Contracts\MethodType;
use WebxUi\Payments\Contracts\ValidatesSettings;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\DeliveryRule;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodCredentials;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\Words;

/**
 * The methods of the panel (spec §13.2, §13.3): a line of the list, the form of one method, its
 * save and «Test the connection».
 *
 * The described screen `payments.method-form` holds what every method has — the words, the rule of
 * delivery, the limits of the amount, on or off. What only a type has — the bank details, the
 * gateway's settings, the hold — comes as a list of fields beside it, and the gateway's keys as a
 * list of «set / not set»: the value of a key never leaves the server (§15.2), a blank field leaves
 * the stored one as it is, and only «Remove» clears it.
 */
final readonly class MethodForm
{
    public const string SCREEN = 'payments.method-form';

    /** The columns the screen writes; the rule and the amounts are put together from its fields. */
    private const array OWN = ['titles', 'descriptions', 'enabled', 'delivery_mode', 'delivery_methods', 'delivery_keys', 'min_amount', 'max_amount'];

    /** The setting a type that holds money keeps its switch under (§9.5). */
    public const string HOLD = 'hold';

    public function __construct(
        private ScreenRecord $record,
        private MethodTypes $types,
        private Gateways $gateways,
        private MethodCredentials $credentials,
        private GatewayCalls $calls,
        private PaymentLinks $links,
        private Locales $locales,
        private Money $money,
        private CurrencySource $currencies,
    ) {}

    /**
     * What «Add method» offers: every installed type.
     *
     * @return list<array<string, mixed>>
     */
    public function types(): array
    {
        $locale = $this->locales->current();
        $rows = [];

        foreach ($this->types->all() as $type) {
            $gateway = $this->gateway($type);
            $rows[] = [
                'key' => $type->key(),
                'title' => $this->typeTitle($type, $locale),
                'kind' => $type->kind()->value,
                'gateway' => $gateway?->key(),
                'gateway_title' => $gateway?->title($locale),
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed> a line of the list */
    public function row(PaymentMethod $method): array
    {
        $locale = $this->locales->current();
        $type = $this->types->has($method->type) ? $this->types->get($method->type) : null;
        $gateway = $type !== null ? $this->gateway($type) : null;
        $keys = $this->keyState($method, $type, $gateway);
        $warnings = [];

        if ($type === null) {
            $warnings[] = 'type_missing';
        } elseif ($type instanceof ChargesOnline && $gateway === null) {
            $warnings[] = 'gateway_missing';
        }

        if ($keys === 'missing' || $keys === 'unreadable') {
            $warnings[] = $keys === 'missing' ? 'no_credentials' : 'credentials_unreadable';
        }

        return [
            'id' => $method->id,
            'key' => $method->key,
            'type' => $method->type,
            'type_title' => $type !== null ? $this->typeTitle($type, $locale) : $method->type,
            'kind' => $type?->kind()->value,
            'title' => Words::pick($method->title, $locale) ?? ($type !== null ? $this->typeTitle($type, $locale) : $method->key),
            'gateway' => $gateway?->key(),
            'gateway_title' => $gateway?->title($locale),
            'mode' => $gateway !== null ? $this->mode($gateway, $method->settings) : null,
            // `set`, `environment`, `missing`, `unreadable` — or null: the type keeps no keys.
            'credentials' => $keys,
            'hold' => $type instanceof CapturesPayments && (bool) ($method->settings[self::HOLD] ?? false),
            'warnings' => $warnings,
            'enabled' => $method->enabled,
            'position' => $method->position,
        ];
    }

    /** @return array<string, mixed> */
    public function describe(PaymentMethod $method): array
    {
        $type = $this->types->has($method->type) ? $this->types->get($method->type) : null;

        return [
            'method' => $this->row($method),
            'values' => $this->values($method),
            ...$this->typeParts($type, $method),
        ];
    }

    /**
     * What a new method of a type opens with.
     *
     * @return array<string, mixed>
     */
    public function blank(string $typeKey): array
    {
        $type = $this->types->get($typeKey);

        return [
            'type_title' => $this->typeTitle($type, $this->locales->current()),
            ...$this->typeParts($type, null),
        ];
    }

    /**
     * Save the screen's values, the type's settings, the keys typed anew and the keys to remove.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>|null  $settings
     * @param  array<string, mixed>|null  $credentials
     * @param  list<string>|null  $clear
     * @param  (callable(string): bool)|null  $can
     */
    public function save(PaymentMethod $method, array $values, ?array $settings, ?array $credentials, ?array $clear, ?callable $can = null): PaymentMethod
    {
        $type = $this->types->get($method->type);
        $own = $this->record->split(self::SCREEN, $values, self::OWN, can: $can)->own;

        if (array_key_exists('titles', $own)) {
            $method->title = Words::clean(is_array($own['titles']) ? $own['titles'] : []);
        }

        if (array_key_exists('descriptions', $own)) {
            $method->description = Words::clean(is_array($own['descriptions']) ? $own['descriptions'] : []) ?: null;
        }

        if (array_key_exists('enabled', $own)) {
            $method->enabled = (bool) $own['enabled'];
        }

        if (array_key_exists('delivery_mode', $own)) {
            $method->delivery_rule = $this->rule($own);
        }

        foreach (['min_amount', 'max_amount'] as $field) {
            if (array_key_exists($field, $own)) {
                $method->setAttribute($field, $own[$field] === null || $own[$field] === '' ? null : $this->minor($own[$field]));
            }
        }

        if ($method->min_amount !== null && $method->max_amount !== null && $method->min_amount > $method->max_amount) {
            throw ValidationException::withMessages(['max_amount' => [__('webx-payments::panel.errors.amounts')]]);
        }

        if ($settings !== null) {
            $method->settings = [...$method->settings, ...$this->cleanSettings($type, $settings)];
        }

        // Only when the settings are being written: a pair stored before the type learnt to
        // refuse it must not stop a method's title from being changed.
        if ($settings !== null || ! $method->exists) {
            $this->checkTogether($type, $method->settings);
        }

        if ($credentials !== null || $clear !== null) {
            $method->credentials = $this->mergeCredentials($method, $type, $credentials ?? [], $clear ?? []);
        }

        return DB::transaction(function () use ($method): PaymentMethod {
            if (! $method->exists) {
                $method->key = $this->key($method->type);
                $method->position = (int) PaymentMethod::query()->max('position') + 1;
            }

            $method->save();

            return $method;
        });
    }

    /**
     * «Test the connection» (§13.3): the stored keys with whatever was typed over them, not saved;
     * the request is an event of the journal of kind `connection`.
     *
     * @param  array<string, mixed>  $typed
     */
    public function test(PaymentMethod $method, array $typed): ConnectionResult
    {
        $type = $this->types->has($method->type) ? $this->types->get($method->type) : null;
        $gateway = $type instanceof ChargesOnline ? $this->gateway($type) : null;

        if (! $type instanceof ChargesOnline || $gateway === null) {
            return ConnectionResult::failed((string) __('webx-payments::panel.errors.not_online'));
        }

        $one = Method::of($method);
        $values = $this->credentials->for($one)->reveal();

        foreach ($this->fieldNames($gateway->credentials()) as $name) {
            $value = $typed[$name] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $values[$name] = trim($value);
            }
        }

        $keys = Credentials::of($values);

        try {
            return $gateway->testConnection($keys, $method->settings, $this->calls->http($type, $one, $keys, ExchangeKind::Connection, 'panel'));
        } catch (Throwable) {
            // The gateway's own words are in the journal; the panel gets ours, never a body.
            return ConnectionResult::failed((string) __('webx-payments::panel.errors.test_failed'));
        }
    }

    /**
     * The settings fields of a type and its gateway, the keys, the hold and the addresses.
     *
     * @return array<string, mixed>
     */
    private function typeParts(?MethodType $type, ?PaymentMethod $method): array
    {
        $gateway = $type !== null ? $this->gateway($type) : null;
        $fields = [];

        foreach ([...($type?->settings() ?? []), ...($gateway?->settings() ?? [])] as $field) {
            if (is_string($field['name'] ?? null) && $field['name'] !== self::HOLD) {
                $fields[] = $this->field($field);
            }
        }

        $stored = $method !== null ? $this->stored($method) : [];
        $one = $method !== null ? Method::of($method) : null;

        return [
            'settings' => [
                'fields' => $fields,
                'values' => (object) array_intersect_key($method->settings ?? [], array_flip(array_column($fields, 'name'))),
            ],
            'credentials' => $gateway === null ? [] : array_map(function (array $field) use ($stored, $one): array {
                $name = (string) $field['name'];

                return [
                    'name' => $name,
                    'label' => $this->words($field['label'] ?? $name),
                    'multiline' => (bool) ($field['multiline'] ?? false),
                    // Whether it is there; the value itself never leaves the server (§15.2).
                    'set' => is_string($stored[$name] ?? null) && $stored[$name] !== '',
                    'environment' => $one !== null && $this->fromEnvironment($one, $name),
                ];
            }, array_values(array_filter($gateway->credentials(), static fn (array $field): bool => is_string($field['name'] ?? null)))),
            'credentials_unreadable' => $method !== null && $this->unreadable($method),
            'hold' => $type instanceof CapturesPayments ? [
                'value' => (bool) ($method?->settings[self::HOLD] ?? false),
                'partial' => $type->capturesPartially(),
                'days' => $type->maxHoldDays(),
            ] : null,
            'online' => $type instanceof ChargesOnline,
            'testable' => $type instanceof ChargesOnline && $gateway !== null,
            'notify_url' => $type instanceof ChargesOnline && $method !== null ? $this->links->notifyUrl($method->key) : null,
        ];
    }

    /**
     * A field of a type's or a gateway's settings as the form draws it.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function field(array $field): array
    {
        $options = [];

        foreach ((array) ($field['options'] ?? []) as $value => $label) {
            $options[] = is_array($label)
                ? ['value' => (string) ($label['value'] ?? ''), 'label' => $this->words($label['label'] ?? $label['value'] ?? '')]
                : ['value' => (string) $value, 'label' => $this->words($label)];
        }

        return [
            'name' => (string) $field['name'],
            'type' => in_array($field['type'] ?? 'text', ['text', 'textarea', 'select', 'number', 'switch'], true) ? $field['type'] : 'text',
            'label' => $this->words($field['label'] ?? $field['name']),
            'help' => isset($field['help']) ? $this->words($field['help']) : null,
            'localized' => (bool) ($field['localized'] ?? false),
            'options' => $options,
            'default' => $field['default'] ?? null,
            // The switch that takes this field away while it is on, and why (§13.3).
            'off_with' => is_string($field['off_with'] ?? null) ? $field['off_with'] : null,
            'off_help' => isset($field['off_help']) ? $this->words($field['off_help']) : null,
        ];
    }

    /**
     * Only the fields of the type, each its own shape: words by language, a choice of the list,
     * a number, a line.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function cleanSettings(MethodType $type, array $input): array
    {
        $gateway = $this->gateway($type);
        $clean = [];

        foreach ([...$type->settings(), ...($gateway?->settings() ?? [])] as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || $name === self::HOLD || ! array_key_exists($name, $input)) {
                continue;
            }

            $value = $input[$name];
            $described = $this->field($field);

            $clean[$name] = match (true) {
                $described['localized'] => Words::clean(is_array($value) ? array_map(static fn (mixed $one): string => is_scalar($one) ? mb_substr((string) $one, 0, 2000) : '', $value) : []) ?: null,
                $described['type'] === 'select' => $value === null || $value === '' ? null : (in_array((string) $value, array_column($described['options'], 'value'), true)
                    ? (string) $value
                    : throw ValidationException::withMessages(["settings.{$name}" => [__('validation.in', ['attribute' => $described['label']])]])),
                $described['type'] === 'number' => $value === null || $value === '' ? null : (is_numeric($value)
                    ? $value + 0
                    : throw ValidationException::withMessages(["settings.{$name}" => [__('validation.numeric', ['attribute' => $described['label']])]])),
                $described['type'] === 'switch' => (bool) $value,
                default => is_scalar($value) && trim((string) $value) !== '' ? mb_substr(trim((string) $value), 0, 2000) : null,
            };
        }

        // The hold only where the type can capture later (§9.5): no switch, nothing stored.
        if ($type instanceof CapturesPayments && array_key_exists(self::HOLD, $input)) {
            $clean[self::HOLD] = (bool) $input[self::HOLD];
        }

        return $clean;
    }

    /**
     * The type's word on the settings as a whole (§6.4): each field was checked on its own above,
     * and neither the form nor an agent can be trusted with the pair.
     *
     * @param  array<string, mixed>  $settings
     */
    private function checkTogether(MethodType $type, array $settings): void
    {
        if (! $type instanceof ValidatesSettings) {
            return;
        }

        $refused = [];

        foreach ($type->validateSettings($settings) as $field => $words) {
            $refused["settings.{$field}"] = [$this->words($words)];
        }

        if ($refused !== []) {
            throw ValidationException::withMessages($refused);
        }
    }

    /**
     * @param  array<string, mixed>  $typed
     * @param  list<string>  $clear
     * @return array<string, string>|null
     */
    private function mergeCredentials(PaymentMethod $method, MethodType $type, array $typed, array $clear): ?array
    {
        $gateway = $this->gateway($type);

        if ($gateway === null) {
            return $method->exists ? $this->stored($method) ?: null : null;
        }

        $names = $this->fieldNames($gateway->credentials());
        // Unreadable keys (a new APP_KEY) are dropped: they are to be typed again (§15.1).
        $stored = $method->exists ? $this->stored($method) : [];

        foreach ($typed as $name => $value) {
            // Blank is «leave it»: the field on screen is empty because the key is never sent.
            if (in_array($name, $names, true) && is_string($value) && trim($value) !== '') {
                $stored[$name] = trim($value);
            }
        }

        foreach ($clear as $name) {
            unset($stored[$name]);
        }

        return $stored === [] ? null : $stored;
    }

    /** @return array<string, mixed> the screen's values of a method */
    private function values(PaymentMethod $method): array
    {
        $rule = DeliveryRule::fromArray($method->delivery_rule);

        return [
            'titles' => $method->title,
            'descriptions' => $method->description ?? [],
            'enabled' => $method->enabled,
            'delivery_mode' => $rule->mode,
            'delivery_methods' => $rule->methods,
            'delivery_keys' => implode(', ', $rule->methods),
            'min_amount' => $method->min_amount === null ? null : $this->major($method->min_amount),
            'max_amount' => $method->max_amount === null ? null : $this->major($method->max_amount),
        ];
    }

    /**
     * @param  array<string, mixed>  $own
     * @return array<string, mixed>
     */
    private function rule(array $own): array
    {
        $mode = is_string($own['delivery_mode'] ?? null) && in_array($own['delivery_mode'], DeliveryRule::MODES, true) ? $own['delivery_mode'] : DeliveryRule::ANY;
        $methods = array_key_exists('delivery_methods', $own)
            ? array_values(array_filter((array) $own['delivery_methods'], 'is_string'))
            : array_values(array_filter(array_map('trim', explode(',', (string) ($own['delivery_keys'] ?? ''))), static fn (string $key): bool => $key !== ''));

        return (new DeliveryRule($mode, $methods))->toArray();
    }

    /**
     * The delivery methods a rule may name (§13.3): from the delivery module's public list where it
     * is installed; null — it is not, and the form asks for the keys as text.
     *
     * @return list<array{value: string, label: string}>|null
     */
    public function deliveryOptions(): ?array
    {
        if (! class_exists(DeliveryServiceProvider::class) || ! app()->providerIsLoaded(DeliveryServiceProvider::class)) {
            return null;
        }

        $locale = $this->locales->current();
        $options = [];

        foreach (app(DeliveryMethods::class)->catalogue()['methods'] as $row) {
            $titles = is_array($row['titles'] ?? null) ? $row['titles'] : [];
            $options[] = ['value' => (string) $row['key'], 'label' => Words::pick($titles, $locale) ?? (string) $row['key']];
        }

        return $options;
    }

    /** `set`, `environment`, `missing`, `unreadable`; null — the type keeps no keys. */
    private function keyState(PaymentMethod $method, ?MethodType $type, ?Gateway $gateway): ?string
    {
        if (! $type instanceof ChargesOnline || $gateway === null || $this->fieldNames($gateway->credentials()) === []) {
            return null;
        }

        if ($this->credentials->inEnvironment(Method::of($method))) {
            return 'environment';
        }

        if ($method->getRawOriginal('credentials') === null) {
            return 'missing';
        }

        return $this->unreadable($method) ? 'unreadable' : 'set';
    }

    /**
     * The keys of the column, or none when they cannot be read — decrypted by hand, as the
     * column's cast would: a key a new APP_KEY cannot read is a case, not a crash (§15.1).
     *
     * @return array<string, string>
     */
    private function stored(PaymentMethod $method): array
    {
        $raw = $method->getRawOriginal('credentials');

        if (! is_string($raw)) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($raw), true);
        } catch (DecryptException) {
            return [];
        }

        return is_array($decoded) ? array_filter($decoded, 'is_string') : [];
    }

    private function unreadable(PaymentMethod $method): bool
    {
        $raw = $method->getRawOriginal('credentials');

        if (! is_string($raw)) {
            return false;
        }

        try {
            Crypt::decryptString($raw);

            return false;
        } catch (DecryptException) {
            return true;
        }
    }

    private function fromEnvironment(Method $method, string $field): bool
    {
        $value = Env::get(MethodCredentials::variable($method->key, $field));

        return is_string($value) && $value !== '';
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<string>
     */
    private function fieldNames(array $fields): array
    {
        return array_values(array_filter(array_column($fields, 'name'), 'is_string'));
    }

    /** @param  array<string, mixed>  $settings */
    private function mode(Gateway $gateway, array $settings): ?string
    {
        foreach ($gateway->settings() as $field) {
            if (($field['name'] ?? null) === 'mode') {
                $value = $settings['mode'] ?? $field['default'] ?? null;

                return is_string($value) ? $value : null;
            }
        }

        return null;
    }

    private function gateway(MethodType $type): ?Gateway
    {
        return $this->gateways->has($type->provider()) ? $this->gateways->get($type->provider()) : null;
    }

    /** The title a type gives a method that has none of its own: the words of the type itself. */
    private function typeTitle(MethodType $type, string $locale): string
    {
        $blank = new Method($type->key(), $type->key(), [], [], null, false, 0, ['mode' => DeliveryRule::ANY], null, null, [], false);

        try {
            return $type->present($blank, new PaymentContext(0, $this->currency(), $locale, null, Purpose::Order))->title;
        } catch (Throwable) {
            return $type->key();
        }
    }

    /** A label as a type gives it: a key of a dictionary, or words. */
    private function words(mixed $label): string
    {
        $text = is_scalar($label) ? (string) $label : '';

        return str_contains($text, '::') ? (string) __($text) : $text;
    }

    private function key(string $type): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($type)), '-');
        $taken = PaymentMethod::query()->where('key', 'like', $base.'%')->pluck('key')->all();
        $key = $base;

        for ($n = 2; in_array($key, $taken, true); $n++) {
            $key = $base.'-'.$n;
        }

        return $key;
    }

    private function currency(): string
    {
        return $this->currencies->currency() ?? 'EUR';
    }

    private function major(int $minor): float
    {
        return $minor / 10 ** $this->money->exponent($this->currency());
    }

    private function minor(mixed $major): int
    {
        return (int) round((float) $major * 10 ** $this->money->exponent($this->currency()));
    }
}
