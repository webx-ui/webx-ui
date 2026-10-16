<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Notifications\Relations\ChannelTarget;

/**
 * What a consumer chooses its channels from (§15.2): one target per event, `notify-<event>`, with
 * the channels of that event and the ones for any event — and nothing a picker should not show.
 * And the administrators an e-mail channel can write to.
 */
final class ChannelTargetTest extends TestCase
{
    #[Test]
    public function an_event_brings_its_target_named_for_the_address(): void
    {
        $this->assertSame('notify-inbox-submission', ChannelTarget::keyFor('inbox.submission'));
        $this->assertSame('notify-shop-order-paid', ChannelTarget::keyFor('shop.order_paid'));
        $this->assertTrue($this->app->make(RelationTargets::class)->has('notify-shop-order'));
    }

    #[Test]
    public function the_picker_offers_the_events_channels_and_the_universal_ones_without_the_bin_or_a_secret(): void
    {
        $mine = $this->telegram(['name' => 'Orders chat']);
        $universal = $this->email(['name' => 'Any event letter', 'event' => null, 'enabled' => false]);
        $other = $this->telegram(['name' => 'Other event', 'event' => 'blog.comment']);
        $binned = $this->telegram(['name' => 'Binned']);
        $binned->delete();

        // Choosing a channel is the consumer's right: an editor without the module's permissions.
        $editor = $this->admin(false, ['inbox.manage']);
        $found = $this->actingAs($editor, 'cms')->getJson('/api/cms/relations/notify-shop-order')->assertOk();

        $titles = array_column($this->rows($found), 'title');
        sort($titles);
        $this->assertSame(['Any event letter', 'Orders chat'], $titles);

        $row = $this->row($found, $mine->id);
        $this->assertSame('telegram', $row['provider']);
        $this->assertTrue($row['enabled']);
        $this->assertTrue($row['visible']);
        $this->assertStringContainsString('Telegram', (string) $row['subtitle']);

        $off = $this->row($found, $universal->id);
        $this->assertFalse($off['visible']);
        $this->assertStringContainsString('Any event', (string) $off['subtitle']);

        $this->assertStringNotContainsString(self::TOKEN, $found->getContent());
        $this->assertStringNotContainsString('100200300', $found->getContent());
        $this->assertNull($this->row($found, $other->id));

        // A form names what it holds: the one in the bin is named too, and marked.
        $named = $this->actingAs($editor, 'cms')->getJson('/api/cms/relations/notify-shop-order?ids[]='.$binned->id.'&ids[]='.$mine->id)->assertOk();
        $this->assertSame([$binned->id, $mine->id], array_column($this->rows($named), 'id'));
        $this->assertTrue($named->json('data.0.trashed'));

        $searched = $this->actingAs($editor, 'cms')->getJson('/api/cms/relations/notify-shop-order?q=letter')->assertOk();
        $this->assertSame([$universal->id], array_column($this->rows($searched), 'id'));
    }

    #[Test]
    public function an_email_channel_offers_the_administrators_who_are_switched_on(): void
    {
        $manager = $this->admin(false, ['notifications.manage']);
        CmsUser::query()->create(['name' => 'Gone', 'email' => 'gone@site.test', 'password' => 'correct-horse-battery', 'is_super' => false, 'is_active' => false]);

        $this->actingAs($this->admin(false, ['notifications.view']), 'cms')->getJson(self::API.'/administrators')->assertForbidden();

        $answer = $this->actingAs($manager, 'cms')->getJson(self::API.'/administrators')->assertOk();
        $emails = array_column($this->rows($answer), 'email');

        $this->assertContains($manager->email, $emails);
        $this->assertNotContains('gone@site.test', $emails);
        $this->assertSame(['id', 'name', 'email'], array_keys($answer->json('data.0')));
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<array<string, mixed>>
     */
    private function rows(TestResponse $response): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json('data');

        return $rows;
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<string, mixed>|null
     */
    private function row(TestResponse $response, int $id): ?array
    {
        foreach ($this->rows($response) as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }
}
