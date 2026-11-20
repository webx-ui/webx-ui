<?php

declare(strict_types=1);

namespace WebxUi\NestedSet\Tests;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\NestedSet\NestedSet;
use WebxUi\NestedSet\Tests\Fixtures\Page;

/**
 * Placements in one tree at one moment, each from a process and a connection of its own.
 *
 * Every placement rewrites the bounds of everything to its right, so two of them side by side
 * used to deadlock, or to compute a position from bounds the other had just moved and leave a
 * tree that no longer adds up. Every tree built on this — pages, categories, menu items, media
 * folders — is written by a panel and by agents at once, so the placements line up behind a
 * hold on the tree and come out as if they had come one after another.
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
        foreach (['tree_race', 'tree_race_child'] as $name) {
            $app['config']->set("database.connections.{$name}", [
                'driver' => 'mariadb',
                'url' => self::url(),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]);
        }

        $app['config']->set('database.default', 'tree_race');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::dropIfExists('pages');
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id')->index();
            $table->string('name');
            NestedSet::columns($table);
            $table->timestamps();
        });
    }

    #[Test]
    public function nodes_placed_side_by_side_all_land_and_the_tree_adds_up(): void
    {
        $shop = $this->root(1, 'Shop');
        $help = $this->root(1, 'Help');
        $moving = [];

        foreach (range(1, 3) as $number) {
            $moving[$number] = $this->under($help, "Moving {$number}");
        }

        $elsewhere = $this->root(2, 'Elsewhere');

        $work = [];

        foreach (range(1, 6) as $number) {
            $work[] = static fn () => (new Page(['site_id' => 1, 'name' => "Shop {$number}"]))->appendTo(Page::query()->findOrFail($shop->id));
        }

        foreach (range(1, 3) as $number) {
            $work[] = static fn () => (new Page(['site_id' => 1, 'name' => "First {$number}"]))->prependTo(Page::query()->findOrFail($help->id));
        }

        foreach ($moving as $node) {
            $work[] = static fn () => Page::query()->findOrFail($node->id)->appendTo(Page::query()->findOrFail($shop->id));
        }

        foreach (range(1, 2) as $number) {
            $work[] = static fn () => (new Page(['site_id' => 2, 'name' => "Other {$number}"]))->appendTo(Page::query()->findOrFail($elsewhere->id));
        }

        $this->assertSame(array_fill(0, count($work), 'ok'), $this->together($work));

        $this->assertSame([], Page::checkTreeIntegrity(['site_id' => 1]));
        $this->assertSame([], Page::checkTreeIntegrity(['site_id' => 2]));

        $this->assertSame(['Shop', 'Help'], $this->namesUnder(1, null));
        $this->assertEqualsCanonicalizing(
            ['Shop 1', 'Shop 2', 'Shop 3', 'Shop 4', 'Shop 5', 'Shop 6', 'Moving 1', 'Moving 2', 'Moving 3'],
            $this->namesUnder(1, $shop->id),
        );
        $this->assertEqualsCanonicalizing(['First 1', 'First 2', 'First 3'], $this->namesUnder(1, $help->id));
        $this->assertEqualsCanonicalizing(['Other 1', 'Other 2'], $this->namesUnder(2, $elsewhere->id));
    }

    /**
     * Every piece of work in a process of its own, all of them let go at once.
     *
     * @param  list<Closure(): mixed>  $work
     * @return list<string> "ok", or what was thrown instead
     */
    private function together(array $work): array
    {
        $base = sys_get_temp_dir().'/webx-tree-race-'.getmypid();
        $go = $base.'-go';
        $this->files[] = $go;
        @unlink($go);

        $children = [];

        foreach ($work as $number => $piece) {
            $answer = "{$base}-{$number}";
            $this->files[] = $answer;
            @unlink($answer);

            $pid = pcntl_fork();

            if ($pid === 0) {
                config(['database.default' => 'tree_race_child']);

                while (! file_exists($go)) {
                    usleep(1_000);
                    clearstatcache();
                }

                try {
                    $piece();
                    file_put_contents($answer, 'ok');
                } catch (Throwable $e) {
                    file_put_contents($answer, $e->getMessage());
                }

                // No shutdown: the parent's connection, copied into this process, stays untouched.
                posix_kill(posix_getpid(), SIGKILL);
            }

            $children[] = $pid;
        }

        touch($go);

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        return array_map(
            static fn (int $number): string => (string) @file_get_contents("{$base}-{$number}"),
            array_keys($work),
        );
    }

    private function root(int $site, string $name): Page
    {
        $page = new Page(['site_id' => $site, 'name' => $name]);
        $page->saveAsRoot();

        return $page;
    }

    private function under(Page $parent, string $name): Page
    {
        $page = new Page(['site_id' => $parent->site_id, 'name' => $name]);
        $page->appendTo($parent);

        return $page;
    }

    /**
     * @return list<string>
     */
    private function namesUnder(int $site, ?int $parent): array
    {
        return array_values(array_map(
            static fn (Page $page): string => $page->name,
            Page::query()->where('site_id', $site)->where('parent_id', $parent)->ordered()->get()->all(),
        ));
    }

    private static function url(): ?string
    {
        $url = getenv('WEBX_TEST_MYSQL_URL');

        return is_string($url) && $url !== '' ? $url : null;
    }
}
