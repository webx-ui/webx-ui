<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

use PDO;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use WebxUi\Admin\Setup\Catalogue;
use WebxUi\Admin\Setup\Database;
use WebxUi\Admin\Setup\EnvFile;
use WebxUi\Admin\Setup\LocalesConfig;
use WebxUi\Admin\Setup\SetupFailed;

/**
 * Everything between an empty directory and a panel somebody can sign into.
 *
 * `composer create-project webx-ui/site example.local` calls this once, and running it again on
 * a site that has been live for months is the supported way of installing a module: it asks the
 * same questions with the answers already in the file as the defaults, adds what is missing and
 * leaves alone what is not.
 *
 * Almost everything it does runs as its own `artisan` process, and that is not tidiness. This
 * process booted before the `.env` it is about to write and before the packages it is about to
 * install — its configuration is the old file and its autoloader has never heard of
 * `module-blog`. A child gets both. It also settles the one about published views for free:
 * `loadViewsFrom` decides whether `resources/views/vendor/<package>` exists when the view
 * factory is resolved, so a process that publishes and then renders still renders the package's
 * copy.
 */
final class SetupCommand extends Command
{
    protected $signature = 'webx:setup
                            {--name= : What the site is called}
                            {--domain= : The domain it answers on}
                            {--modules= : Which modules to install, comma separated, or "all"}
                            {--locales= : The languages it publishes in, comma separated; the first is the default}
                            {--db= : The database name}
                            {--db-connection= : mysql, mariadb, pgsql or sqlite}
                            {--db-host= : Where the database server is}
                            {--db-port= : The port it listens on}
                            {--db-username= : The user to connect as}
                            {--db-password= : That user\'s password}
                            {--admin= : The first administrator\'s email address}
                            {--admin-name= : What to call them}
                            {--demo : Fill the site with demo content}
                            {--no-demo : Leave the site empty}
                            {--no-build : Leave npm alone — no install, no build}
                            {--composer= : The Composer binary, when it is not on the PATH}';

    protected $description = 'Set this application up as a WebX UI site: database, modules, panel, administrator';

    private EnvFile $env;

    private Catalogue $catalogue;

    private Composer $composer;

    /** @var array<string, string> */
    private array $answers = [];

    /** @var list<string> */
    private array $modules = [];

    /**
     * What every child process is told, over the top of what it inherits.
     *
     * Not belt and braces: this process read `.env` when it booted, and what it read is in
     * `$_ENV`, which is what a child inherits — and Laravel reads the file immutably in both
     * directions, so a name already in the environment beats the same name in the file. A
     * child spawned after the file was rewritten therefore migrates the database the file used
     * to name, and says nothing, because that database usually exists too.
     *
     * @var array<string, string>
     */
    private array $childEnv = [];

    public function handle(Filesystem $files, Catalogue $catalogue, Composer $composer): int
    {
        $this->catalogue = $catalogue;
        // The container binds this under the string `composer`, with the application's base
        // path already in it; injected by class name it is built fresh with no path at all,
        // and `findComposer()` then looks for `/composer.phar` at the root of the drive.
        $this->composer = $composer->setWorkingPath($this->laravel->basePath());
        $this->env = new EnvFile($files, $this->laravel->basePath('.env'));

        if (! $this->env->exists()) {
            $this->components->error(SetupFailed::noEnv($this->env->path())->getMessage());

            return self::FAILURE;
        }

        try {
            $this->gather();
            $this->writeEnvironment();
            $this->prepareDatabase();
            $this->installModules();
            $this->wireThePanel($files);
            $this->migrate();
            $this->createAdministrator();
            $this->build();
            $this->seedDemo($files);
            $this->callTheDoctor();
        } catch (SetupFailed $failure) {
            $this->newLine();
            $this->components->error($failure->getMessage());

            return self::FAILURE;
        }

        $this->summarise();

        return self::SUCCESS;
    }

    // -- The questions ---------------------------------------------------------------------

    /**
     * Every answer, from the options when they are there and from the person when they are not.
     *
     * The defaults are worked out from the directory, the file and `git config` rather than
     * invented: on a new site the directory is the domain, and on a site being changed the
     * answers are already in `.env`.
     */
    private function gather(): void
    {
        $directory = basename($this->laravel->basePath());
        $firstLabel = Str::before($directory, '.');

        $this->answers['name'] = $this->answer(
            'name',
            'What the site is called',
            $this->env->chosen('APP_NAME', ['Laravel']) ?? Str::headline($firstLabel),
        );

        $this->answers['domain'] = $this->answer(
            'domain',
            'The domain it answers on',
            $this->domainFromUrl() ?? $directory,
        );

        $this->answers['locales'] = $this->answer(
            'locales',
            'The languages it publishes in, the first one the default',
            'en',
        );

        $this->modules = $this->chooseModules();

        $this->answers['db-connection'] = $this->option('db-connection')
            ?? $this->env->chosen('DB_CONNECTION') ?? 'mysql';

        if ($this->answers['db-connection'] !== 'sqlite') {
            $this->answers['db-host'] = (string) ($this->option('db-host') ?? $this->env->chosen('DB_HOST') ?? '127.0.0.1');
            $this->answers['db-port'] = (string) ($this->option('db-port') ?? $this->env->chosen('DB_PORT') ?? '3306');
            $this->answers['db-username'] = (string) ($this->option('db-username') ?? $this->env->chosen('DB_USERNAME') ?? 'root');
            $this->answers['db-password'] = (string) ($this->option('db-password') ?? $this->env->get('DB_PASSWORD') ?? '');

            $this->answers['db'] = $this->answer(
                'db',
                'The database, created if it is not there',
                $this->env->chosen('DB_DATABASE', ['laravel']) ?? Str::snake(str_replace('-', '_', $firstLabel)),
            );

            if (! Database::isValidName($this->answers['db'])) {
                throw SetupFailed::badDatabaseName($this->answers['db']);
            }
        }

        $this->answers['demo'] = $this->wantsDemo() ? 'yes' : 'no';

        if (in_array('admins', $this->modules, true)) {
            $this->answers['admin'] = $this->answer(
                'admin',
                'The first administrator, by email address',
                $this->fromGit('user.email') ?? '',
            );

            $this->answers['admin-name'] = (string) ($this->option('admin-name')
                ?? $this->fromGit('user.name') ?? 'Administrator');
        }
    }

    /** An option if it was given, the person's answer otherwise, the default when nobody is there. */
    private function answer(string $option, string $question, string $default): string
    {
        $given = $this->option($option);

        if (is_string($given) && $given !== '') {
            return $given;
        }

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return trim(text(label: $question, default: $default, required: false)) ?: $default;
    }

    /** @return list<string> */
    private function chooseModules(): array
    {
        $given = $this->option('modules');

        if (is_string($given) && $given !== '') {
            $ids = $given === 'all'
                ? $this->catalogue->ids()
                : array_values(array_filter(array_map(trim(...), explode(',', $given))));

            foreach ($ids as $id) {
                if (! $this->catalogue->knows($id)) {
                    throw SetupFailed::unknownModule($id, implode(', ', $this->catalogue->ids()));
                }
            }

            // Whatever is installed stays installed: this command adds, it does not uninstall.
            return array_values(array_unique([...$ids, ...$this->catalogue->installed()]));
        }

        if (! $this->input->isInteractive()) {
            return $this->catalogue->suggested();
        }

        /** @var list<string> $chosen */
        $chosen = multiselect(
            label: 'Which modules the panel has',
            options: $this->catalogue->options(),
            default: $this->catalogue->suggested(),
            hint: 'What is already installed stays, whether it is ticked or not.',
        );

        return array_values(array_unique([...$chosen, ...$this->catalogue->installed()]));
    }

    private function wantsDemo(): bool
    {
        if ($this->option('no-demo')) {
            return false;
        }

        if ($this->option('demo') || ! $this->input->isInteractive()) {
            return true;
        }

        return confirm(label: 'Fill it with demo content to look at', default: true);
    }

    // -- The steps -------------------------------------------------------------------------

    private function writeEnvironment(): void
    {
        $url = Str::startsWith($this->answers['domain'], ['http://', 'https://'])
            ? rtrim($this->answers['domain'], '/')
            : 'http://'.$this->answers['domain'];

        $values = [
            'APP_NAME' => $this->answers['name'],
            'APP_URL' => $url,
            'APP_LOCALE' => LocalesConfig::codes($this->answers['locales'])[0] ?? 'en',
            'WEBX_ADMIN_TITLE' => $this->answers['name'],
            'DB_CONNECTION' => $this->answers['db-connection'],
        ];

        if ($this->answers['db-connection'] !== 'sqlite') {
            $values += [
                'DB_HOST' => $this->answers['db-host'],
                'DB_PORT' => $this->answers['db-port'],
                'DB_DATABASE' => $this->answers['db'],
                'DB_USERNAME' => $this->answers['db-username'],
                'DB_PASSWORD' => $this->answers['db-password'],
            ];
        }

        $this->env->write($values);
        $this->childEnv = $values;

        $this->components->twoColumnDetail('.env', 'written — '.implode(', ', array_keys($values)));
    }

    private function prepareDatabase(): void
    {
        if ($this->answers['db-connection'] === 'sqlite') {
            $path = $this->laravel->databasePath('database.sqlite');

            if (! file_exists($path)) {
                touch($path);
            }

            $this->components->twoColumnDetail('Database', 'sqlite at database/database.sqlite');

            return;
        }

        $server = new Database(
            $this->answers['db-connection'] === 'mariadb' ? 'mysql' : $this->answers['db-connection'],
            $this->answers['db-host'],
            (int) $this->answers['db-port'],
            $this->answers['db-username'],
            $this->answers['db-password'],
        );

        $refused = $server->unreachable();

        if ($refused !== null) {
            throw new SetupFailed(sprintf(
                'No answer from %s:%s as [%s] — %s. Start the server, or say where it is with '
                .'--db-host and --db-port, or pass --db-connection=sqlite to stand on a file. '
                .'sqlite hides three things a real server refuses, so it is worth a minute of '
                .'looking first.',
                $this->answers['db-host'],
                $this->answers['db-port'],
                $this->answers['db-username'],
                rtrim($refused, '. '),
            ));
        }

        if ($server->has($this->answers['db'])) {
            $this->components->twoColumnDetail('Database', $this->answers['db'].' is already there');

            return;
        }

        $server->create($this->answers['db']);
        $this->components->twoColumnDetail('Database', $this->answers['db'].' created, utf8mb4');
    }

    private function installModules(): void
    {
        $wanted = $this->catalogue->toRequire($this->modules);

        if ($wanted === []) {
            $this->components->twoColumnDetail('Modules', 'already installed: '.implode(', ', $this->modules));

            return;
        }

        $this->components->info('Installing '.implode(', ', $wanted).' — this is the slow part.');

        $this->mustRun(
            [...$this->composerBinary(), 'require', ...$wanted, '--no-interaction', '--no-progress'],
            'composer require',
        );

        // The list this command read at boot is now a list of what used to be installed.
        $this->catalogue->refresh();
    }

    private function wireThePanel(Filesystem $files): void
    {
        $this->artisan(['webx:install'], 'webx:install');

        $this->setLocales($files);

        $this->artisan(['webx:panel', '--sync'], 'webx:panel --sync');

        if (! $files->exists($this->laravel->publicPath('storage'))) {
            // Not fatal: on Windows a symlink needs a privilege the shell may not have, and a
            // panel that works apart from the pictures beats no panel at all.
            $this->artisan(['storage:link'], 'storage:link', fatal: false);
        }

        $this->routeTheDemoPage($files);
    }

    /**
     * Publish the language list and fill it in.
     *
     * The publish is its own process, and so is everything that renders afterwards: a command
     * that publishes and then reads is a command that reads the package's copy.
     */
    private function setLocales(Filesystem $files): void
    {
        $codes = LocalesConfig::codes($this->answers['locales']);

        if ($codes === []) {
            return;
        }

        $path = $this->laravel->configPath('webx-localization.php');

        if (! $files->exists($path)) {
            $this->artisan(
                ['vendor:publish', '--tag=webx-localization-config', '--quiet'],
                'vendor:publish webx-localization-config',
            );
        }

        if (! $files->exists($path)) {
            $this->components->warn('config/webx-localization.php: name the languages in `locales` by hand.');

            return;
        }

        $rewritten = LocalesConfig::rewrite((string) $files->get($path), $codes);

        if ($rewritten === null) {
            $this->components->twoColumnDetail(
                'config/webx-localization.php',
                'left as it is — `locales` is not the list that shipped',
            );

            return;
        }

        $files->put($path, $rewritten);
        $this->components->twoColumnDetail('Languages', implode(', ', $codes).' — the first is the default');
    }

    /**
     * Put the placeholder page on `/`, or take it off again.
     *
     * A site without the pages module has nothing to answer its own address with, and a brand
     * new site that says 404 to it looks broken rather than empty. With the module, that same
     * route is the trap: the home page of the tree is an empty slug formatted to `/`, and a
     * route here holds the address against it for good — the page cannot even be saved.
     */
    private function routeTheDemoPage(Filesystem $files): void
    {
        $path = $this->laravel->basePath('routes/web.php');
        $marker = '// webx:demo-page';
        $route = $marker."\n\\Illuminate\\Support\\Facades\\Route::view('/', 'demo');\n";

        if (! $files->exists($path)) {
            return;
        }

        $contents = (string) $files->get($path);
        $routed = str_contains($contents, $marker);
        $hasPages = $this->catalogue->has('webx-ui/module-pages');

        if ($hasPages && $routed) {
            $files->put($path, trim(str_replace($route, '', $contents))."\n");
            $this->components->twoColumnDetail('routes/web.php', 'the placeholder route is gone — / is the home page now');

            return;
        }

        // A site that has written routes of its own has answered this question already. At the
        // start of a line, because the file that ships explains this trap in prose and names
        // `Route::get('/')` while doing it.
        if ($hasPages || $routed || preg_match('/^[^\S\n]*\\\\?(?:Illuminate\\\\Support\\\\Facades\\\\)?Route::/m', $contents) === 1) {
            return;
        }

        $files->put($path, rtrim($contents)."\n\n".$route);
        $this->components->twoColumnDetail('routes/web.php', 'the placeholder page answers / until the site has one');
    }

    private function migrate(): void
    {
        // Passport only publishes its migrations; the tables an agent's token lives in do not
        // exist until somebody does. Publishing them twice is the thing to avoid: the copy is
        // stamped with the time it was made, so a second run leaves two files creating the
        // same table and `migrate` stops on the second of them.
        if ($this->catalogue->has('laravel/passport') && $this->missingPassportMigrations()) {
            $this->artisan(
                ['vendor:publish', '--tag=passport-migrations', '--quiet'],
                'vendor:publish passport-migrations',
            );
        }

        $this->artisan(['migrate', '--force'], 'migrate');
        $this->artisan(['webx:locales:seed'], 'webx:locales:seed');

        // Without the keys the API guard cannot even be built, and a call with no token at all
        // answers 500 where it should answer 401. Asked for twice it refuses rather than
        // overwrites, which is right and reads like a failure, so it is asked for once.
        if ($this->catalogue->has('laravel/passport') && ! file_exists($this->laravel->storagePath('oauth-private.key'))) {
            $this->artisan(['passport:keys', '--quiet'], 'passport:keys', fatal: false);
        }
    }

    private function missingPassportMigrations(): bool
    {
        return glob($this->laravel->databasePath('migrations/*_create_oauth_auth_codes_table.php')) === [];
    }

    private function createAdministrator(): void
    {
        if (! in_array('admins', $this->modules, true)) {
            $this->components->warn('No sign-in module, so no administrator — and the panel is open to anybody who finds it.');

            return;
        }

        if ($this->administrators() > 0) {
            $this->components->twoColumnDetail('Administrator', 'there is one already — `php artisan webx:admin` adds another');

            return;
        }

        $email = $this->answers['admin'] ?? '';

        if ($email === '') {
            $this->components->warn('Nobody to sign in as. `php artisan webx:admin --super` creates the first administrator.');

            return;
        }

        // The same variable `webx:admin` reads, so a provisioning script that already sets it
        // keeps the password it chose; otherwise one is made here and said once at the end.
        $given = getenv('WEBX_ADMIN_PASSWORD');
        $password = is_string($given) && $given !== '' ? $given : Str::random(20);

        if ($password !== $given) {
            $this->answers['password'] = $password;
        }

        // It travels as an environment variable rather than an option, the way `webx:admin`
        // asks for it: an argument lands in the shell history and in `ps`.
        $this->artisan(
            ['webx:admin', '--name='.($this->answers['admin-name'] ?? 'Administrator'), '--email='.$email, '--super'],
            'webx:admin',
            env: ['WEBX_ADMIN_PASSWORD' => $password],
        );
    }

    /**
     * How many administrators the site has, asked over PDO.
     *
     * Not through Eloquent: this process is connected to whatever `.env` said when it started,
     * which is not the database it has since created.
     */
    private function administrators(): int
    {
        if ($this->answers['db-connection'] === 'sqlite') {
            try {
                $found = (new PDO('sqlite:'.$this->laravel->databasePath('database.sqlite')))
                    ->query('select count(*) from cms_users')?->fetchColumn();
            } catch (\PDOException) {
                return 0;
            }

            return is_numeric($found) ? (int) $found : 0;
        }

        $count = (new Database(
            $this->answers['db-connection'] === 'mariadb' ? 'mysql' : $this->answers['db-connection'],
            $this->answers['db-host'],
            (int) $this->answers['db-port'],
            $this->answers['db-username'],
            $this->answers['db-password'],
        ))->count($this->answers['db'], 'cms_users');

        return $count ?? 0;
    }

    private function build(): void
    {
        if ($this->option('no-build')) {
            $this->components->warn('npm left alone — the panel will not answer until `npm install && npm run build`.');

            return;
        }

        $this->components->info('npm install && npm run build — the other slow part.');

        $npm = $this->npmBinary();

        // Not fatal, and this is the step where that matters most: it is the last long one, the
        // administrator already exists, and a password printed nowhere is a site nobody can
        // sign into. `npm run build` is one line to run again.
        $this->mustRun([...$npm, 'install'], 'npm install', fatal: false);
        $this->mustRun([...$npm, 'run', 'build'], 'npm run build', fatal: false);
    }

    private function seedDemo(Filesystem $files): void
    {
        if ($this->answers['demo'] !== 'yes') {
            return;
        }

        if ($files->exists($this->laravel->storagePath('app/webx-demo.json'))) {
            $this->components->twoColumnDetail('Demo content', 'already there — `php artisan webx:demo --remove` takes it out');

            return;
        }

        // Not fatal: demo content is the least of what this command does, and a module that
        // refuses to seed has already said why.
        $this->artisan(['webx:demo'], 'webx:demo', fatal: false);
    }

    private function callTheDoctor(): void
    {
        if (! $this->getApplication()?->has('webx:doctor')) {
            return;
        }

        $this->newLine();
        $this->artisan(['webx:doctor'], 'webx:doctor', fatal: false);
    }

    private function summarise(): void
    {
        $path = '/'.ltrim((string) ($this->env->get('WEBX_ADMIN_PATH') ?: 'cms'), '/');
        $url = rtrim((string) $this->env->get('APP_URL'), '/');

        $this->newLine();
        $this->components->info($this->answers['name'].' is set up.');
        $this->components->twoColumnDetail('Site', $url);
        $this->components->twoColumnDetail('Panel', $url.$path);

        if (isset($this->answers['password'])) {
            $this->components->twoColumnDetail('Sign in', $this->answers['admin']);
            $this->components->twoColumnDetail('Password', $this->answers['password']);
            $this->components->warn('That password is printed once and stored nowhere. Change it from the panel.');
        }
    }

    // -- Running things --------------------------------------------------------------------

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $env
     */
    private function artisan(array $arguments, string $what, array $env = [], bool $fatal = true): void
    {
        $this->mustRun([PHP_BINARY, $this->laravel->basePath('artisan'), ...$arguments], $what, $env, $fatal);
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    private function mustRun(array $command, string $what, array $env = [], bool $fatal = true): void
    {
        $process = new Process($command, $this->laravel->basePath(), $env + $this->childEnv);
        // No timeout: `composer require` of seven packages and `npm install` both take minutes
        // on a cold cache, and a run killed halfway leaves the site between two states.
        $process->setTimeout(null);

        $status = $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if ($status === 0) {
            return;
        }

        if ($fatal) {
            throw SetupFailed::step($what, $status);
        }

        $this->components->warn("{$what} exited with {$status} — run it yourself when you have read what it said.");
    }

    /** @return list<string> */
    private function composerBinary(): array
    {
        $named = $this->option('composer');

        return $this->executable(
            $this->composer->findComposer(is_string($named) && $named !== '' ? $named : null),
            'composer',
        );
    }

    /** @return list<string> */
    private function npmBinary(): array
    {
        return $this->executable(['npm'], 'npm');
    }

    /**
     * A bare name is not a program on Windows: `proc_open` gets the array as it is, so
     * `npm` — which is `npm.cmd` — is simply not found. The finder knows about PATHEXT.
     *
     * @param  list<string>  $found
     * @return list<string>
     */
    private function executable(array $found, string $name): array
    {
        if ($found !== [$name]) {
            return $found;
        }

        $path = (new ExecutableFinder)->find($name);

        return $path === null ? $found : [$path];
    }

    private function domainFromUrl(): ?string
    {
        $url = $this->env->chosen('APP_URL', ['http://localhost', 'http://localhost:8000']);
        $host = $url === null ? null : parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' && $host !== 'localhost' ? $host : null;
    }

    /** Whatever git already knows about the person running this, so they do not retype it. */
    private function fromGit(string $key): ?string
    {
        $process = new Process(['git', 'config', '--get', $key], $this->laravel->basePath());
        $process->setTimeout(5);
        $process->run();

        $value = trim($process->getOutput());

        return $process->isSuccessful() && $value !== '' ? $value : null;
    }
}
