<?php

declare(strict_types=1);

namespace WebxUi\Users\Social;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Support\Field;

/**
 * A social network a visitor signs in with (spec §9.1). Registered with
 * `SocialProviders::register()` in `boot()` of a package or the site: the module is not edited
 * to add one. `SocialiteProvider` does the work for a network Laravel Socialite knows.
 */
interface Provider
{
    /** The key a visitor's identity and the settings are stored by: `google`. [a-z0-9-] */
    public function key(): string;

    /** A dictionary key or plain words: "Google". */
    public function label(): string;

    /** An icon for the button: a view name the theme may override. */
    public function icon(): string;

    /**
     * The settings an administrator fills in the panel: secret ones go to `credentials`,
     * encrypted and never given back; the others to `options`.
     *
     * @return list<Field>
     */
    public function fields(): array;

    /**
     * Whether the module may trust the provider's "this e-mail is verified" and link the provider
     * to an existing account with that address without a password (§9.7). False for a provider
     * that does not say it, or says it carelessly.
     */
    public function trustsVerifiedEmail(): bool;

    /** Where the provider sends the visitor back; shown in the panel to copy into its console. */
    public function callbackUrl(): string;

    /** Send the visitor to the provider. `state` is the module's own, checked on the way back. */
    public function redirect(ProviderConfig $config, string $state): RedirectResponse;

    /**
     * Who came back. Throws ProviderFailed when the provider refused, the visitor cancelled, or
     * the answer is broken.
     *
     * @throws ProviderFailed
     */
    public function profile(ProviderConfig $config, Request $request): SocialProfile;
}
