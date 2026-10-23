<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Tests;

use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Compositions\Compiler;
use WebxUi\Compositions\Compositions;
use WebxUi\Compositions\Doctor\CompositionsCheck;

/** The compositions in `webx:doctor` (COMPOSITIONS §8). */
final class DoctorTest extends TestCase
{
    #[Test]
    public function a_compiled_set_as_the_sources_give_it_is_ok(): void
    {
        $this->compiled();

        $diagnoses = $this->diagnose();

        $this->assertCount(1, $diagnoses);
        $this->assertSame('ok', $diagnoses[0]->level());
        $this->assertSame('4 views of layout, layout.minimal, compiled and as the sources give them.', $diagnoses[0]->detail);
    }

    #[Test]
    public function nothing_compiled_yet_is_a_note(): void
    {
        $this->assertSame(['note'], $this->levels());
        $this->assertStringContainsString('not compiled yet', $this->diagnose()[0]->detail);
    }

    #[Test]
    public function a_stale_set_is_a_warning_only_while_the_mtime_check_is_off(): void
    {
        $this->compiled();
        $this->config('layout', 'default', ['attrs' => ['body' => ['class' => 'later']]]);

        config()->set('webx-compositions.check', true);
        $this->assertSame(['note'], $this->levels());

        config()->set('webx-compositions.check', false);
        $this->assertSame(['warn'], $this->levels());
        $this->assertStringContainsString('php artisan webx:composition:compile', $this->diagnose()[0]->detail);
    }

    #[Test]
    public function a_finding_of_the_trees_is_a_warning_and_a_broken_file_a_failure(): void
    {
        $this->compiled();
        $this->theme('layout', 'default', ['remove' => ['nothing']]);
        config()->set('webx-compositions.check', true);

        $diagnoses = $this->diagnose();
        $this->assertSame('warn', $diagnoses[0]->level());
        $this->assertStringContainsString('remove nothing — no node with this id', $diagnoses[0]->detail);

        $this->theme('layout', 'default', '{ not json');
        $diagnoses = $this->diagnose();
        $this->assertSame(Diagnosis::FAIL, $diagnoses[0]->state);
        $this->assertStringContainsString('invalid JSON', $diagnoses[0]->detail);
    }

    #[Test]
    public function a_module_page_that_calls_the_layout_without_a_handle_is_a_note(): void
    {
        $this->compiled();
        View::addNamespace('webx-a', self::fixtures().'/module-a/resources/views');

        $diagnoses = $this->diagnose();

        $this->assertSame(['note'], array_map(static fn (Diagnosis $diagnosis): string => $diagnosis->level(), $diagnoses));
        $this->assertStringStartsWith('webx-a::page calls the layout without handle=', $diagnoses[0]->detail);
    }

    /**
     * Compiled a moment ago: a source of the same second as the set may be newer than it (as
     * Blade reads its views), and every test here would see a stale set.
     */
    private function compiled(): void
    {
        $manifest = $this->app->make(Compositions::class)->compile();
        $manifest['compiled_at'] = (int) $manifest['compiled_at'] + 5;
        file_put_contents($this->app->make(Compiler::class)->path().'/manifest.php', '<?php return '.var_export($manifest, true).';'.PHP_EOL);
    }

    /** @return list<Diagnosis> */
    private function diagnose(): array
    {
        return $this->app->make(CompositionsCheck::class)->run();
    }

    /** @return list<string> */
    private function levels(): array
    {
        return array_map(static fn (Diagnosis $diagnosis): string => $diagnosis->level(), $this->diagnose());
    }
}
