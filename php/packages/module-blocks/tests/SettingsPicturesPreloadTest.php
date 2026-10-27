<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Settings\Models\Setting;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * The logo of the settings is printed by the header after the content's blocks looked their
 * pictures up: the first look-up of a response takes the settings' pictures along
 * (`MediaFiles::prefetch()`), so the page asks the library once, not once more for the logo.
 */
final class SettingsPicturesPreloadTest extends TestCase
{
    use CountsQueries;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), MediaServiceProvider::class, SettingsServiceProvider::class];
    }

    #[Test]
    public function the_logo_comes_with_the_pictures_of_the_blocks_in_one_query(): void
    {
        $this->publish('figure', '<img src="{{ $image["url"] ?? "" }}">', [], [
            'schema' => [['id' => 'image', 'type' => 'wx-media']],
        ]);
        $picture = $this->picture('hero');
        $logo = $this->picture('logo');
        Setting::query()->create(['key' => 'branding.logo', 'value' => ['path' => $logo->path]]);
        $this->app->make(MediaFiles::class)->flush();

        $tally = $this->recordQueries(function (): string {
            $html = $this->render([$this->node('figure', ['image' => ['path' => 'media/ab/cd/hero.jpg']])]);
            $url = settings('branding.logo');

            return $html.(is_array($url) ? (string) ($url['url'] ?? '') : '');
        });
        $library = array_filter($tally->queries, static fn (array $query): bool => str_contains($query['query'], 'media_files'));

        $this->assertCount(1, $library, $tally->describe());
        $this->assertStringContainsString($picture->path, (string) $tally->result);
        $this->assertStringContainsString($logo->path, (string) $tally->result);
    }

    #[Test]
    public function a_failing_guess_costs_the_prefetch_and_not_the_picture(): void
    {
        $picture = $this->picture('hero');
        $files = $this->app->make(MediaFiles::class);
        $files->prefetch(static function (): void {
            throw new \RuntimeException('The settings are not there yet.');
        });
        $files->flush();

        $this->assertSame($picture->getKey(), $files->find($picture->path)?->getKey());
    }

    private function picture(string $name): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => "media/ab/cd/{$name}.jpg",
            'hash' => substr(md5($name), 0, 32),
            'name' => $name,
            'file_name' => "{$name}.jpg",
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
            'width' => 800,
            'height' => 600,
        ]);
    }
}
