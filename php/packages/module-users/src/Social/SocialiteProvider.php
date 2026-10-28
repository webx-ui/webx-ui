<?php

declare(strict_types=1);

namespace WebxUi\Users\Social;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;
use WebxUi\Users\Urls;

/**
 * A provider on top of Laravel Socialite (spec §9.1): the driver gets the keys of `ProviderConfig`
 * and the module's address of return, and its own `state` is off — the module keeps one in the
 * session and checks it before the driver is asked anything (§9.4). The answer becomes a
 * `SocialProfile`; no token of the provider outlives the request.
 *
 * The driver is built here rather than through Socialite's manager, so the module needs neither
 * its service provider nor its `services.*` config.
 */
abstract class SocialiteProvider implements Provider
{
    /** @return class-string<AbstractProvider> the Socialite driver: `Laravel\Socialite\Two\GoogleProvider` */
    abstract protected function driver(): string;

    abstract protected function toProfile(SocialiteUser $user): SocialProfile;

    /** The names of the client id and the secret among `fields()`. */
    protected function clientIdField(): string
    {
        return 'client_id';
    }

    protected function clientSecretField(): string
    {
        return 'client_secret';
    }

    /** A driver as the module needs it; a provider adds its scopes or fields here. */
    protected function build(ProviderConfig $config, Request $request): AbstractProvider
    {
        $class = $this->driver();

        return (new $class($request, $config->string($this->clientIdField()), $config->string($this->clientSecretField()), $this->callbackUrl()))
            ->stateless();
    }

    public function icon(): string
    {
        return 'webx-users::social.icons.'.$this->key();
    }

    public function callbackUrl(): string
    {
        return app(Urls::class)->callback($this->key());
    }

    public function redirect(ProviderConfig $config, string $state): RedirectResponse
    {
        $driver = $this->build($config, request());

        // Stateless drops Socialite's `state`; the module's goes in as a parameter of its own.
        return $driver->with(['state' => $state])->redirect();
    }

    public function profile(ProviderConfig $config, Request $request): SocialProfile
    {
        if ($request->filled('error') || ! $request->filled('code')) {
            throw new ProviderFailed('The provider answered without a code: '.(string) $request->input('error', 'none'));
        }

        try {
            $user = $this->build($config, $request)->user();
        } catch (Throwable $exception) {
            throw new ProviderFailed('The provider did not give the visitor: '.$exception->getMessage(), 0, $exception);
        }

        if (! $user instanceof SocialiteUser || (string) $user->getId() === '') {
            throw new ProviderFailed('The provider gave nobody.');
        }

        return $this->toProfile($user);
    }

    protected static function text(mixed $value): ?string
    {
        return is_string($value) && ($value = trim($value)) !== '' ? $value : null;
    }
}
