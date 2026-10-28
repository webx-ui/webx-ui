<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers\Panel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Support\Field;
use WebxUi\Settings\Settings;
use WebxUi\Users\Models\ProviderSettings;
use WebxUi\Users\Models\User;
use WebxUi\Users\Panel\SettingsScreen;
use WebxUi\Users\Social\Provider;
use WebxUi\Users\Social\ProviderKeys;
use WebxUi\Users\Social\SocialProviders;

/**
 * `/api/cms/users/settings` (spec §16.3), behind `users.settings`.
 *
 * Two halves. The registration and how much the forms ask for are the site's settings
 * (`users.registration`, `users.fields.<name>`), described by the screen `users.settings` and
 * stored where every setting is — there is nothing to store them in on a site without
 * `module-settings`, and the screen says so. The keys of the providers are `users_providers`:
 * a card per provider of the registry, a secret answered only with «set» and replaced or
 * forgotten, never read back — not here, not in the journal.
 */
final readonly class SettingsController
{
    public function __construct(
        private Container $container,
        private ScreenRegistry $screens,
        private SocialProviders $providers,
        private ProviderKeys $keys,
    ) {}

    public function show(Config $config, Application $app): JsonResponse
    {
        return ApiResponse::data($this->answer($config, $app));
    }

    public function update(Request $request, ScreenValues $values, Config $config, Application $app): JsonResponse
    {
        $settings = $this->settings();

        if ($settings === null) {
            abort(404);
        }

        $input = $request->input('values');
        $user = $request->user();

        $stored = $values->validate(
            SettingsScreen::NAME,
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
        );

        $settings->save($stored);

        return ApiResponse::data($this->answer($config, $app));
    }

    /**
     * One provider's card saved: on or off, the open fields, a secret only when a new one was
     * typed, a secret forgotten when asked. A field left empty falls back on `.env` (§9.3).
     */
    public function provider(Request $request, string $key, Config $config, Application $app): JsonResponse
    {
        $provider = $this->providers->find($key) ?? abort(404);

        $row = ProviderSettings::query()->find($key) ?? new ProviderSettings(['key' => $key]);
        $options = $row->options ?? [];
        $credentials = $row->credentials ?? [];
        $errors = [];

        $sent = $request->input('options');
        $secrets = $request->input('credentials');
        $forget = array_map('strval', (array) $request->input('forget', []));

        foreach ($provider->fields() as $field) {
            if ($field->secret) {
                $value = is_array($secrets) ? ($secrets[$field->name] ?? null) : null;

                if (in_array($field->name, $forget, true)) {
                    unset($credentials[$field->name]);
                } elseif (is_string($value) && trim($value) !== '') {
                    if (mb_strlen($value) > $field->max) {
                        $errors['credentials.'.$field->name] = [(string) trans('validation.max.string', ['attribute' => (string) __($field->label), 'max' => $field->max])];
                    }

                    $credentials[$field->name] = trim($value);
                }

                continue;
            }

            if (! is_array($sent) || ! array_key_exists($field->name, $sent)) {
                continue;
            }

            $value = $sent[$field->name];
            $value = is_scalar($value) ? trim((string) $value) : null;

            if ($value !== null && mb_strlen($value) > $field->max) {
                $errors['options.'.$field->name] = [(string) trans('validation.max.string', ['attribute' => (string) __($field->label), 'max' => $field->max])];
            }

            if ($value === null || $value === '') {
                unset($options[$field->name]);
            } else {
                $options[$field->name] = $value;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($request->has('enabled')) {
            $row->enabled = $request->boolean('enabled');
        }

        $row->options = $options === [] ? null : $options;
        $row->credentials = $credentials === [] ? null : $credentials;
        $row->save();

        $this->keys->forget();

        return ApiResponse::data($this->answer($config, $app));
    }

    /**
     * @return array<string, mixed>
     */
    private function answer(Config $config, Application $app): array
    {
        $settings = $this->settings();
        $keys = array_map(static fn (array $node): string => (string) $node['name'], $this->screens->fields(SettingsScreen::NAME));

        return [
            // Without the settings module there is nowhere to keep them: the site's config rules.
            'stored' => $settings !== null,
            'values' => $settings === null ? [] : array_intersect_key($settings->raw(), array_flip($keys)),
            'providers' => array_values(array_map(fn (Provider $provider): array => $this->card($provider), $this->providers->all())),
            'mail' => self::mail($config, $app),
        ];
    }

    /**
     * A provider's card: never a secret, only whether it is set and where from.
     *
     * @return array<string, mixed>
     */
    private function card(Provider $provider): array
    {
        $row = ProviderSettings::query()->find($provider->key());
        $config = $this->keys->config($provider);

        return [
            'key' => $provider->key(),
            'label' => (string) __($provider->label()),
            'enabled' => $this->keys->enabled($provider),
            'saved' => $row !== null,
            'shown' => $this->keys->shown($provider),
            'missing' => $this->keys->missing($provider),
            'callback' => $provider->callbackUrl(),
            'fields' => array_values(array_map(static function (Field $field) use ($row, $config): array {
                $own = $field->secret ? ($row?->credentials[$field->name] ?? null) : ($row?->options[$field->name] ?? null);
                $own = is_string($own) && $own !== '' ? $own : null;

                return [
                    ...$field->toArray(),
                    'value' => $field->secret ? null : $own,
                    'set' => $own !== null,
                    // Filled by `.env` where the panel left it empty (§9.3).
                    'env' => $own === null && $config->has($field->name),
                ];
            }, $provider->fields())),
            'only' => self::onlyBy($provider->key()),
        ];
    }

    /** How many visitors sign in by this provider alone — switched off, they are locked out (§9.6). */
    private static function onlyBy(string $key): int
    {
        return User::query()
            ->whereNull('password')
            ->whereHas('identities', static fn ($links) => $links->where('provider', $key))
            ->whereDoesntHave('identities', static fn ($links) => $links->where('provider', '!=', $key))
            ->count();
    }

    /**
     * Where the letters go (§8.6): only the line «the transport writes into the log», the address
     * of the sender is the site's mail settings.
     *
     * @return array{transport: string, reaches: bool}
     */
    private static function mail(Config $config, Application $app): array
    {
        $mailer = (string) $config->get('mail.default', 'log');
        $transport = (string) $config->get("mail.mailers.{$mailer}.transport", $mailer);

        return ['transport' => $transport, 'reaches' => ! in_array($transport, ['log', 'array'], true) || $app->isLocal()];
    }

    private function settings(): ?Settings
    {
        return $this->container->bound(Settings::class) ? $this->container->make(Settings::class) : null;
    }
}
