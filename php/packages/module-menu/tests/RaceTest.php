<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;

/**
 * Several agents writing one menu at the same moment, on a real server.
 *
 * An agent that fills a footer sends its calls side by side, and every one of them rewrites the
 * bounds of everything to its right. Without one writer at a time per menu they deadlocked into
 * a 500 and, worse, left a tree whose bounds no longer added up — a heading vanished from it.
 * Here every call is its own process with its own connection, released together, and the tree
 * has to come out as if they had arrived one after another.
 *
 * sqlite has one writer for the whole file, so the race does not exist there: this runs against
 * MariaDB or MySQL named by `WEBX_TEST_MYSQL_URL` and is skipped without it.
 */
final class RaceTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        if (self::url() === null || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('WEBX_TEST_MYSQL_URL and pcntl are needed: the race is a server\'s.');
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        foreach (['menu_race', 'menu_race_child'] as $name) {
            $app['config']->set("database.connections.{$name}", [
                'driver' => 'mariadb',
                'url' => self::url(),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]);
        }

        $app['config']->set('database.default', 'menu_race');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true])->run();

        Schema::create('things', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->json('title')->nullable();
            $table->string('slug')->default('');
            $table->boolean('published')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    #[Test]
    public function links_added_side_by_side_all_land_where_they_were_asked_to(): void
    {
        $catalog = $this->heading('Catalog');
        $buyers = $this->heading('Buyers');

        $calls = [];

        foreach (range(0, 5) as $index) {
            $calls[] = ['add_link', ['menu' => 'footer', 'target' => 'url', 'url' => "/catalog/{$index}", 'title' => "Catalog {$index}", 'parent' => $catalog->getKey(), 'index' => $index]];
        }

        foreach (range(0, 4) as $index) {
            $calls[] = ['add_link', ['menu' => 'footer', 'target' => 'url', 'url' => "/buyers/{$index}", 'title' => "Buyers {$index}", 'parent' => $buyers->getKey(), 'index' => $index]];
        }

        $answers = $this->together($calls);

        $this->assertSame(array_fill(0, count($calls), 'ok'), $answers);
        $this->assertTreeIsWhole();
        $this->assertSame(['Catalog', 'Buyers'], $this->labelsUnder(null));

        // Each call is applied as if it had come alone, in the order the menu let them in — which
        // is the order of their ids, since an id is handed out once the menu is held.
        foreach ([$catalog, $buyers] as $heading) {
            $added = MenuItem::query()->where('parent_id', $heading->getKey())->orderBy('id')->get();
            $expected = [];

            foreach ($added as $item) {
                $index = (int) substr((string) $item->url, strrpos((string) $item->url, '/') + 1);
                array_splice($expected, min($index, count($expected)), 0, [(string) $item->getTranslation('title', 'en')]);
            }

            $this->assertCount($heading->is($catalog) ? 6 : 5, $added);
            $this->assertSame($expected, $this->labelsUnder($heading));
        }
    }

    #[Test]
    public function moves_removals_and_additions_side_by_side_leave_a_whole_tree(): void
    {
        $from = $this->heading('From');
        $to = $this->heading('To');
        $kept = $this->heading('Kept');

        $children = [];

        foreach (range(1, 6) as $number) {
            $children[$number] = $this->child($from, "Child {$number}");
        }

        $calls = [
            ['move_link', ['menu' => 'footer', 'item' => $children[1]->getKey(), 'parent' => $to->getKey(), 'index' => 0]],
            ['move_link', ['menu' => 'footer', 'item' => $children[2]->getKey(), 'parent' => $to->getKey(), 'index' => 0]],
            ['move_link', ['menu' => 'footer', 'item' => $children[3]->getKey(), 'parent' => $to->getKey(), 'index' => 1]],
            ['move_link', ['menu' => 'footer', 'item' => $children[4]->getKey(), 'parent' => null, 'index' => 0]],
            ['remove_link', ['menu' => 'footer', 'item' => $children[5]->getKey()]],
            ['remove_link', ['menu' => 'footer', 'item' => $children[6]->getKey()]],
            ['add_link', ['menu' => 'footer', 'target' => 'none', 'title' => 'New one', 'parent' => $to->getKey(), 'index' => 0]],
            ['add_link', ['menu' => 'footer', 'target' => 'none', 'title' => 'New two', 'parent' => $kept->getKey()]],
        ];

        $answers = $this->together($calls);

        $this->assertSame(array_fill(0, count($calls), 'ok'), $answers);
        $this->assertTreeIsWhole();

        $this->assertSame(['Child 4', 'From', 'To', 'Kept'], $this->labelsUnder(null));
        $this->assertSame([], $this->labelsUnder($from));
        $this->assertEqualsCanonicalizing(['Child 1', 'Child 2', 'Child 3', 'New one'], $this->labelsUnder($to));
        $this->assertSame(['New two'], $this->labelsUnder($kept));
        $this->assertSame(9, MenuItem::query()->count());
    }

    /**
     * Every call in a process of its own, all of them let go at once.
     *
     * @param  list<array{string, array<string, mixed>}>  $calls
     * @return list<string> "ok", or what the call answered instead
     */
    private function together(array $calls): array
    {
        $base = sys_get_temp_dir().'/webx-menu-race-'.getmypid();
        $go = $base.'-go';
        $this->files[] = $go;
        @unlink($go);

        $children = [];

        foreach ($calls as $number => [$tool, $arguments]) {
            $answer = "{$base}-{$number}";
            $this->files[] = $answer;
            @unlink($answer);

            $pid = pcntl_fork();

            if ($pid === 0) {
                config(['database.default' => 'menu_race_child']);

                while (! file_exists($go)) {
                    usleep(1_000);
                    clearstatcache();
                }

                file_put_contents($answer, $this->answer($tool, $arguments));

                // No shutdown: the parent's connection, copied into this process, stays untouched.
                posix_kill(posix_getpid(), SIGKILL);
            }

            $children[$number] = $pid;
        }

        touch($go);

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $answers = [];

        foreach (array_keys($calls) as $number) {
            $answers[] = (string) @file_get_contents("{$base}-{$number}");
        }

        return $answers;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function answer(string $tool, array $arguments): string
    {
        try {
            $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('menu_'.$tool));
            $response = WebxServer::tool($bound, $arguments);
            // What the agent read instead of an answer, word for word: a 500 and a refusal that
            // asks it to try again are different failures, and the message is how they differ.
            $errors = (fn (): array => $this->errors())->call($response);

            return $errors === [] ? 'ok' : $tool.': '.implode(' ', array_map(strval(...), $errors));
        } catch (Throwable $e) {
            return $tool.' failed: '.$e->getMessage();
        }
    }

    private function heading(string $title): MenuItem
    {
        return $this->item(['target' => 'none', 'is_heading' => true, 'title' => ['en' => $title]], $this->menu('footer'));
    }

    private function child(MenuItem $parent, string $title): MenuItem
    {
        return $this->item(['target' => 'none', 'title' => ['en' => $title]], $this->menu('footer'), $parent);
    }

    private function assertTreeIsWhole(): void
    {
        $menu = Menu::query()->where('key', 'footer')->firstOrFail();

        $this->assertSame([], MenuItem::checkTreeIntegrity(['menu_id' => $menu->getKey()]));
    }

    /**
     * @return list<string>
     */
    private function labelsUnder(?MenuItem $parent): array
    {
        return array_values(array_map(
            static fn (MenuItem $item): string => (string) $item->getTranslation('title', 'en'),
            MenuItem::query()->where('parent_id', $parent?->getKey())->ordered()->get()->all(),
        ));
    }

    private static function url(): ?string
    {
        $url = getenv('WEBX_TEST_MYSQL_URL');

        return is_string($url) && $url !== '' ? $url : null;
    }
}
