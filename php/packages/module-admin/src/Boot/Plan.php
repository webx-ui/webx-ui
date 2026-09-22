<?php

declare(strict_types=1);

namespace WebxUi\Admin\Boot;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Laravel\Passport\Passport;
use WebxUi\Admin\Setup\Catalogue;

/**
 * What a site has to be told before it can answer a request, worked out from what is installed.
 *
 * The list itself is the point. Every deployment writes this sequence once, by hand, into a
 * shell script — and then the script stops being true: a module installed six months later
 * brings a step nobody adds, and the site serves its section half set up with nothing to say
 * about it. Here the sequence arrives with the release that needs it, and the script in the
 * site is one line.
 *
 * Deciding is separated from running so that the whole plan can be read without a database, a
 * container or a child process: `webx:boot --pretend` prints it, and the tests assert on it.
 * What it decides from is what is in `vendor` and what is on disk, never what a person passed.
 *
 * One thing is deliberately not here: publishing Passport's migrations. Passport only ships
 * them to be copied, and the copy is named after the minute it was made — so a boot that
 * publishes gives every container a migration of its own, under a name the migrations table
 * has never seen, and the second deployment stops on "table oauth_auth_codes already exists".
 * Publishing is `webx:setup`'s, once, into the repository, where it is committed like any
 * other migration; a site that is missing it hears so from `webx:doctor`.
 */
final class Plan
{
    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
        private readonly Repository $config,
        private readonly Catalogue $catalogue,
    ) {}

    /**
     * @param  bool  $cache  Whether to finish by caching configuration, routes, views and
     *                       events. A container being developed in says no; everything else
     *                       says yes, because a panel behind a cached config has broken
     *                       before and only a cached run would have caught it.
     * @return list<Step>
     */
    public function steps(bool $cache = true): array
    {
        return array_values(array_filter([
            new Step(
                'migrate',
                ['migrate', '--force'],
                'the tables every module keeps its rows in',
            ),
            $this->storageLink(),
            $this->passportKeys(),
            ...$this->locales(),
            $this->blockTypes(),
            $this->administrator(),
            ...($cache ? $this->caches() : []),
        ]));
    }

    /**
     * The library's public files, which a fresh container has no link to.
     *
     * Not fatal: on a deployment where `public/storage` is a real directory rather than a
     * link — a volume mounted there, most often — this refuses, and refusing is right.
     */
    private function storageLink(): ?Step
    {
        $links = $this->config->get('filesystems.links');

        if (! is_array($links) || $links === []) {
            return null;
        }

        return new Step(
            'storage:link',
            ['storage:link', '--force'],
            'uploaded files answer from the public disk',
            fatal: false,
        );
    }

    /**
     * Without them the api guard cannot be built at all, and a call with no token answers 500
     * where it should answer 401 — an error that names encryption and not a missing file.
     *
     * A first-boot step and not an every-boot one: asked for twice the command refuses rather
     * than overwrites, which is right and reads like a failure.
     */
    private function passportKeys(): ?Step
    {
        if (! $this->catalogue->has('laravel/passport') || ! class_exists(Passport::class)) {
            return null;
        }

        if ($this->files->exists(Passport::keyPath('oauth-private.key'))) {
            return null;
        }

        return new Step(
            'passport:keys',
            ['passport:keys', '--quiet'],
            'an agent can connect over OAuth',
            fatal: false,
        );
    }

    /**
     * The languages, and then the dictionary built before this release existed.
     *
     * Both are seeds rather than resets: a language the panel has since renamed or switched
     * off stays the way the panel left it. Clearing matters on every boot and not only the
     * first — the dictionary is cached per language, and a release that adds a module adds
     * namespaces to it. Without this the panel goes on serving the dictionary built before
     * that module existed: its section falls back to the English the npm package carries, in
     * every language, and switching language changes everything except that section.
     *
     * @return list<Step>
     */
    private function locales(): array
    {
        if (! $this->catalogue->has('webx-ui/localization')) {
            return [];
        }

        return [
            new Step('webx:locales:seed', ['webx:locales:seed'], 'the languages the site publishes in'),
            new Step('webx:locales:clear', ['webx:locales:clear'], 'the panel speaks this release, not the last one'),
        ];
    }

    /**
     * Block types travel as files, so the repository is what a fresh database is brought up to.
     *
     * Idempotent in both directions: a type edited in the panel since is a newer version
     * rather than a conflict, and a type the files no longer mention is left alone.
     */
    private function blockTypes(): ?Step
    {
        if (! $this->catalogue->has('webx-ui/module-blocks')) {
            return null;
        }

        if ($this->files->glob($this->app->resourcePath('blocks/*.json')) === []) {
            return null;
        }

        return new Step(
            'webx:blocks:import',
            ['webx:blocks:import', '--publish'],
            'the block types this repository describes',
        );
    }

    /**
     * The first account, on a site where nobody has one yet.
     *
     * The password travels in the environment because that is how `webx:admin` asks for it,
     * and because an argument lands in `ps` and in the shell history. Every boot after the
     * first one lands on the address already being taken, which the command reports as a
     * failure — so this one does not stop the boot.
     */
    private function administrator(): ?Step
    {
        if (! $this->catalogue->has('webx-ui/module-auth')) {
            return null;
        }

        $email = $this->fromEnvironment('WEBX_ADMIN_EMAIL');
        $password = $this->fromEnvironment('WEBX_ADMIN_PASSWORD');

        if ($email === null || $password === null) {
            return null;
        }

        return new Step(
            'webx:admin',
            ['webx:admin', '--name='.($this->fromEnvironment('WEBX_ADMIN_NAME') ?? 'Administrator'), '--email='.$email, '--super'],
            'there is somebody to sign in as',
            fatal: false,
            env: ['WEBX_ADMIN_PASSWORD' => $password],
        );
    }

    /**
     * What production runs on, and therefore what a deploy has to have exercised.
     *
     * @return list<Step>
     */
    private function caches(): array
    {
        return [
            new Step('config:cache', ['config:cache'], 'the site runs the way production does'),
            new Step('route:cache', ['route:cache'], 'the site runs the way production does'),
            new Step('view:cache', ['view:cache'], 'the site runs the way production does'),
            new Step('event:cache', ['event:cache'], 'the site runs the way production does'),
        ];
    }

    private function fromEnvironment(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
