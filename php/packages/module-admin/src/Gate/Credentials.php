<?php

declare(strict_types=1);

namespace WebxUi\Admin\Gate;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Who may see a closed site: the pairs from the environment, and nothing from the database.
 *
 * The switch that closes a site lives beside `APP_DEBUG` on purpose. A setting in the panel would
 * be changed by the same person it can lock out, and would need a query on every request of a
 * site that is not supposed to be serving anyone yet.
 *
 * Passwords are kept as written, not hashed. The file they live in holds `APP_KEY` and the
 * database password already, so whoever reads it has the site and has no use for these; and a
 * browser sends the pair again with every request, which is a hash per request — tens of
 * milliseconds each with bcrypt, many times over on one page.
 */
final class Credentials
{
    public function __construct(private readonly Config $config) {}

    public function enabled(): bool
    {
        return filter_var($this->config->get('webx-admin.gate.enabled') ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether the site's config knows about the gate at all. A `config/webx-admin.php` published
     * before it existed has no `gate` key, and then `WEBX_SITE_GATE=true` in `.env` closes
     * nothing — silently, since the top-level merge replaces nothing that is missing.
     */
    public function configured(): bool
    {
        return is_array($this->config->get('webx-admin.gate'));
    }

    /**
     * `user:secret,other:secret`, or a list of such strings in a published config. The name ends
     * at the first colon, so a password may have colons in it; a comma separates pairs, so it may
     * not have commas.
     *
     * @return array<string, string>
     */
    public function pairs(): array
    {
        $users = $this->config->get('webx-admin.gate.users') ?? '';
        $entries = is_array($users) ? $users : explode(',', is_string($users) ? $users : '');
        $pairs = [];

        foreach ($entries as $entry) {
            if (! is_string($entry) || ! str_contains($entry, ':')) {
                continue;
            }

            [$name, $password] = explode(':', trim($entry), 2);

            if ($name !== '' && $password !== '') {
                $pairs[$name] = $password;
            }
        }

        return $pairs;
    }

    /**
     * Every pair is compared, whichever one matches, so the time the answer takes says nothing
     * about which names exist.
     */
    public function accepts(?string $name, ?string $password): bool
    {
        if ($name === null || $password === null) {
            return false;
        }

        $accepted = false;

        foreach ($this->pairs() as $knownName => $knownPassword) {
            // Both comparisons run before either is looked at.
            $nameMatches = hash_equals($knownName, $name);
            $passwordMatches = hash_equals($knownPassword, $password);

            if ($nameMatches && $passwordMatches) {
                $accepted = true;
            }
        }

        return $accepted;
    }
}
