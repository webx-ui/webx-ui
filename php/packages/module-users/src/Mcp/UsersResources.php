<?php

declare(strict_types=1);

namespace WebxUi\Users\Mcp;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use WebxUi\Mcp\McpResource;
use WebxUi\Users\Account\AccountMenu;
use WebxUi\Users\Account\AccountSection;
use WebxUi\Users\Emails\Verification;
use WebxUi\Users\Fields\FieldCatalog;
use WebxUi\Users\Http\Controllers\RegisterController;
use WebxUi\Users\Panel\Groups;
use WebxUi\Users\Panel\UserForm;
use WebxUi\Users\Passwords\ResetLinks;
use WebxUi\Users\SignIn;
use WebxUi\Users\Social\Provider;
use WebxUi\Users\Social\ProviderKeys;
use WebxUi\Users\Social\SocialProviders;

/**
 * What an agent reads before it writes a customer (spec §17): the fields a customer has and how
 * much each form asks of them, the social networks, the groups, the sections of the account, the
 * rules of a password and the limits — gathered from the registries and the config, never
 * written by hand, so a field a site declares is in it the moment it is declared.
 *
 * No customer is in it, and no key of a network: it is a map of the module, not of the people.
 */
final class UsersResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'users://catalog',
                'Customers catalog',
                'The fields of a customer — the built-in ones and the site\'s, with their types, where each is '
                .'asked, whether it is hidden, optional or required, unique, editable by the customer — the social '
                .'networks and whether each is shown, the customer groups, the sections of the account, the rules '
                .'of a password and the limits. Read it before creating or changing a customer.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $config = $this->container->make(Config::class);
        $keys = $this->container->make(ProviderKeys::class);

        return [
            'registration' => SignIn::registrationOpen() ? 'open' : 'closed',
            'fields' => $this->container->make(FieldCatalog::class)->describe(),
            'providers' => array_values(array_map(static fn (Provider $provider): array => [
                'key' => $provider->key(),
                'label' => (string) __($provider->label()),
                'shown' => $keys->shown($provider),
                'trusts_verified_email' => $provider->trustsVerifiedEmail(),
            ], $this->container->make(SocialProviders::class)->all())),
            'groups' => array_map(
                static fn (array $group): array => ['code' => $group['code'], 'name' => $group['name'], 'users_count' => $group['users_count']],
                $this->container->make(Groups::class)->rows(),
            ),
            'account_sections' => array_map(static fn (AccountSection $section): array => [
                'id' => $section->id,
                'label' => (string) __($section->label),
                'needs_verified_email' => $section->verified,
            ], $this->container->make(AccountMenu::class)->all()),
            'password' => [
                'min' => RegisterController::minimum(),
                ...array_map(
                    static fn (mixed $on): bool => (bool) $on,
                    array_intersect_key((array) $config->get('webx-users.password_rules', []), array_flip(['mixed', 'numbers', 'symbols', 'uncompromised'])),
                ),
            ],
            'links' => [
                'reset_minutes' => ResetLinks::minutes(),
                'invitation_days' => intdiv(UserForm::INVITE_MINUTES, 1440),
                'verify_hours' => Verification::hours(),
            ],
            'limits' => array_map('intval', (array) $config->get('webx-users.throttle', [])),
        ];
    }
}
