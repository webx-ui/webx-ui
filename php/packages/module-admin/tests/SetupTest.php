<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Setup\Catalogue;
use WebxUi\Admin\Setup\Connection;
use WebxUi\Admin\Setup\Database;
use WebxUi\Admin\Setup\EnvFile;
use WebxUi\Admin\Setup\LocalesConfig;
use WebxUi\Admin\Setup\SetupFailed;

/**
 * The parts of `webx:setup` that decide things, apart from the parts that run things.
 *
 * What the command itself does is install packages, migrate a real database and build a front
 * end in child processes, and that is what `scripts/php-smoke.sh` is for. What is here is
 * everything it works out before any of that: what the `.env` should say, which packages the
 * chosen modules are, and which language list is safe to rewrite.
 */
final class SetupTest extends TestCase
{
    private Filesystem $files;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->path = $this->app->basePath('.env.setup-test');
    }

    protected function tearDown(): void
    {
        $this->files->delete($this->path);

        parent::tearDown();
    }

    private function env(string $contents): EnvFile
    {
        $this->files->put($this->path, $contents);

        return new EnvFile($this->files, $this->path);
    }

    private function catalogue(): Catalogue
    {
        return new Catalogue($this->files, __DIR__.'/Fixtures/setup-installed.json');
    }

    // -- .env ------------------------------------------------------------------------------

    #[Test]
    public function it_replaces_an_assignment_rather_than_adding_a_second_one(): void
    {
        // The whole reason this class exists: Laravel reads the file immutably, the first
        // assignment wins, and a value appended below one already there changes nothing.
        $env = $this->env("APP_NAME=Laravel\nDB_DATABASE=laravel\n");

        $env->write(['DB_DATABASE' => 'kolesa']);

        $this->assertSame("APP_NAME=Laravel\nDB_DATABASE=kolesa\n", $this->files->get($this->path));
        $this->assertSame('kolesa', $env->get('DB_DATABASE'));
    }

    #[Test]
    public function it_replaces_every_assignment_of_the_same_name(): void
    {
        $env = $this->env("DB_HOST=one\nSESSION_DRIVER=database\nDB_HOST=two\n");

        $env->write(['DB_HOST' => '127.0.0.1']);

        $this->assertSame(
            "DB_HOST=127.0.0.1\nSESSION_DRIVER=database\nDB_HOST=127.0.0.1\n",
            $this->files->get($this->path),
        );
    }

    #[Test]
    public function it_fills_in_a_commented_out_assignment_where_there_is_one(): void
    {
        $env = $this->env("DB_CONNECTION=sqlite\n# DB_HOST=127.0.0.1\n");

        $env->write(['DB_HOST' => 'db.internal']);

        $this->assertSame("DB_CONNECTION=sqlite\nDB_HOST=db.internal\n", $this->files->get($this->path));
    }

    #[Test]
    public function it_appends_a_name_the_file_does_not_mention(): void
    {
        $env = $this->env("APP_NAME=Laravel\n");

        $env->write(['WEBX_ADMIN_TITLE' => 'Kolesa']);

        $this->assertSame("APP_NAME=Laravel\nWEBX_ADMIN_TITLE=Kolesa\n", $this->files->get($this->path));
    }

    #[Test]
    public function it_quotes_a_value_that_needs_quoting_and_reads_it_back(): void
    {
        $env = $this->env("APP_NAME=Laravel\n");

        $env->write(['APP_NAME' => 'Four Wheels', 'DB_PASSWORD' => '']);

        $this->assertStringContainsString('APP_NAME="Four Wheels"', (string) $this->files->get($this->path));
        $this->assertStringContainsString("DB_PASSWORD=\n", (string) $this->files->get($this->path));
        $this->assertSame('Four Wheels', $env->get('APP_NAME'));
        $this->assertSame('', $env->get('DB_PASSWORD'));
    }

    #[Test]
    public function a_placeholder_is_not_an_answer(): void
    {
        $env = $this->env("APP_NAME=Laravel\nDB_DATABASE=kolesa\n");

        $this->assertNull($env->chosen('APP_NAME', ['Laravel']));
        $this->assertSame('kolesa', $env->chosen('DB_DATABASE', ['laravel']));
        $this->assertNull($env->chosen('NOTHING_SAYS_THIS'));
    }

    // -- The catalogue ----------------------------------------------------------------------

    #[Test]
    public function it_reads_what_is_installed_off_disk(): void
    {
        $catalogue = $this->catalogue();

        $this->assertSame(['pages', 'admins'], $catalogue->installed());
        $this->assertTrue($catalogue->has('laravel/passport'));
        $this->assertFalse($catalogue->has('webx-ui/module-blog'));
    }

    #[Test]
    public function it_asks_composer_only_for_what_is_missing(): void
    {
        $catalogue = $this->catalogue();

        $this->assertSame(
            ['webx-ui/module-blog:^0.28.0', 'webx-ui/module-media:^0.28.0'],
            $catalogue->toRequire(['blog', 'pages', 'media', 'admins']),
        );
    }

    #[Test]
    public function a_checkout_has_no_version_to_build_a_range_out_of(): void
    {
        $this->files->put($path = $this->app->basePath('installed-dev.json'), json_encode([
            'packages' => [['name' => 'webx-ui/module-admin', 'version' => 'dev-main']],
        ]));

        $catalogue = new Catalogue($this->files, $path);

        // `^dev-main` is not a constraint, so the answer is the repository the checkout is in.
        $this->assertSame('*', $catalogue->constraint());
        $this->assertSame(['webx-ui/module-blog:*'], $catalogue->toRequire(['blog']));

        $this->files->delete($path);
    }

    #[Test]
    public function it_refuses_a_module_it_has_never_heard_of(): void
    {
        $catalogue = $this->catalogue();

        $this->assertTrue($catalogue->knows('admins'));
        $this->assertFalse($catalogue->knows('users'));
        $this->assertSame('webx-ui/module-auth', $catalogue->packageFor('admins'));
    }

    #[Test]
    public function what_is_installed_is_suggested_whether_it_is_a_default_or_not(): void
    {
        $this->files->put($path = $this->app->basePath('installed-blog.json'), json_encode([
            'packages' => [['name' => 'webx-ui/module-blog', 'version' => '0.28.0']],
        ]));

        // The blog is not what a new site gets unasked, but a site that has one keeps it.
        $this->assertContains('blog', (new Catalogue($this->files, $path))->suggested());

        $this->files->delete($path);
    }

    // -- Languages ---------------------------------------------------------------------------

    #[Test]
    public function it_writes_the_languages_into_the_list_that_shipped(): void
    {
        $rewritten = LocalesConfig::rewrite($this->shippedConfig(), ['ru', 'uk']);

        $this->assertNotNull($rewritten);
        $this->assertStringContainsString("['code' => 'ru', 'default' => true],", (string) $rewritten);
        $this->assertStringContainsString("['code' => 'uk'],", (string) $rewritten);
        $this->assertStringNotContainsString("'code' => 'en'", (string) $rewritten);
        // Everything around it is left alone.
        $this->assertStringContainsString("'fallback' => 'en',", (string) $rewritten);
    }

    #[Test]
    public function a_list_somebody_has_already_changed_is_an_answer(): void
    {
        $chosen = str_replace(
            "        ['code' => 'en', 'default' => true],",
            "        ['code' => 'de', 'default' => true],\n        ['code' => 'fr'],",
            $this->shippedConfig(),
        );

        $this->assertNull(LocalesConfig::rewrite($chosen, ['ru']));
    }

    #[Test]
    public function it_reads_a_comma_separated_answer(): void
    {
        $this->assertSame(['ru', 'uk'], LocalesConfig::codes(' RU , uk '));
        $this->assertSame(['en'], LocalesConfig::codes('en,en'));
        $this->assertSame(['pt-br'], LocalesConfig::codes('pt-BR'));
        $this->assertSame([], LocalesConfig::codes('  '));
    }

    // -- Where the server is --------------------------------------------------------------

    #[Test]
    public function an_option_beats_the_file_and_the_file_beats_the_default(): void
    {
        $env = $this->env("DB_HOST=db.internal\nDB_USERNAME=kolesa\nDB_PASSWORD=secret\n");

        $server = Connection::resolve('mysql', ['host' => '127.0.1.14'], $env);

        $this->assertSame('127.0.1.14', $server->host);
        $this->assertSame('kolesa', $server->username);
        $this->assertSame('secret', $server->password);
        // Nothing says otherwise, on the command line or in the file.
        $this->assertSame('3306', $server->port);
    }

    #[Test]
    public function an_empty_option_is_an_answer_and_an_empty_password_is_a_password(): void
    {
        $env = $this->env("DB_PASSWORD=secret\n");

        // `--db-password=` is how a password in the file is taken back off, so it cannot fall
        // through to the file the way an option nobody passed does.
        $this->assertSame('', Connection::resolve('mysql', ['password' => ''], $env)->password);
        $this->assertSame('secret', Connection::resolve('mysql', [], $env)->password);
    }

    #[Test]
    public function an_answer_replaces_what_was_tried_and_an_empty_one_keeps_it(): void
    {
        $server = (new Connection('mysql', '127.0.0.1', '3306', 'root', 'secret'))
            ->with(host: '127.0.1.14', port: '3307');

        $this->assertSame('127.0.1.14', $server->host);
        $this->assertSame('3307', $server->port);
        $this->assertSame('root', $server->username);
        // A hidden field has no default, so Enter has to mean the password already in the file
        // — otherwise being asked where the server is costs you the one thing that was right.
        $this->assertSame('secret', $server->password);
    }

    #[Test]
    public function mariadb_is_a_laravel_connection_that_pdo_has_never_heard_of(): void
    {
        $this->assertSame('mysql', (new Connection('mariadb', '127.0.0.1', '3306', 'root', ''))->driver());
        $this->assertSame('pgsql', (new Connection('pgsql', '127.0.0.1', '5432', 'postgres', ''))->driver());
        $this->assertTrue((new Connection('sqlite', '', '', '', ''))->isSqlite());
        $this->assertFalse((new Connection('mariadb', '127.0.0.1', '3306', 'root', ''))->isSqlite());
    }

    #[Test]
    public function it_writes_the_server_into_env_under_the_names_the_skeleton_has(): void
    {
        $values = (new Connection('mariadb', '127.0.1.14', '3307', 'root', ''))->env('kolesa');

        $this->assertSame(
            [
                'DB_HOST' => '127.0.1.14',
                'DB_PORT' => '3307',
                'DB_DATABASE' => 'kolesa',
                'DB_USERNAME' => 'root',
                'DB_PASSWORD' => '',
            ],
            $values,
        );
    }

    #[Test]
    public function giving_up_on_the_server_says_all_three_ways_out(): void
    {
        $message = SetupFailed::noDatabaseServer(
            '127.0.0.1',
            '3306',
            'root',
            'SQLSTATE[HY000] [2002] the target machine actively refused it.',
        )->getMessage();

        // The end of both roads — the run nobody was there to ask, and the one that asked and
        // was told the same address three times — so it is the only place the escapes are
        // written down.
        $this->assertStringContainsString('No answer from 127.0.0.1:3306 as [root]', $message);
        $this->assertStringContainsString('actively refused it.', $message);
        $this->assertStringContainsString('--db-host', $message);
        $this->assertStringContainsString('--db-port', $message);
        $this->assertStringContainsString('--db-connection=sqlite', $message);
    }

    // -- The database name --------------------------------------------------------------------

    #[Test]
    public function a_name_that_is_about_to_become_ddl_is_this_or_nothing(): void
    {
        $this->assertTrue(Database::isValidName('kolesa_local'));
        $this->assertFalse(Database::isValidName('kolesa.local'));
        $this->assertFalse(Database::isValidName('kolesa`; drop database mysql; --'));
        $this->assertFalse(Database::isValidName(''));
    }

    private function shippedConfig(): string
    {
        return <<<'PHP'
            <?php

            return [

                'locales' => [
                    ['code' => 'en', 'default' => true],
                ],

                'fallback' => 'en',

            ];
            PHP;
    }
}
