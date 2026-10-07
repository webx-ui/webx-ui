<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Media\MediaModule;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Remote\HostResolver;
use WebxUi\Media\Remote\RemoteFetcher;
use WebxUi\Media\Storage\FileStore;

/**
 * The guards of the media tools: refusals as errors, deleting what the site still uses, deleting
 * folders, and fetching only from the public internet.
 */
final class McpGuardsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, list<string>> host → addresses the fake resolver answers */
    private array $dns = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $dns = &$this->dns;
        $this->app->instance(HostResolver::class, new class($dns) extends HostResolver
        {
            /** @param  array<string, list<string>>  $dns */
            public function __construct(private array &$dns) {}

            public function resolve(string $host): array
            {
                return $this->dns[$host] ?? [];
            }
        });
    }

    #[Test]
    public function a_refusal_is_an_error_rather_than_an_answer(): void
    {
        $this->assertRefused('get_file', ['id' => 999], 'There is no file with id 999');
    }

    #[Test]
    public function moving_into_a_folder_that_does_not_exist_names_it(): void
    {
        $file = $this->file('Sofa.jpg');

        $this->assertRefused('move_files', ['ids' => [$file->id], 'directory_id' => 4242], 'There is no folder with id 4242');
        $this->assertRefused('move_files', ['ids' => [$file->id, 777], 'directory_id' => $this->root()->id], 'There is no file with id 777');
    }

    #[Test]
    public function a_file_in_use_is_not_deleted_without_force(): void
    {
        Schema::create('test_articles', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cover_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->json('content')->nullable();
        });

        $cover = $this->file('Cover.jpg');
        $inBlock = $this->file('In a block.jpg');
        $loose = $this->file('Loose.jpg');

        // The way a cast writes it: slashes escaped, so the full key never appears as it is.
        DB::table('test_articles')->insert([
            ['id' => 5, 'cover_id' => $cover->id, 'content' => null],
            ['id' => 6, 'cover_id' => null, 'content' => json_encode(['image' => ['path' => $inBlock->path, 'alt' => '']])],
        ]);

        $dry = $this->invoke('delete_files', ['ids' => [$cover->id, $inBlock->id, $loose->id], 'dry_run' => true]);

        $this->assertStringStartsWith('refuse', $dry['would']);
        $this->assertCount(2, $dry['in_use']);
        $this->assertSame([['table' => 'test_articles', 'column' => 'cover_id', 'id' => 5]], $dry['in_use'][0]['used_in']);
        $this->assertSame([['table' => 'test_articles', 'column' => 'content', 'id' => 6]], $dry['in_use'][1]['used_in']);

        $this->assertRefused('delete_files', ['ids' => [$cover->id, $loose->id]], 'test_articles #5 (cover_id)');
        $this->assertSame(3, MediaFile::query()->count());

        // A file nothing uses goes as before.
        $this->assertSame(1, $this->invoke('delete_files', ['ids' => [$loose->id]])['deleted']);

        $this->assertSame(2, $this->invoke('delete_files', ['ids' => [$cover->id, $inBlock->id], 'force' => true])['deleted']);
        $this->assertSame(0, MediaFile::query()->count());
    }

    #[Test]
    public function only_an_empty_folder_is_deleted(): void
    {
        $root = $this->root();
        $full = new MediaDirectory(['title' => 'Full']);
        $full->appendTo($root);
        $empty = new MediaDirectory(['title' => 'Empty']);
        $empty->appendTo($root);
        $this->app->make(FileStore::class)->store(UploadedFile::fake()->image('a.jpg', 120, 80), $full->refresh());

        $this->assertRefused('delete_directory', ['id' => $root->id], 'root');
        $this->assertRefused('delete_directory', ['id' => $full->id], 'not empty: 1 file(s)');
        $this->assertRefused('delete_directory', ['id' => 9999], 'There is no folder with id 9999');

        $this->assertStringContainsString('Empty', $this->invoke('delete_directory', ['id' => $empty->id, 'dry_run' => true])['would']);
        $this->assertNotNull(MediaDirectory::query()->find($empty->id));

        $this->assertTrue($this->invoke('delete_directory', ['id' => $empty->id])['ok']);
        $this->assertNull(MediaDirectory::query()->find($empty->id));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function privateAddresses(): iterable
    {
        yield 'loopback' => ['127.0.0.1'];
        yield 'loopback elsewhere in the block' => ['127.8.8.8'];
        yield 'private 10' => ['10.0.0.5'];
        yield 'private 172' => ['172.16.4.4'];
        yield 'private 192' => ['192.168.1.1'];
        yield 'metadata' => ['169.254.169.254'];
        yield 'shared' => ['100.64.0.1'];
        yield 'nothing' => ['0.0.0.0'];
        yield 'v6 loopback' => ['::1'];
        yield 'v6 unspecified' => ['::'];
        yield 'v6 unique local' => ['fd00::1'];
        yield 'v6 link-local' => ['fe80::1'];
        yield 'v4 mapped' => ['::ffff:127.0.0.1'];
        yield 'v4 mapped private' => ['::ffff:10.0.0.1'];
        yield 'nat64 of private' => ['64:ff9b::a00:1'];
        yield '6to4 of loopback' => ['2002:7f00:1::1'];
    }

    #[Test]
    #[DataProvider('privateAddresses')]
    public function a_private_address_is_not_public(string $address): void
    {
        $this->assertFalse(RemoteFetcher::isPublic($address));
    }

    #[Test]
    public function a_public_address_is_public(): void
    {
        $this->assertTrue(RemoteFetcher::isPublic('93.184.215.14'));
        $this->assertTrue(RemoteFetcher::isPublic('2606:2800:21f:cb07:6820:80da:af6b:8b2c'));
        $this->assertTrue(RemoteFetcher::isPublic('::ffff:93.184.215.14'));
    }

    #[Test]
    public function nothing_is_fetched_from_inside_the_network(): void
    {
        Http::fake();
        $this->dns['site.test'] = ['127.0.0.1'];
        $this->dns['sneaky.example'] = ['93.184.215.14', '10.0.0.7'];

        $this->assertRefused('upload_from_url', ['url' => 'http://127.0.0.1/secret.png', 'directory_id' => $this->root()->id], 'not on the public internet');
        $this->assertRefused('upload_from_url', ['url' => 'http://[::1]:8080/x.png', 'directory_id' => $this->root()->id], 'not on the public internet');
        $this->assertRefused('upload_from_url', ['url' => 'http://169.254.169.254/latest/meta-data/', 'directory_id' => $this->root()->id], 'not on the public internet');
        $this->assertRefused('upload_from_url', ['url' => 'https://site.test/storage/a.png', 'directory_id' => $this->root()->id], 'not on the public internet');
        // One private address among public ones is enough: the connection could take that one.
        $this->assertRefused('upload_from_url', ['url' => 'https://sneaky.example/a.png', 'directory_id' => $this->root()->id], 'not on the public internet');
        $this->assertRefused('upload_from_url', ['url' => 'https://nowhere.example/a.png', 'directory_id' => $this->root()->id], 'does not resolve');

        Http::assertNothingSent();
    }

    #[Test]
    public function a_trusted_host_may_be_on_the_network(): void
    {
        config()->set('webx-media.remote.allow_hosts', ['images.internal']);
        $this->dns['images.internal'] = ['10.0.0.9'];
        Http::fake(['*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);

        $answer = $this->invoke('upload_from_url', ['url' => 'http://images.internal/a.png', 'directory_id' => $this->root()->id]);

        $this->assertTrue($answer['ok']);
    }

    #[Test]
    public function a_redirect_into_the_network_is_not_followed(): void
    {
        $this->dns['cdn.example'] = ['93.184.215.14'];
        Http::fake(['cdn.example/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);

        $this->assertRefused('upload_from_url', ['url' => 'https://cdn.example/a.png', 'directory_id' => $this->root()->id], 'not on the public internet');

        Http::assertSentCount(1);
    }

    #[Test]
    public function a_transport_failure_is_said_without_its_details(): void
    {
        $this->dns['down.example'] = ['93.184.215.14'];
        Http::fake(static fn () => throw new ConnectionException('cURL error 7: Failed to connect to 10.1.2.3 port 6379'));

        try {
            $this->invoke('upload_from_url', ['url' => 'https://down.example/a.png', 'directory_id' => $this->root()->id]);
            $this->fail('No refusal.');
        } catch (ToolFailure $failure) {
            $this->assertSame('The address could not be reached.', $failure->getMessage());
        }
    }

    #[Test]
    public function the_type_is_what_the_bytes_are_and_the_name_ends_in_it(): void
    {
        $this->dns['cdn.example'] = ['93.184.215.14'];
        config()->set('webx-media.optimize.enabled', false);
        Http::fake(['*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png; qs=0.7'])]);

        $answer = $this->invoke('upload_from_url', ['url' => 'https://cdn.example/files/shell.php', 'directory_id' => $this->root()->id]);
        $file = MediaFile::query()->findOrFail($answer['id']);

        $this->assertSame('image/png', $file->mime);
        $this->assertSame('png', $file->extension);
        $this->assertStringEndsWith('.png', $file->path);
    }

    #[Test]
    public function a_type_the_library_does_not_take_is_refused(): void
    {
        $this->dns['cdn.example'] = ['93.184.215.14'];
        Http::fake(['*' => Http::response('<?php echo 1;', 200, ['Content-Type' => 'image/png'])]);

        $this->assertRefused('upload_from_url', ['url' => 'https://cdn.example/a.png', 'directory_id' => $this->root()->id], 'Only these can be uploaded');
        $this->assertSame(0, MediaFile::query()->count());
    }

    #[Test]
    public function a_stored_type_never_keeps_parameters(): void
    {
        $file = $this->app->make(FileStore::class)->store(
            UploadedFile::fake()->createWithContent('notes.txt', 'hello')->mimeType('text/plain; charset=UTF-8'),
            $this->root(),
        );

        $this->assertSame('text/plain', $file->mime);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function assertRefused(string $tool, array $arguments, string $says): void
    {
        try {
            $this->invoke($tool, $arguments);
        } catch (ToolFailure $failure) {
            $this->assertStringContainsString($says, $failure->getMessage());

            return;
        }

        $this->fail("[{$tool}] was not refused.");
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function invoke(string $name, array $arguments): array
    {
        foreach ($this->app->make(MediaModule::class)->mcpTools() as $tool) {
            if ($tool->name === $name) {
                /** @var array<string, mixed> $result */
                $result = ($tool->handler)($arguments);

                return $result;
            }
        }

        $this->fail("No tool named [{$name}].");
    }

    private function file(string $name): MediaFile
    {
        static $nth = 0;
        $nth++;

        return $this->app->make(FileStore::class)->store(
            UploadedFile::fake()->image($name, 100 + $nth, 300),
            $this->root(),
        );
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function root(): MediaDirectory
    {
        return MediaDirectory::query()->whereNull('parent_id')->firstOrFail();
    }
}
