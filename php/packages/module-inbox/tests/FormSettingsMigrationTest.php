<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * The forms a site already has, after the update: the way they tell is written down as what they
 * were doing, and the address they sent the visitor to is the same address, now as a link.
 */
final class FormSettingsMigrationTest extends TestCase
{
    #[Test]
    public function existing_forms_keep_what_they_did(): void
    {
        $channels = $this->row('channels', ['recipients' => [['email' => 'office@site.test']], 'notify_channels' => [3], 'redirect' => '/thank-you#top']);
        $letters = $this->row('letters', ['recipients' => [['email' => 'office@site.test']], 'notify_channels' => [], 'redirect' => '  ']);
        $bare = $this->row('bare', null);
        $chosen = $this->row('chosen', ['notify_via' => 'email', 'notify_channels' => [3]]);

        $this->migrate('up');

        $this->assertSame([
            'recipients' => [['email' => 'office@site.test']],
            'notify_channels' => [3],
            'redirect' => ['target' => 'url', 'entity_type' => null, 'entity_id' => null, 'url' => '/thank-you', 'hash' => 'top', 'new_tab' => false, 'rel' => []],
            'notify_via' => 'channels',
        ], $this->stored($channels));

        $this->assertSame('email', $this->stored($letters)['notify_via']);
        $this->assertArrayNotHasKey('redirect', $this->stored($letters));
        $this->assertSame(['notify_via' => 'email'], $this->stored($bare));
        // A choice already made is not second-guessed.
        $this->assertSame('email', $this->stored($chosen)['notify_via']);

        $this->migrate('down');

        $this->assertSame('/thank-you#top', $this->stored($channels)['redirect']);
        $this->assertArrayNotHasKey('notify_via', $this->stored($channels));
    }

    /**
     * @param  array<string, mixed>|null  $options
     */
    private function row(string $slug, ?array $options): int
    {
        return (int) DB::table('inbox_forms')->insertGetId([
            'slug' => $slug,
            'title' => json_encode(['en' => $slug]),
            'is_enabled' => true,
            'options' => $options === null ? null : json_encode($options),
            'position' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(int $id): array
    {
        $json = DB::table('inbox_forms')->where('id', $id)->value('options');

        return is_string($json) ? (array) json_decode($json, true) : [];
    }

    private function migrate(string $direction): void
    {
        $migration = require __DIR__.'/../database/migrations/2026_10_20_000000_settle_notify_via_and_redirect_of_inbox_forms.php';

        if (! $migration instanceof Migration || ! method_exists($migration, $direction)) {
            self::fail("The migration has no {$direction}().");
        }

        $migration->{$direction}();
    }
}
