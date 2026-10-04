<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Agents\AgentDocs;
use WebxUi\Admin\Agents\RootFile;
use WebxUi\Admin\Panel\PackageRegistry;

/**
 * The site's root AGENTS.md, as `webx:panel` keeps it.
 *
 * Against a fixed vendor in `Fixtures/agents`: one documented package, one without a guide yet
 * and one that is not ours, because what matters is which of them the file names and how.
 */
final class AgentGuideTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;

        $this->app->instance(PackageRegistry::class, new PackageRegistry($this->files, __DIR__.'/Fixtures/installed.json'));
        $this->app->instance(AgentDocs::class, $this->docs());
    }

    protected function tearDown(): void
    {
        $this->files->delete([
            $this->guide(),
            $this->app->basePath('CLAUDE.md'),
            $this->app->configPath('webx-admin.php'),
        ]);
        $this->files->deleteDirectory($this->app->basePath('resources/js'));
        $this->files->deleteDirectory($this->app->basePath('resources/views/components'));

        parent::tearDown();
    }

    private function docs(): AgentDocs
    {
        return new AgentDocs($this->files, __DIR__.'/Fixtures/agents/composer/installed.json');
    }

    private function guide(): string
    {
        return $this->app->basePath('AGENTS.md');
    }

    #[Test]
    public function it_lists_only_webx_packages_and_knows_which_carry_a_guide(): void
    {
        $packages = $this->docs()->packages();

        $this->assertSame(['webx-ui/module-pages', 'webx-ui/module-seo'], array_map(fn ($doc) => $doc->name, $packages));
        $this->assertTrue($packages[0]->documented);
        $this->assertFalse($packages[1]->documented);
        $this->assertSame('vendor/webx-ui/module-pages/AGENTS.md', $packages[0]->link());
    }

    #[Test]
    public function it_writes_the_guide_and_the_one_line_claude_md(): void
    {
        $this->artisan('webx:panel')->assertSuccessful();

        $guide = (string) $this->files->get($this->guide());

        $this->assertStringStartsWith(RootFile::START, $guide);
        $this->assertStringContainsString('**Never fork a module and never edit `vendor/`.**', $guide);
        $this->assertStringContainsString(
            '- [webx-ui/module-pages](vendor/webx-ui/module-pages/AGENTS.md) — Pages for the WebX UI admin panel.',
            $guide,
        );
        $this->assertStringContainsString('Without a guide yet, read their README.md: `webx-ui/module-seo`.', $guide);
        $this->assertStringNotContainsString('laravel/framework', $guide);
        $this->assertStringContainsString('`/cms`', $guide);
        $this->assertStringContainsString("## The site's look", $guide);
        $this->assertStringContainsString('how to see a change: '.RootFile::STYLES_GUIDE, $guide);
        $this->assertLessThan(
            strpos($guide, '## Installed packages'),
            strpos($guide, RootFile::STYLES_GUIDE),
            'The styles guide is named before the package list, where it is read.',
        );
        $this->assertStringEndsWith(RootFile::PROJECT."\n", $guide);

        $this->assertSame("@AGENTS.md\n", $this->files->get($this->app->basePath('CLAUDE.md')));
    }

    #[Test]
    public function a_second_run_changes_nothing(): void
    {
        $this->artisan('webx:panel')->assertSuccessful();
        $first = $this->files->get($this->guide());

        $this->artisan('webx:panel', ['--sync' => true])
            ->expectsOutputToContain('already lists what is installed')
            ->assertSuccessful();

        $this->assertSame($first, $this->files->get($this->guide()));
    }

    #[Test]
    public function it_rewrites_the_block_and_never_the_project_text(): void
    {
        $this->files->put($this->guide(), implode("\n", [
            '# Notes above',
            '',
            RootFile::START,
            'whatever an older release wrote here',
            RootFile::END,
            '',
            '## This project',
            '',
            'Prices are always shown with VAT.',
            '',
        ]));

        $this->artisan('webx:panel')->assertSuccessful();

        $guide = (string) $this->files->get($this->guide());

        $this->assertStringStartsWith("# Notes above\n\n".RootFile::START, $guide);
        $this->assertStringNotContainsString('whatever an older release wrote here', $guide);
        $this->assertStringContainsString('vendor/webx-ui/module-pages/AGENTS.md', $guide);
        $this->assertStringEndsWith(RootFile::END."\n\n## This project\n\nPrices are always shown with VAT.\n", $guide);
        $this->assertSame(1, substr_count($guide, RootFile::START));
    }

    #[Test]
    public function a_hand_written_file_keeps_every_word_under_the_block(): void
    {
        $this->files->put($this->guide(), "# Our site\n\nUse the staging database.\n");

        $this->artisan('webx:panel')
            ->expectsOutputToContain('guide added above your text')
            ->assertSuccessful();

        $guide = (string) $this->files->get($this->guide());

        $this->assertStringStartsWith(RootFile::START, $guide);
        $this->assertStringEndsWith(RootFile::END."\n\n# Our site\n\nUse the staging database.\n", $guide);

        $this->artisan('webx:panel', ['--sync' => true])->assertSuccessful();
        $this->assertSame($guide, $this->files->get($this->guide()));
    }

    #[Test]
    public function a_claude_md_of_the_site_is_left_alone(): void
    {
        $this->files->put($this->app->basePath('CLAUDE.md'), "Our own rules.\n");

        $this->artisan('webx:panel')
            ->expectsOutputToContain('add a line `@AGENTS.md`')
            ->assertSuccessful();

        $this->assertSame("Our own rules.\n", $this->files->get($this->app->basePath('CLAUDE.md')));
    }

    #[Test]
    public function with_no_guide_installed_it_says_where_to_read_instead(): void
    {
        $block = RootFile::block([], 'cms');

        $this->assertStringContainsString('None of the installed packages carries an AGENTS.md yet', $block);
        $this->assertStringEndsWith(RootFile::END, $block);
    }
}
