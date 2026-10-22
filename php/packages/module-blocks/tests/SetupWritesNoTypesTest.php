<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Setup\Catalogue;
use WebxUi\Admin\Setup\Processes;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Blocks\Models\Block;

/**
 * `webx:setup` on a clean database leaves the block types empty (BLOCK_SCHEMAS §1): the hosting
 * platform makes its staging and production sites with it, and a type reaches those only by a
 * person's import. A dev copy runs the commands setup prints.
 *
 * The children that could write a type run for real, in this process — the migrations, the
 * theme's sync, the block commands and the demo — so that setup calling any of them again fills
 * the table and fails here. Composer, npm and the rest are stood in for.
 */
final class SetupWritesNoTypesTest extends TestCase
{
    /** The children that write into the tables, or could; run in-process when setup asks. */
    private const array REAL = ['migrate', 'webx:theme:sync', 'webx:blocks:import', 'webx:blocks:offered', 'webx:demo'];

    private Filesystem $files;

    private string $dir;

    private string $composer = '';

    /** @var list<string> */
    private array $ran = [];

    /** @var list<string> */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $installed = $this->app->basePath('setup-blocks-installed.json');

        $this->files->put($installed, (string) json_encode(['packages' => array_map(
            static fn (string $name): array => ['name' => $name, 'version' => '0.28.0'],
            ['webx-ui/module-admin', 'webx-ui/module-auth', 'webx-ui/module-blocks', 'webx-ui/widgets'],
        )]));

        $this->app->instance(Catalogue::class, new Catalogue($this->files, $installed));
        $this->app->instance(Processes::class, $this->processes());

        // An offer of the chosen module, for `webx:blocks:offered --install` to find.
        $this->dir = storage_path('framework/testing/setup-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/notice.json', (string) json_encode([
            'slug' => 'notice',
            'title' => 'Notice',
            'schema' => [['id' => 'title', 'type' => 'wx-input']],
            'template' => '<aside data-wx-block="notice">{{ $title }}</aside>',
            'sample' => ['title' => 'Hello'],
        ]));
        $this->app->make(BlockOffers::class)->offer('blocks', $this->dir);

        // The demo journal too: a setup that seeded the demo again would leave it for the next test.
        $this->written = [$installed, $this->app->basePath('.env'), $this->app->databasePath('database.sqlite'), storage_path('app/webx-demo.json')];
        $this->files->put($this->app->basePath('.env'), "APP_NAME=Laravel\nDB_CONNECTION=sqlite\n");
        $this->composer = (string) $this->files->get($this->app->basePath('composer.json'));
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            $this->files->delete($path);
        }

        $this->files->put($this->app->basePath('composer.json'), $this->composer);
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function setup_on_a_clean_database_leaves_no_block_types(): void
    {
        $this->assertSame(0, Block::query()->count());

        $this->artisan('webx:setup', [
            '--modules' => 'blocks,admins',
            '--db-connection' => 'sqlite',
            '--admin' => 'admin@example.test',
            '--no-build' => true,
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('php artisan webx:blocks:offered --install')
            ->assertSuccessful();

        $this->assertContains('migrate --force', $this->ran, 'the migrations did run');
        $this->assertSame(0, Block::query()->count());

        // The printed command is what puts them on: the offer is real, and so is the table.
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['blocks']])->assertSuccessful();
        $this->assertContains('notice', Block::query()->pluck('slug')->all());
    }

    private function processes(): Processes
    {
        $test = $this;

        return new class(new Composer($this->files), $test) extends Processes
        {
            public function __construct(Composer $composer, private readonly SetupWritesNoTypesTest $test)
            {
                parent::__construct($composer);
            }

            public function run(array $command, string $cwd, array $env = [], ?callable $output = null): int
            {
                return $this->test->ran($command);
            }

            public function artisan(string $base): array
            {
                return ['artisan'];
            }

            public function composer(string $base, ?string $named = null): array
            {
                return ['composer'];
            }

            public function npm(): array
            {
                return ['npm'];
            }
        };
    }

    /** @param  list<string>  $command */
    public function ran(array $command): int
    {
        if ($command[0] !== 'artisan' || ! in_array($command[1] ?? '', self::REAL, true)) {
            return 0;
        }

        $line = implode(' ', array_slice($command, 1));
        $this->ran[] = $line;

        return array_key_exists($command[1], Artisan::all()) ? Artisan::call($line) : 0;
    }
}
