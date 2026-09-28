<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Panel;

use Illuminate\Database\ConnectionInterface;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Support\Day;

/**
 * "Close the hiring" and "open it again" from the menu of a row (§4.11): whether a vacancy is
 * closed waits in the draft like everything else, so closing is a save and a publication — here
 * one transaction, one button.
 *
 * Only on a vacancy that is on the site with nothing waiting. With edits it would publish them
 * too, which is not what the button says; off the site it would put the vacancy back on it. Both
 * are refused ({@see refusal()}), and the editor publishes or discards first.
 *
 * Opening an expired vacancy again clears its last day as well — otherwise the button would leave
 * it exactly as closed as it was.
 */
final class Closing
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly VacancyWriter $writer,
    ) {}

    /** Why the button cannot do it now, as the words of a 409 — or null when it can. */
    public function refusal(Vacancy $vacancy): ?string
    {
        if (! $vacancy->isPublished()) {
            return (string) __('webx-vacancies::errors.close-unpublished');
        }

        if ($vacancy->hasDraft()) {
            return (string) __('webx-vacancies::errors.close-with-edits');
        }

        return null;
    }

    public function close(Vacancy $vacancy, ?int $authorId = null, string $source = EntityVersion::SOURCE_PANEL): Vacancy
    {
        return $this->apply($vacancy, ['is_closed' => true], $authorId, $source);
    }

    public function reopen(Vacancy $vacancy, ?int $authorId = null, string $source = EntityVersion::SOURCE_PANEL): Vacancy
    {
        $columns = ['is_closed' => false];
        $through = $vacancy->valid_through?->toDateString();

        if ($through !== null && $through < Day::today()) {
            $columns['valid_through'] = null;
        }

        return $this->apply($vacancy, $columns, $authorId, $source);
    }

    /**
     * @param  array<string, mixed>  $columns
     */
    private function apply(Vacancy $vacancy, array $columns, ?int $authorId, string $source): Vacancy
    {
        return $this->db->transaction(function () use ($vacancy, $columns, $authorId, $source): Vacancy {
            // Not through the writer's checks: they are about what an editor typed, and the button
            // types nothing — a vacancy put up after its last day must still be closable.
            $vacancy->saveDraft($this->writer->draft($vacancy, $columns), $authorId, $source);
            $vacancy->publish($authorId, $source);

            return $vacancy->refresh();
        });
    }
}
