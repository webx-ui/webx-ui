<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;

/**
 * Forget submissions that are old enough to be forgotten (§15).
 *
 * Submissions hold somebody else's name, telephone number and address, so a panel that keeps
 * them for ever keeps them for ever by accident rather than by decision. Two ages, because
 * they are two different decisions: spam is rubbish and goes after a month, and a real
 * enquiry is a record of a conversation and goes only when a site has said how long it keeps
 * one. Zero means never, and `days` is zero by default: deleting a client's enquiries is not
 * a thing to start doing by surprise.
 *
 * Row by row rather than one `delete` over the table, and through the model rather than the
 * query builder: the files on the disk go with the submission on the model's own event, and a
 * bulk delete would cascade the rows away in the database and leave the bytes behind with
 * nothing pointing at them (§8).
 */
class PruneSubmissionsCommand extends Command
{
    protected $signature = 'webx:inbox:prune
        {--spam-days= : How old a submission in a spam status has to be; the config when omitted}
        {--days= : How old any submission has to be; the config when omitted, where 0 means never}
        {--dry-run : Count what would go, and delete nothing}';

    protected $description = 'Delete old spam and old submissions, with the files that came with them';

    public function handle(): int
    {
        $spamDays = $this->days('spam-days', 'webx-inbox.prune.spam_days');
        $days = $this->days('days', 'webx-inbox.prune.days');

        if ($spamDays === 0 && $days === 0) {
            $this->components->info('Nothing is pruned: both ages are zero, which means keep everything.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');

        $spam = $spamDays === 0 ? 0 : $this->prune($this->olderThan($spamDays)->whereIn('status_id', $this->spamIds()), $dry);
        // Everything that is old enough, spam included — the second age is the outer one, so a
        // site that keeps submissions for a year keeps its spam for a month and not for both.
        $all = $days === 0 ? 0 : $this->prune($this->olderThan($days), $dry);

        $this->components->info(sprintf(
            '%s %d spam and %d submission%s.',
            $dry ? 'Would delete' : 'Deleted',
            $spam,
            $all,
            $all === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }

    /**
     * @param  Builder<Submission>  $query
     */
    private function prune(Builder $query, bool $dry): int
    {
        if ($dry) {
            return (int) $query->count();
        }

        $deleted = 0;

        // `chunkById` and not `chunk`: the rows are being removed as it goes, and a chunk by
        // offset would step over as many as it deleted.
        $query->chunkById(200, function (Collection $submissions) use (&$deleted): void {
            foreach ($submissions as $submission) {
                $submission->delete();
                $deleted++;
            }
            // Qualified for the `where`, plain for the row that comes back.
        }, 'inbox_submissions.id', 'id');

        return $deleted;
    }

    /**
     * @return Builder<Submission>
     */
    private function olderThan(int $days): Builder
    {
        return Submission::query()->where('inbox_submissions.created_at', '<', Carbon::now()->subDays($days));
    }

    /**
     * @return list<int>
     */
    private function spamIds(): array
    {
        /** @var list<int> $ids */
        $ids = Status::query()->where('is_spam', true)->pluck('id')->all();

        return $ids;
    }

    /** An age from the command line, or the site's own. Never negative: that would be the future. */
    private function days(string $option, string $key): int
    {
        $given = $this->option($option);

        if (is_scalar($given) && (string) $given !== '') {
            return max(0, (int) $given);
        }

        $configured = $this->laravel->make('config')->get($key, 0);

        return is_numeric($configured) ? max(0, (int) $configured) : 0;
    }
}
