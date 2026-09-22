<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Boot\Plan;
use WebxUi\Admin\Boot\Step;
use WebxUi\Admin\Setup\Catalogue;

/**
 * What a container is told to do before it serves, for the site it turns out to be.
 *
 * The running is the smoke script's business — a real database, a real migration, a real
 * cache. What is here is the deciding, which is the half that goes wrong quietly: a step
 * skipped for a module that is installed is a section of the panel that comes up half set up
 * and says nothing about it, and a step run twice is `migrate` stopping on two files that
 * create the same table.
 */
final class BootTest extends TestCase
{
    private Filesystem $files;

    /** @var list<string> */
    private array $written = [];

    /** @var list<string> */
    private array $variables = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            $this->files->delete($path);
        }

        foreach ($this->variables as $name) {
            putenv($name);
        }

        parent::tearDown();
    }

    // -- The shape of a boot -----------------------------------------------------------------

    #[Test]
    public function it_migrates_and_leaves_the_site_cached(): void
    {
        $lines = $this->lines($this->plan(['webx-ui/module-admin']));

        $this->assertSame([
            'php artisan migrate --force',
            'php artisan storage:link --force',
            'php artisan config:cache',
            'php artisan route:cache',
            'php artisan view:cache',
            'php artisan event:cache',
        ], $lines);
    }

    #[Test]
    public function it_leaves_the_caches_alone_when_the_container_is_one_to_develop_in(): void
    {
        $lines = $this->lines($this->plan(['webx-ui/module-admin']), cache: false);

        $this->assertSame(['php artisan migrate --force', 'php artisan storage:link --force'], $lines);
    }

    #[Test]
    public function it_seeds_the_languages_and_clears_the_dictionary_of_the_release_before(): void
    {
        $lines = $this->lines($this->plan(['webx-ui/module-admin', 'webx-ui/localization']));

        // In this order and both of them: seeding is what a first boot needs, clearing is
        // what every boot after a release that added a module needs.
        $this->assertSame(
            ['php artisan webx:locales:seed', 'php artisan webx:locales:clear'],
            array_values(array_filter($lines, static fn (string $line): bool => str_contains($line, 'locales'))),
        );
    }

    #[Test]
    public function the_command_itself_loads_and_prints_what_it_would_do(): void
    {
        // Worth a test of its own because the plan can be perfect while the command cannot be
        // loaded at all: a private helper named like a method of the base class — `run()`,
        // here — is a fatal error at class load, and neither Pint nor PHPStan says a word.
        // Nothing that only builds a Plan ever loads the command, so nothing else notices.
        $this->artisan('webx:boot', ['--pretend' => true])
            ->expectsOutputToContain('php artisan migrate --force')
            ->assertSuccessful();
    }

    // -- Passport ----------------------------------------------------------------------------

    #[Test]
    public function it_asks_for_the_keys_a_fresh_volume_does_not_have(): void
    {
        $lines = $this->lines($this->plan(['webx-ui/module-admin', 'laravel/passport']));

        $this->assertContains('php artisan passport:keys --quiet', $lines);
    }

    #[Test]
    public function it_never_publishes_the_passport_migrations(): void
    {
        // Publishing stamps the copy with the minute it was made, so a boot that published
        // would give every container a migration under a name the migrations table has never
        // seen: the first deployment is fine and the second stops on "table already exists".
        // Found by deploying twice; no test would have said a word.
        $lines = $this->lines($this->plan(['webx-ui/module-admin', 'laravel/passport']));

        $this->assertSame([], array_values(array_filter(
            $lines,
            static fn (string $line): bool => str_contains($line, 'vendor:publish'),
        )));
    }

    #[Test]
    public function it_asks_for_no_keys_where_the_volume_already_holds_them(): void
    {
        $this->write(Passport::keyPath('oauth-private.key'), 'not a key, but a file');

        $lines = $this->lines($this->plan(['webx-ui/module-admin', 'laravel/passport']));

        $this->assertNotContains('php artisan passport:keys --quiet', $lines);
    }

    #[Test]
    public function it_says_nothing_about_passport_on_a_site_without_it(): void
    {
        $lines = $this->lines($this->plan(['webx-ui/module-admin', 'webx-ui/module-pages']));

        $this->assertSame([], array_values(array_filter(
            $lines,
            static fn (string $line): bool => str_contains($line, 'passport'),
        )));
    }

    // -- Block types -------------------------------------------------------------------------

    #[Test]
    public function it_brings_the_database_up_to_the_block_types_in_the_repository(): void
    {
        $this->write($this->app->resourcePath('blocks/hero.json'), '{"key":"hero"}');

        $lines = $this->lines($this->plan(['webx-ui/module-admin', 'webx-ui/module-blocks']));

        $this->assertContains('php artisan webx:blocks:import --publish', $lines);
    }

    #[Test]
    public function it_imports_nothing_where_there_are_no_files_to_import(): void
    {
        $lines = $this->lines($this->plan(['webx-ui/module-admin', 'webx-ui/module-blocks']));

        $this->assertNotContains('php artisan webx:blocks:import --publish', $lines);
    }

    // -- The first administrator ---------------------------------------------------------------

    #[Test]
    public function it_creates_the_first_administrator_out_of_the_environment(): void
    {
        $this->given(['WEBX_ADMIN_EMAIL' => 'admin@example.test', 'WEBX_ADMIN_PASSWORD' => 'a-long-enough-one']);

        $step = $this->step($this->plan(['webx-ui/module-admin', 'webx-ui/module-auth']), 'webx:admin');

        $this->assertNotNull($step);
        $this->assertSame(
            'php artisan webx:admin --name=Administrator --email=admin@example.test --super',
            $step->line(),
        );

        // The secret goes to the child in the environment: an argument lands in `ps`.
        $this->assertSame(['WEBX_ADMIN_PASSWORD' => 'a-long-enough-one'], $step->env);
        $this->assertStringNotContainsString('a-long-enough-one', $step->line());

        // Every boot after the first one lands on the address being taken, which the command
        // reports as a failure. A boot that stopped there would be a container that never
        // starts twice.
        $this->assertFalse($step->fatal);
    }

    #[Test]
    public function it_creates_nobody_without_both_halves_of_the_answer(): void
    {
        $this->given(['WEBX_ADMIN_EMAIL' => 'admin@example.test']);

        $this->assertNull($this->step($this->plan(['webx-ui/module-admin', 'webx-ui/module-auth']), 'webx:admin'));
    }

    #[Test]
    public function it_creates_nobody_on_a_site_with_no_way_to_sign_in(): void
    {
        $this->given(['WEBX_ADMIN_EMAIL' => 'admin@example.test', 'WEBX_ADMIN_PASSWORD' => 'a-long-enough-one']);

        $this->assertNull($this->step($this->plan(['webx-ui/module-admin']), 'webx:admin'));
    }

    // -- Helpers -------------------------------------------------------------------------------

    /** @param  list<string>  $packages */
    private function plan(array $packages): Plan
    {
        $path = $this->app->storagePath('boot-installed.json');

        $this->write($path, (string) json_encode([
            'packages' => array_map(static fn (string $name): array => ['name' => $name, 'version' => '0.28.0'], $packages),
        ]));

        return new Plan(
            $this->app,
            $this->files,
            $this->app->make(Repository::class),
            new Catalogue($this->files, $path),
        );
    }

    /** @return list<string> */
    private function lines(Plan $plan, bool $cache = true): array
    {
        return array_map(static fn (Step $step): string => $step->line(), $plan->steps($cache));
    }

    private function step(Plan $plan, string $what): ?Step
    {
        foreach ($plan->steps() as $step) {
            if ($step->what === $what) {
                return $step;
            }
        }

        return null;
    }

    private function write(string $path, string $contents): void
    {
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);

        $this->written[] = $path;
    }

    /** @param  array<string, string>  $variables */
    private function given(array $variables): void
    {
        foreach ($variables as $name => $value) {
            putenv("{$name}={$value}");

            $this->variables[] = $name;
        }
    }
}
