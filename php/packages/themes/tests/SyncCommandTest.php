<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Contracts\SyncStep;
use WebxUi\Themes\Sync\SyncLine;
use WebxUi\Themes\Tests\Fixtures\FakeSyncStep;
use WebxUi\Themes\ThemeChain;

/**
 * `webx:theme:sync` runs what packages registered as a {@see SyncStep} after the assets (spec §9.2,
 * §17): their lines under a heading, and a refused line fails the command once the rest is done.
 */
final class SyncCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // public/themes of the testbench skeleton is not ours to fill.
        $this->app->usePublicPath(sys_get_temp_dir().'/webx-themes-sync-'.getmypid());
    }

    #[Test]
    public function a_registered_step_runs_with_the_options_and_its_lines_are_printed(): void
    {
        $step = $this->step([new SyncLine('hero', 'created from webx-ui/theme-fixture')]);

        $this->artisan('webx:theme:sync', ['--force' => true, '--dry-run' => true])
            ->expectsOutputToContain('Fake step')
            ->expectsOutputToContain('created from webx-ui/theme-fixture')
            ->assertSuccessful();

        $this->assertSame([[true, true]], $step->calls);
    }

    #[Test]
    public function a_refused_line_fails_the_command(): void
    {
        $this->step([new SyncLine('note', 'unchanged'), SyncLine::refused('hero', 'this site has a type of its own by this slug')]);

        $this->artisan('webx:theme:sync')
            ->expectsOutputToContain('this site has a type of its own by this slug')
            ->expectsOutputToContain('1 refused')
            ->assertFailed();
    }

    #[Test]
    public function without_a_theme_no_step_runs(): void
    {
        $step = $this->step([SyncLine::refused('hero', 'never')]);
        $this->app->instance(ThemeChain::class, new ThemeChain);

        $this->artisan('webx:theme:sync')->assertSuccessful();
        $this->assertSame([], $step->calls);
    }

    /**
     * @param  list<SyncLine>  $lines
     */
    private function step(array $lines): FakeSyncStep
    {
        $step = new FakeSyncStep($lines);
        $this->app->instance(FakeSyncStep::class, $step);
        $this->app->tag([FakeSyncStep::class], SyncStep::TAG);

        return $step;
    }
}
