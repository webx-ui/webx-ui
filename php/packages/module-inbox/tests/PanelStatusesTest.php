<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Models\Status;

/**
 * The states a submission can be in (§2.11, §12).
 */
final class PanelStatusesTest extends TestCase
{
    #[Test]
    public function it_lists_the_five_a_panel_starts_with(): void
    {
        $response = $this->actingAs($this->editor(['inbox.view']), 'cms')
            ->getJson($this->api('statuses'))
            ->assertOk();

        $keys = array_column($response->json('data'), 'key');

        $this->assertSame(['new', 'in-progress', 'done', 'rejected', 'spam'], $keys);
        $this->assertTrue($response->json('data.0.is_default'));
        $this->assertSame('New', $response->json('data.0.title.en'));
    }

    #[Test]
    public function moving_the_default_takes_it_off_the_one_that_had_it(): void
    {
        $done = Status::query()->where('key', 'done')->sole();

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api("statuses/{$done->getKey()}"), [
                'key' => 'done',
                'title' => ['en' => 'Done'],
                'color' => 'success',
                'is_default' => true,
                'is_closed' => true,
            ])
            ->assertOk();

        $this->assertTrue($done->refresh()->is_default);
        $this->assertFalse(Status::query()->where('key', 'new')->sole()->is_default);
    }

    #[Test]
    public function a_status_with_submissions_in_it_cannot_be_deleted(): void
    {
        $form = $this->form('contact');
        $new = Status::query()->where('key', 'new')->sole();
        $this->submission($form, ['status_id' => $new->getKey()]);

        $this->actingAs($this->editor(), 'cms')
            ->deleteJson($this->api("statuses/{$new->getKey()}"))
            ->assertStatus(422);

        $this->assertModelExists($new);
    }

    #[Test]
    public function an_empty_status_can_be_deleted(): void
    {
        $rejected = Status::query()->where('key', 'rejected')->sole();

        $this->actingAs($this->editor(), 'cms')
            ->deleteJson($this->api("statuses/{$rejected->getKey()}"))
            ->assertNoContent();

        $this->assertModelMissing($rejected);
    }

    #[Test]
    public function spam_is_closed_whatever_the_form_said(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('statuses'), [
                'key' => 'junk',
                'title' => ['en' => 'Junk'],
                'color' => 'danger',
                'is_spam' => true,
                'is_closed' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_closed', true);
    }

    #[Test]
    public function a_colour_outside_the_badge_tones_is_refused(): void
    {
        // A hex value picked in the light theme is unreadable in the dark one.
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('statuses'), ['key' => 'pink', 'title' => 'Pink', 'color' => '#ff69b4'])
            ->assertJsonValidationErrors('color');
    }

    #[Test]
    public function it_saves_their_order(): void
    {
        $new = Status::query()->where('key', 'new')->sole();
        $done = Status::query()->where('key', 'done')->sole();

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('statuses/sorting'), ['ids' => [$done->getKey(), $new->getKey()]])
            ->assertNoContent();

        $this->assertSame(0, $done->refresh()->position);
        $this->assertSame(1, $new->refresh()->position);
    }

    #[Test]
    public function a_reader_sees_them_and_only_a_manager_changes_them(): void
    {
        // The list draws a badge per submission and tabs over them: a reader needs the words.
        $this->actingAs($this->editor(['inbox.view']), 'cms')
            ->getJson($this->api('statuses'))
            ->assertOk();

        $this->actingAs($this->editor(['inbox.view', 'inbox.update']), 'cms')
            ->postJson($this->api('statuses'), ['key' => 'mine', 'title' => 'Mine'])
            ->assertForbidden();
    }
}
