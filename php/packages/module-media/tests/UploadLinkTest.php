<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Server\Testing\TestResponse as McpResponse;
use Laravel\Passport\Contracts\ScopeAuthorizable;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Gate\Openings;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Models\MediaUploadLink;
use WebxUi\Media\UploadLinks\UploadLinks;

/**
 * `media_upload_link` and the endpoint curl sends the file to.
 *
 * What would be quiet if it broke: a link that works twice or after its time, a link that is
 * not ours, a file that skips the panel's rules because it came by another door, an upload put
 * in by nobody, and a refusal a terminal cannot read.
 */
final class UploadLinkTest extends TestCase
{
    private CmsUser $admin;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), McpServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = $this->actingAsAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function the_tool_is_listed_behind_the_write_scope_and_the_upload_permission(): void
    {
        $tool = $this->app->make(ToolRegistry::class)->tool('media_upload_link');

        $this->assertSame(['media.upload', 'media.manage'], $tool->permissions());

        // A token that may only read gets no link, and no row is made.
        $reader = $this->actingAsAdmin();
        $reader->withAccessToken($this->scoped(['media:read']));

        $this->agent(['directory_id' => $this->root()->id], $reader)->assertHasErrors(['[media:write]']);
        $this->assertSame(0, MediaUploadLink::query()->count());

        // Nor does an administrator who may not upload.
        $viewer = $this->actingAsAdmin(super: false);
        $this->grant($viewer, ['media.view']);

        $this->agent(['directory_id' => $this->root()->id], $viewer)->assertHasErrors(['[media.upload]']);
        $this->assertSame(0, MediaUploadLink::query()->count());
    }

    #[Test]
    public function the_link_says_how_to_use_it(): void
    {
        $answer = $this->link(['directory_id' => $this->root()->id]);

        $this->assertStringContainsString('/media/upload-links/', $answer['url']);
        $this->assertSame('curl -F file=@<path> "'.$answer['url'].'"', $answer['curl']);
        $this->assertSame('curl -T <path> "'.$answer['url'].'"', $answer['curl_raw']);
        $this->assertContains('jpg', $answer['extensions']);
        $this->assertGreaterThan(0, $answer['max_bytes']);
        $this->assertLessThanOrEqual($answer['max_bytes'], $answer['max_bytes_form']);
        $this->assertEqualsWithDelta(Carbon::now()->addMinutes(10)->getTimestamp(), Carbon::parse($answer['expires_at'])->getTimestamp(), 2);

        $link = MediaUploadLink::query()->sole();
        $this->assertSame($this->admin->id, $link->admin_id);
        $this->assertSame($this->root()->id, $link->directory_id);

        // A dry run checks the folder and makes nothing.
        $this->agent(['directory_id' => 4242, 'dry_run' => true])->assertHasErrors(['There is no folder with id 4242']);
        $this->agent(['directory_id' => $this->root()->id, 'dry_run' => true])->assertOk();
        $this->assertSame(1, MediaUploadLink::query()->count());
    }

    #[Test]
    public function a_photo_sent_as_a_form_lands_in_the_folder_as_the_administrator_who_asked(): void
    {
        $folder = MediaDirectory::query()->create(['title' => 'Photos', 'parent_id' => $this->root()->id]);
        $answer = $this->link(['directory_id' => $folder->id, 'name' => 'Sofa Oslo.jpg', 'alt' => 'A grey sofa']);

        // Nobody signed in: curl carries no session.
        $this->signOut();

        $response = $this->post($answer['url'], ['file' => UploadedFile::fake()->image('IMG_0001.jpg', 3000, 2000)]);

        $response->assertCreated();
        $file = MediaFile::query()->sole();

        // What media_get_file answers, plus duplicate.
        $response->assertJson(fn (AssertableJson $json) => $json
            ->where('ok', true)
            ->where('id', $file->id)
            ->where('directory_id', $folder->id)
            ->where('path', $file->path)
            ->where('type', 'image')
            ->where('duplicate', false)
            ->where('alt', ['en' => 'A grey sofa'])
            ->etc());

        // The panel's pipeline: scaled down to the configured side, re-encoded, named as asked.
        $this->assertSame('image/webp', $file->mime);
        $this->assertSame(2560, $file->width);
        $this->assertSame('Sofa Oslo', $file->name);
        Storage::disk('public')->assertExists($file->path);

        $link = MediaUploadLink::query()->sole();
        $this->assertNotNull($link->used_at);
        $this->assertSame($file->id, $link->file_id);
        $this->assertSame($this->admin->id, $link->admin_id);
    }

    #[Test]
    public function the_raw_body_is_taken_too(): void
    {
        $answer = $this->link(['directory_id' => $this->root()->id]);
        $this->signOut();

        $response = $this->raw('PUT', $answer['url'].'?name=dot.png', $this->png());

        $response->assertCreated()->assertJsonPath('type', 'image');
        $this->assertSame('dot', MediaFile::query()->sole()->name);
    }

    #[Test]
    public function a_link_works_once(): void
    {
        $answer = $this->link(['directory_id' => $this->root()->id]);
        $this->signOut();

        $this->raw('POST', $answer['url'], $this->png())->assertCreated();

        $this->raw('POST', $answer['url'], $this->png())
            ->assertStatus(410)
            ->assertJsonPath('error', 'link_used');

        $this->assertSame(1, MediaFile::query()->count());
    }

    #[Test]
    public function of_two_requests_at_once_one_takes_the_link(): void
    {
        $this->link(['directory_id' => $this->root()->id]);
        $links = $this->app->make(UploadLinks::class);
        $row = MediaUploadLink::query()->sole();

        // Two requests that both read the row before either wrote: the database decides.
        $first = MediaUploadLink::query()->findOrFail($row->id);
        $second = MediaUploadLink::query()->findOrFail($row->id);

        $this->assertTrue($links->claim($first));
        $this->assertFalse($links->claim($second));
    }

    #[Test]
    public function an_expired_link_is_refused(): void
    {
        $answer = $this->link(['directory_id' => $this->root()->id]);
        $this->signOut();

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        $this->raw('POST', $answer['url'], $this->png())
            ->assertStatus(410)
            ->assertJsonPath('error', 'link_expired');

        $this->assertSame(0, MediaFile::query()->count());
    }

    #[Test]
    public function the_lifetime_is_configured(): void
    {
        config()->set('webx-media.upload_links.ttl', 2);

        $answer = $this->link(['directory_id' => $this->root()->id]);

        $this->assertEqualsWithDelta(Carbon::now()->addMinutes(2)->getTimestamp(), Carbon::parse($answer['expires_at'])->getTimestamp(), 2);
    }

    #[Test]
    public function a_link_that_is_not_ours_opens_nothing(): void
    {
        $answer = $this->link(['directory_id' => $this->root()->id]);
        $this->signOut();

        [$base, $token] = explode('/media/upload-links/', $answer['url']);
        [$payload, $signature] = explode('.', $token);

        // Signed with another key, a signature changed by one character, a token of nothing.
        $forged = [
            $payload.'.'.hash_hmac('sha256', 'webx-media-upload-link|'.$payload, 'another-key'),
            $payload.'.'.substr($signature, 0, -1).($signature[-1] === 'a' ? 'b' : 'a'),
            'nothing',
        ];

        foreach ($forged as $wrong) {
            $this->raw('POST', $base.'/media/upload-links/'.$wrong, $this->png())
                ->assertForbidden()
                ->assertJsonPath('error', 'link_invalid');
        }

        $this->assertSame(0, MediaFile::query()->count());
        $this->assertNull(MediaUploadLink::query()->sole()->used_at);
    }

    #[Test]
    public function an_administrator_switched_off_since_has_no_links(): void
    {
        $answer = $this->link(['directory_id' => $this->root()->id]);
        $this->signOut();

        $this->admin->forceFill(['is_active' => false])->save();

        $this->raw('POST', $answer['url'], $this->png())->assertForbidden()->assertJsonPath('error', 'link_invalid');
        $this->assertSame(0, MediaFile::query()->count());
    }

    #[Test]
    public function a_type_the_library_does_not_take_is_refused_and_the_link_stays_usable(): void
    {
        $answer = $this->link(['directory_id' => $this->root()->id]);
        $this->signOut();

        $this->raw('POST', $answer['url'].'?name=script.php', '<?php echo "not a picture";')
            ->assertStatus(422)
            ->assertJsonPath('error', 'type_not_allowed')
            ->assertJson(fn (AssertableJson $json) => $json->where('message', fn (string $message): bool => str_contains($message, 'Only these can be uploaded'))->etc());

        // The refusal did not use the link up: the right file goes through it.
        $this->raw('POST', $answer['url'], $this->png())->assertCreated();
        $this->assertSame(1, MediaFile::query()->count());
    }

    #[Test]
    public function a_file_over_the_size_limit_is_refused(): void
    {
        config()->set('webx-media.upload.max_size', 1);

        $answer = $this->link(['directory_id' => $this->root()->id]);
        $this->signOut();

        $this->raw('POST', $answer['url'].'?name=big.zip', random_bytes(4096))
            ->assertStatus(413)
            ->assertJsonPath('error', 'too_large');

        $this->post($answer['url'], ['file' => UploadedFile::fake()->image('big.jpg', 1200, 800)])
            ->assertStatus(413)
            ->assertJsonPath('error', 'too_large');

        $this->assertSame(0, MediaFile::query()->count());
    }

    #[Test]
    public function a_request_without_a_file_says_how_to_send_one(): void
    {
        $answer = $this->link(['directory_id' => $this->root()->id]);
        $this->signOut();

        $this->post($answer['url'], ['photo' => 'x'])->assertStatus(422)->assertJsonPath('error', 'no_file');
        $this->raw('POST', $answer['url'], '')->assertStatus(422)->assertJsonPath('error', 'no_file');

        $this->assertNull(MediaUploadLink::query()->sole()->used_at);
    }

    #[Test]
    public function an_svg_keeps_no_script(): void
    {
        $answer = $this->link(['directory_id' => $this->root()->id]);
        $this->signOut();

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)"><script>alert(2)</script><rect width="10" height="10"/></svg>';

        $path = $this->raw('POST', $answer['url'].'?name=logo.svg', $svg)->assertCreated()->json('path');
        $kept = (string) Storage::disk('public')->get((string) $path);

        $this->assertStringContainsString('<rect', $kept);
        $this->assertStringNotContainsString('<script', $kept);
        $this->assertStringNotContainsString('onload', $kept);
    }

    #[Test]
    public function the_same_bytes_again_are_the_same_file(): void
    {
        $first = $this->link(['directory_id' => $this->root()->id]);
        $second = $this->link(['directory_id' => $this->root()->id]);
        $this->signOut();

        $id = $this->raw('POST', $first['url'].'?name=dot.png', $this->png())->assertCreated()->json('id');

        $this->raw('POST', $second['url'].'?name=dot.png', $this->png())
            ->assertOk()
            ->assertJsonPath('id', $id)
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, MediaFile::query()->count());
    }

    #[Test]
    public function the_address_is_open_on_a_closed_site_and_the_link_is_still_needed(): void
    {
        $this->assertTrue($this->app->make(Openings::class)->open(
            Request::create('/'.trim((string) config('webx-admin.api_path'), '/').'/media/upload-links/x', 'POST'),
        ));

        $this->signOut();
        $this->raw('POST', '/'.trim((string) config('webx-admin.api_path'), '/').'/media/upload-links/x', $this->png())
            ->assertForbidden()
            ->assertJsonPath('error', 'link_invalid');
    }

    #[Test]
    public function the_endpoint_is_rate_limited(): void
    {
        config()->set('webx-media.upload_links.throttle', '2,1');
        $this->signOut();
        $url = '/'.trim((string) config('webx-admin.api_path'), '/').'/media/upload-links/x';

        $this->raw('POST', $url, 'a')->assertForbidden();
        $this->raw('POST', $url, 'a')->assertForbidden();
        $this->raw('POST', $url, 'a')->assertStatus(429);
    }

    #[Test]
    public function unused_links_are_forgotten_and_used_ones_kept_as_the_record(): void
    {
        $this->link(['directory_id' => $this->root()->id]);
        $used = MediaUploadLink::query()->sole();
        $used->forceFill(['used_at' => Carbon::now()])->save();
        $this->link(['directory_id' => $this->root()->id]);

        Carbon::setTestNow(Carbon::now()->addDays(2));
        $this->link(['directory_id' => $this->root()->id]);

        // The unused one from two days ago is gone; the used one and today's stay.
        $this->assertSame(2, MediaUploadLink::query()->count());
        $this->assertTrue(MediaUploadLink::query()->whereKey($used->id)->exists());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(array $arguments, ?CmsUser $as = null): McpResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('media_upload_link'));

        return WebxServer::actingAs($as ?? $this->admin, 'cms')->tool($bound, $arguments);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function link(array $arguments): array
    {
        $decoded = null;

        $this->agent($arguments)->assertOk()->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return TestResponse<Response>
     */
    private function raw(string $method, string $url, string $body): TestResponse
    {
        return $this->call($method, $url, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => '*/*'], $body);
    }

    private function signOut(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /**
     * @param  list<string>  $scopes
     */
    private function scoped(array $scopes): ScopeAuthorizable
    {
        return new readonly class($scopes) implements ScopeAuthorizable
        {
            /** @param  list<string>  $scopes */
            public function __construct(private array $scopes) {}

            public function can(string $scope): bool
            {
                return in_array($scope, $this->scopes, true);
            }

            public function cant(string $scope): bool
            {
                return ! $this->can($scope);
            }
        };
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
