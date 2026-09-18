<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Submissions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;

/**
 * The list of one form's submissions, with the answers that are columns in it.
 *
 * The columns of this list are the form's own fields (§2.12), which live one table away — so
 * each of them arrives as a correlated subquery, the way the reference implementation did it.
 * A join would multiply the rows by the answers and then have to undo it; an eager load would
 * bring every answer of every row back to sort ten of them on one.
 *
 * The aliases are `v_<field id>` and not the field's machine name: a name is the editor's to
 * change and may collide with a column of the table itself. The resource turns them back into
 * names, and a sort arrives as one and is translated here.
 */
final class ListQuery
{
    /** What a page holds when nobody says otherwise. */
    public const PER_PAGE = 25;

    /** The views the tabs above the list stand for. */
    public const VIEW_ALL = 'all';

    public const VIEW_UNREAD = 'unread';

    /**
     * @param  list<Field>  $columns  the `in_table` fields, in the order of the form
     */
    public function __construct(public readonly Form $form, public readonly array $columns) {}

    public static function for(Form $form): self
    {
        $columns = $form->liveFields()
            ->where('in_table', true)
            ->get()
            ->all();

        return new self($form, array_values($columns));
    }

    /** The alias one field's answers arrive under. */
    public static function alias(Field|int $field): string
    {
        return 'v_'.($field instanceof Field ? (int) $field->getKey() : $field);
    }

    /**
     * @return Builder<Submission>
     */
    public function build(Request $request): Builder
    {
        $query = Submission::query()
            ->select('inbox_submissions.*')
            ->where('inbox_submissions.form_id', $this->form->getKey())
            ->with(['status', 'assignee'])
            ->withCount('files');

        foreach ($this->columns as $field) {
            $query->selectRaw($this->column($field));
        }

        $this->view($query, (string) $request->query('view', self::VIEW_ALL));
        $this->filter($query, $request);
        $this->sort($query, (string) $request->query('sort', ''));

        return $query;
    }

    /**
     * What each tab above the list would hold.
     *
     * Counted under everything except the view itself, which is the one thing a tab decides:
     * a count that ignored the search would promise rows the tab then does not show, and a
     * count that included the view would say the same number on every tab.
     *
     * @return array{all: int, unread: int, statuses: array<string, int>}
     */
    public function counts(Request $request): array
    {
        $base = function () use ($request): Builder {
            $query = Submission::query()->where('inbox_submissions.form_id', $this->form->getKey());
            $this->filter($query, $request);

            return $query;
        };

        /** @var array<int, int> $byStatus */
        $byStatus = $base()
            ->toBase()
            ->selectRaw('status_id, count(*) as total')
            ->groupBy('status_id')
            ->pluck('total', 'status_id')
            ->all();

        $statuses = [];
        $all = 0;

        foreach (Status::query()->orderBy('position')->get() as $status) {
            $count = (int) ($byStatus[$status->getKey()] ?? 0);
            $statuses[$status->key] = $count;

            // "All" is everything that is not spam, exactly as the tab shows it.
            if (! $status->is_spam) {
                $all += $count;
            }
        }

        return [
            'all' => $all,
            'unread' => (int) $base()->whereNull('read_at')->whereNotIn('status_id', $this->spamIds())->count(),
            'statuses' => $statuses,
        ];
    }

    /**
     * The submissions either side of this one, in the list it was opened from.
     *
     * The filters are honoured — that is what makes the arrows in the card's head walk the
     * pile somebody was actually looking at — and the order is the list's default, newest
     * first. A custom sort is deliberately not followed: it would turn two cheap comparisons
     * into a scan for a position, and nobody steps through a list they have sorted by a
     * column of answers.
     *
     * @return array{previous: int|null, next: int|null}
     */
    public function neighbours(Request $request, Submission $submission): array
    {
        $around = function () use ($request): Builder {
            $query = Submission::query()->where('inbox_submissions.form_id', $this->form->getKey());
            $this->view($query, (string) $request->query('view', self::VIEW_ALL));
            $this->filter($query, $request);

            return $query;
        };

        $id = (int) $submission->getKey();

        return [
            'previous' => $around()->where('inbox_submissions.id', '>', $id)
                ->orderBy('inbox_submissions.id')->value('inbox_submissions.id'),
            'next' => $around()->where('inbox_submissions.id', '<', $id)
                ->orderByDesc('inbox_submissions.id')->value('inbox_submissions.id'),
        ];
    }

