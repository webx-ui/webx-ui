<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Snapshots\MediaDisk;
use WebxUi\Seo\Normalisation;
use WebxUi\Settings\Settings;

/**
 * The address normalisation describes the server, not the content: a restore keeps the target's.
 */
final class SnapshotSettingsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/webx-seo-snapshot-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        MediaDisk::deleteDirectory($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function a_restore_keeps_the_stands_normalisation_and_takes_the_rest(): void
    {
        $settings = $this->app->make(Settings::class);

        // The laptop: https and the bare mirror on, and a title template that is content.
        $settings->save([
            Normalisation::HTTPS => true,
            Normalisation::HOST => 'bare',
            Normalisation::LOWERCASE => true,
            'seo.title-template' => '{title} — Laptop',
        ]);
        $archive = $this->dir.'/snapshot.tar.gz';
        $this->artisan('webx:snapshot', ['--output' => $archive, '--no-media' => true])->assertSuccessful();

        // The stand: https is the proxy's job here, the mirror never chosen, case left as it was.
        $settings->save([Normalisation::HTTPS => false]);
        $settings->clear([Normalisation::HOST, Normalisation::LOWERCASE]);
        // The stand's row under the id the archive gives the title: the title still has to go in.
        $titleId = DB::table('cms_settings')->where('key', 'seo.title-template')->value('id');
        DB::table('cms_settings')->where('key', 'seo.title-template')->delete();
        DB::table('cms_settings')->where('key', Normalisation::HTTPS)->update(['id' => $titleId]);
        $settings->forget();

        $this->artisan('webx:snapshot:restore', ['archive' => $archive, '--no-backup' => true, '--no-media' => true, '--force' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $settings->forget();
        $raw = $settings->raw();

        $this->assertFalse((bool) $raw[Normalisation::HTTPS]);
        $this->assertArrayNotHasKey(Normalisation::HOST, $raw);
        $this->assertArrayNotHasKey(Normalisation::LOWERCASE, $raw);
        $this->assertSame('{title} — Laptop', $raw['seo.title-template']);
    }
}
