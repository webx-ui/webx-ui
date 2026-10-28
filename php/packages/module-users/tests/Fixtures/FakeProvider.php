<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests\Fixtures;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Support\Field;
use WebxUi\Users\Social\Provider;
use WebxUi\Users\Social\ProviderConfig;
use WebxUi\Users\Social\ProviderFailed;
use WebxUi\Users\Social\SocialProfile;
use WebxUi\Users\Urls;

/**
 * A social network of the tests (spec §9.1): it «sends» the visitor to `provider.test` and «gives
 * back» whoever the test put in `$next` — so the flow is the module's own, only the network is
 * made up. `trusted` says whether the registry takes its «verified» at its word (§9.7).
 */
final class FakeProvider implements Provider
{
    /** @var array<string, SocialProfile|null> the person the next callback of each provider gives */
    public static array $next = [];

    /** @var array<string, string> what the last redirect was given, by provider */
    public static array $config = [];

    public function __construct(
        private readonly string $key,
        private readonly bool $trusted,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return ucfirst($this->key);
    }

    public function icon(): string
    {
        return 'webx-users::social.icons.'.$this->key;
    }

    public function fields(): array
    {
        return [
            new Field('client_id', 'Client ID', required: true),
            new Field('client_secret', 'Client secret', secret: true, required: true),
        ];
    }

    public function trustsVerifiedEmail(): bool
    {
        return $this->trusted;
    }

    public function callbackUrl(): string
    {
        return app(Urls::class)->callback($this->key);
    }

    public function redirect(ProviderConfig $config, string $state): RedirectResponse
    {
        self::$config[$this->key] = $config->string('client_secret');

        return new RedirectResponse('https://provider.test/'.$this->key.'?'.http_build_query([
            'client_id' => $config->string('client_id'),
            'redirect_uri' => $this->callbackUrl(),
            'state' => $state,
        ]));
    }

    public function profile(ProviderConfig $config, Request $request): SocialProfile
    {
        $profile = self::$next[$this->key] ?? null;

        if ($request->filled('error') || $profile === null) {
            throw new ProviderFailed('The visitor cancelled.');
        }

        return $profile;
    }
}
