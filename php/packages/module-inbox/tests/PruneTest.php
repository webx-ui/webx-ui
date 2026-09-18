<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;

/**
 * `webx:inbox:prune` — forgetting what is old enough to be forgotten (§15).
 */
final class PruneTest extends TestCase
{
    #[Test]
    public function nothing_goes_while_both_ages_are_zero(): void
    {
        config()->set('webx-inbox.prune', ['spam_days' => 0, 'days' => 0]);

        $form = $this->form();
        $this->aged($form, 400);
        $this->aged($form, 400, Status::spam()?->getKey());

        $this->artisan('webx:inbox:prune')->assertSuccessful();

        $this->assertSame(2, Submission::query()->count());
    }

    #[Test]
    public function spam_goes_after_its_own_age_and_everything_else_stays(): void
    {
        config()->set('webx-inbox.prune', ['spam_days' => 30, 'days' => 0]);

        $form = $this->form();
        $old = $this->aged($form, 60, Status::spam()?->getKey());
        $recent = $this->aged($form, 10, Status::spam()?->getKey());
        $enquiry = $this->aged($form, 400);

        $this->artisan('webx:inbox:prune')
            ->expectsOutputToContain('Deleted 1 spam and 0 submissions.')
            ->assertSuccessful();

        $this->assertNull(Submission::query()->find($old->getKey()));
        $this->assertNotNull(Submission::query()->find($recent->getKey()));
        // The second age is the outer one: a site that keeps enquiries for ever keeps this one.
        $this->assertNotNull(Submission::query()->find($enquiry->getKey()));
    }

    #[Test]
    public function the_older_age_takes_everything_and_the_files_with_it(): void
    {
        config()->set('webx-inbox.prune', ['spam_days' => 30, 'days' => 365]);

        $form = $this->form();
        $old = $this->aged($form, 400);
        $recent = $this->aged($form, 100);

        $disk = Storage::disk('inbox');
        $disk->put('inbox/contact/cv.pdf', 'bytes');

        $old->files()->create([
            'field_id' => $form->fields->first()?->getKey(),
            'disk' => 'inbox',
            'path' => 'inbox/contact/cv.pdf',
            'name' => 'cv.pdf',
            'size' => 5,
            'mime' => 'application/pdf',
        ]);

        $this->artisan('webx:inbox:prune')->assertSuccessful();

        $this->assertNull(Submission::query()->find($old->getKey()));
        $this->assertNotNull(Submission::query()->find($recent->getKey()));
        // Deleted through the model, one at a time, so the bytes go with the row rather than
        // staying on the disk with nothing pointing at them (§8).
        $this->assertFalse($disk->exists('inbox/contact/cv.pdf'));
    }

    #[Test]
    public function a_dry_run_counts_and_deletes_nothing(): void
    {
        config()->set('webx-inbox.prune', ['spam_days' => 30, 'days' => 0]);

        $form = $this->form();
        $this->aged($form, 60, Status::spam()?->getKey());

        $this->artisan('webx:inbox:prune --dry-run')
            ->expectsOutputToContain('Would delete 1 spam and 0 submissions.')
            ->assertSuccessful();

        $this->assertSame(1, Submission::query()->count());
    }

    #[Test]
    public function the_command_line_beats_the_config(): void
    {
        config()->set('webx-inbox.prune', ['spam_days' => 0, 'days' => 0]);

        $form = $this->form();
        $this->aged($form, 60);

        $this->artisan('webx:inbox:prune --days=30')->assertSuccessful();

        $this->assertSame(0, Submission::query()->count());
    }

    private function aged(Form $form, int $days, mixed $statusId = null): Submission
    {
        return $this->submission($form, array_filter([
            'created_at' => now()->subDays($days),
            'status_id' => $statusId,
        ]));
    }
}