    /**
     * One answer as a column.
     *
     * `form_id` is in the condition as well as `submission_id` because the values table
     * carries it for exactly this: it lets the index do the work rather than the row.
     */
    private function column(Field $field): string
    {
        $alias = self::alias($field);

        return '(select v.value from inbox_submission_values as v'
            .' where v.submission_id = inbox_submissions.id'
            .' and v.form_id = inbox_submissions.form_id'
            .' and v.field_id = '.(int) $field->getKey()
            .' limit 1) as '.$alias;
    }

    /**
     * Which tab is open. Apart from the rows, so that the counts of every tab can be taken
     * under the same filters.
     *
     * @param  Builder<Submission>  $query
     */
    private function view(Builder $query, string $view): void
    {
        match (true) {
            $view === self::VIEW_UNREAD => $query->whereNull('read_at')->whereNotIn('status_id', $this->spamIds()),
            // A status by key, which is what the tab carries: an id would change between a
            // site and its copy, and the tabs are built from the same list either way.
            $view !== self::VIEW_ALL => $query->whereIn('status_id', $this->statusIds($view)),
            // Spam is out of "all" by design (§3): it is kept, it is reachable by its own tab,
            // and it is not what anybody means when they ask what has come in.
            default => $query->whereNotIn('status_id', $this->spamIds()),
        };
    }

    /**
     * @param  Builder<Submission>  $query
     */
    private function filter(Builder $query, Request $request): void
    {
        if ($request->filled('assignee')) {
            $assignee = (string) $request->query('assignee');

            // "Nobody" is a filter people actually want — it is the pile that has not been
            // picked up — and it cannot be said with an id.
            $assignee === 'none'
                ? $query->whereNull('assignee_id')
                : $query->where('assignee_id', (int) $assignee);
        }

        if ($request->filled('from')) {
            $query->where('inbox_submissions.created_at', '>=', Carbon::parse((string) $request->query('from'))->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('inbox_submissions.created_at', '<=', Carbon::parse((string) $request->query('to'))->endOfDay());
        }

        $term = trim((string) $request->query('search', ''));

        if ($term !== '') {
            $this->search($query, $term);
        }
    }

    /**
     * Search across every answer, not only the ones that are columns.
     *
     * Somebody looking for a telephone number has no idea which field it was typed into, and
     * a search that only looked at the columns would answer "nothing" to a submission that is
     * sitting right there.
     *
     * @param  Builder<Submission>  $query
     */
    private function search(Builder $query, string $term): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $where) use ($like, $term): void {
            $where->whereExists(function ($exists) use ($like): void {
                $exists->selectRaw('1')
                    ->from('inbox_submission_values as s')
                    ->whereColumn('s.submission_id', 'inbox_submissions.id')
                    ->where('s.value', 'like', $like);
            });

            // A list whose rows are identified by number is a list somebody pastes a number
            // into.
            if (ctype_digit($term)) {
                $where->orWhere('inbox_submissions.id', (int) $term);
            }
        });
    }

    /**
     * Newest first unless asked otherwise — a submission list is read from the top, where
     * what has not been dealt with is.
     *
     * @param  Builder<Submission>  $query
     */
    private function sort(Builder $query, string $sort): void
    {
        $descending = str_starts_with($sort, '-');
        $key = ltrim($sort, '-');

        if ($key === '') {
            $query->orderByDesc('inbox_submissions.id');

            return;
        }

        $direction = $descending ? 'desc' : 'asc';

        // A column of answers, named the way the table draws it.
        foreach ($this->columns as $field) {
            if ($key === 'values.'.$field->key()) {
                $query->orderByRaw(self::alias($field).' '.$direction);

                return;
            }
        }

        $column = match ($key) {
            'created_at' => 'inbox_submissions.created_at',
            'status' => 'status_id',
            'id' => 'inbox_submissions.id',
            default => null,
        };

        $column === null
            ? $query->orderByDesc('inbox_submissions.id')
            : $query->orderBy($column, $direction)->orderByDesc('inbox_submissions.id');
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

    /**
     * @return list<int>
     */
    private function statusIds(string $key): array
    {
        /** @var list<int> $ids */
        $ids = Status::query()->where('key', $key)->pluck('id')->all();

        return $ids;
    }
}
