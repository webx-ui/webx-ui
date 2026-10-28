<?php

declare(strict_types=1);

namespace WebxUi\Users\Panel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Support\Field;
use WebxUi\Settings\Settings;
use WebxUi\Users\Models\ProviderSettings;
use WebxUi\Users\Models\User;
use WebxUi\Users\Social\Provider;
use WebxUi\Users\Social\ProviderKeys;
use WebxUi\Users\Social\SocialProviders;

/**
 * The settings of «Customers» (spec §16.3) as the screen and the agent both read and write them.
 *
 * Two halves. The registration and how much the forms ask for are the site's settings
 * (`users.registration`, `users.fields.<name>`), described by the screen `users.settings` and
 * stored where every setting is — there is nowhere to store them on a site without
 * `module-settings`. The keys of the providers are `users_providers`: a card per provider of the
 * registry, a secret answered only with «set», never read back.
 */
final readonly class SettingsState
{
    public function __construct(
        private Container $container,
        private ScreenRegistry $screens,
        private ScreenValues $values,
        private SocialProviders $providers,
        private ProviderKeys $keys,
        private Config $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function answer(): array
    {
        $settings = $this->settings();

        return [
            // Without the settings module there is nowhere to keep them: the site's config rules.
            'stored' => $settings !== null,
            'values' => $settings === null ? [] : array_intersect_key($settings->raw(), array_flip($this->names())),
            'providers' => array_values(array_map(fn (Provider $provider): array => $this->card($provider), $this->providers->all())),
            'mail' => $this->mail(),
        ];
    }

    /**
     * The values checked against the screen — what a save would store, keys sent only.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input, ?callable $can = null): array
    {
        return $this->values->validate(SettingsScreen::NAME, $input, $can);
    }

    /**
     * @param  array<string, mixed>  $stored  what {@see self::validate()} answered
     */
    public function save(array $stored): void
    {
        $this->settings()?->save($stored);
    }

    /** Switch a provider on or off; the keys stay as they are. */
    public function enable(Provider $provider, bool $enabled): void
    {
        $row = ProviderSettings::query()->find($provider->key()) ?? new ProviderSettings(['key' => $provider->key()]);
        $row->enabled = $enabled;
        $row->save();

        $this->keys->forget();
    }

    /**
     * The names the screen writes — `users.registration` and a `users.fields.<name>` per field.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_map(static fn (array $node): string => (string) $node['name'], $this->screens->fields(SettingsScreen::NAME)));
    }

    public function settings(): ?Settings
    {
        return $this->container->bound(Settings::class) ? $this->container->make(Settings::class) : null;
    }

    /**
     * A provider's card: never a secret, only whether it is set and where from.
     *
     * @return array<string, mixed>
     */
    public function card(Provider $provider): array
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
    public static function onlyBy(string $key): int
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
    public function mail(): array
    {
        $mailer = (string) $this->config->get('mail.default', 'log');
        $transport = (string) $this->config->get("mail.mailers.{$mailer}.transport", $mailer);
        $app = $this->container->make(Application::class);

        return ['transport' => $transport, 'reaches' => ! in_array($transport, ['log', 'array'], true) || $app->isLocal()];
    }
}
