<?php

declare(strict_types=1);

namespace WebxUi\Admin\History;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * The questions the journal answers, asked the same way by the panel's API and by an agent
 * (§5, §6). Who may ask them is the caller's to check — the type says which permission — so
 * this only reads.
 */
final readonly class HistoryReader
{
    public const PER_PAGE = 20;

    /**
     * One record's rows, newest first.
     *
     * @return LengthAwarePaginator<int, HistoryEntry>
     */
    public function forSubject(
        HistoryType $type,
        int $id,
        ?Carbon $since = null,
        ?string $field = null,
        int $page = 1,
        int $perPage = self::PER_PAGE,
    ): LengthAwarePaginator {
        $query = HistoryEntry::query()
            ->with('run')
            ->where('subject_type', $type->type)
            ->where('subject_id', $id);

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        if ($field !== null && $field !== '') {
            $this->touching($query, $field);
        }

        return $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    /**
     * Runs of the given types, newest first.
     *
     * @param  list<string>  $types
     * @return LengthAwarePaginator<int, HistoryEntry>
     */
    public function runs(
        array $types,
        ?string $source = null,
        ?Carbon $since = null,
        ?Carbon $until = null,
        int $page = 1,
        int $perPage = self::PER_PAGE,
    ): LengthAwarePaginator {
        $query = HistoryEntry::query()
            ->where('event', HistoryEntry::RUN)
            ->whereIn('subject_type', $types);

        if ($source !== null && $source !== '') {
            $query->where('source', $source);
        }

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        if ($until !== null) {
            $query->where('created_at', '<=', $until);
        }

        return $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    /**
     * The rows of one run, in the order they were written, or only those about one record.
     *
     * @return LengthAwarePaginator<int, HistoryEntry>
     */
    public function rowsOf(HistoryEntry $run, ?int $subjectId = null, int $page = 1, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = HistoryEntry::query()->with('run')->where('parent_id', $run->id);

        if ($subjectId !== null) {
            $query->where('subject_id', $subjectId);
        }

        return $query->orderBy('id')->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    /**
     * Rows whose changes name a field — `price`, or `name` for every language of it.
     *
     * Matched in the JSON text rather than through the database's JSON functions: three engines
     * spell those three ways, and a field name is a plain word the encoder writes one way. A name
     * that is not one — quotes, a percent sign — matches nothing rather than something else.
     *
     * @param  Builder<HistoryEntry>  $query
     */
    private function touching(Builder $query, string $field): void
    {
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $field) !== 1) {
            $query->whereRaw('1 = 0');

            return;
        }

        // MySQL hands a JSON column back as `"field": "price"`, sqlite as it was written.
        $query->where(static function (Builder $any) use ($field): void {
            foreach (['"field":"', '"field": "'] as $key) {
                $any->orWhere('changes', 'like', '%'.$key.$field.'"%')
                    ->orWhere('changes', 'like', '%'.$key.$field.'.%');
            }
        });
    }
}
