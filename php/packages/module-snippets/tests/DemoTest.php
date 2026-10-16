<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Snippets\Models\Snippet;

/**
 * The demo (§13 of the snippets spec): one code to show what the section holds, switched off,
 * with no id of a real service in it — and gone again with `--remove`.
 */
final class DemoTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_seeds_one_snippet_switched_off(): void
    {
        $this->artisan('webx:demo', ['--module' => ['snippets']])->assertSuccessful();

        $snippet = Snippet::query()->sole();

        $this->assertFalse($snippet->enabled);
        $this->assertFalse($snippet->on_stage);
        $this->assertSame('body_end', $snippet->place);
        $this->assertSame('statistics', $snippet->consent);
        $this->assertSame('Example counter (demo)', $snippet->name);
        $this->assertStringContainsString('console.log', $snippet->code);
        // A comment and a log line: no address to load and no id of somebody's service.
        $this->assertStringNotContainsString('src=', $snippet->code);
        $this->assertStringNotContainsString('http', $snippet->code);

        $this->at('/');
        $this->assertSame('', Blade::render('<x-webx-snippets place="body_end" />'));
    }

    #[Test]
    public function a_site_with_a_snippet_of_its_own_gets_none(): void
    {
        $this->snippet('head', '<script>own()</script>');

        $this->artisan('webx:demo', ['--module' => ['snippets']])->assertSuccessful();

        $this->assertSame(1, Snippet::withTrashed()->count());
    }

    #[Test]
    public function removing_takes_it_back_out(): void
    {
        $this->artisan('webx:demo', ['--module' => ['snippets']])->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Snippet::withTrashed()->count());
    }
}
