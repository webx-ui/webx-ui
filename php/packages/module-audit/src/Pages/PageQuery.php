<?php

declare(strict_types=1);

namespace WebxUi\Audit\Pages;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Audit\Runs\AuditRun;

/**
 * The pages of a run under the screen's filters — the list and the CSV build the same query.
 *
 * - `search` — part of the address;
 * - `status` — `2xx`, `3xx`, `4xx`, `5xx` or `none` (nothing answered);
 * - `indexable` — `1` or `0`;
 * - `check` — pages with a finding of this check;
 * - `f[<field>]` — any field of {@see PageColumns}: `empty`, `filled`, `yes`, `no`,
 *   `contains:<text>`, `eq:<value>`, `gt:<n>`, `lt:<n>`;
 * - `sort` — a field, `-field` for descending.
 */
final class PageQuery
{
    /**
     * @return Builder<AuditPage>
     */
    public static function build(AuditRun $run, Request $request): Builder
    {
        $query = AuditPage::query()
            ->where('run_id', $run->id)
            ->whereNotNull('fetched_at')
            ->withCount(['issues' => static fn (Builder $issues) => $issues->whereNull('ignored_by')]);

        $search = trim($request->string('search')->toString());

        if ($search !== '') {
            $query->where('url', 'like', '%'.self::escape($search).'%');
        }

        $status = $request->string('status')->toString();

        if (preg_match('/^([1-5])xx$/', $status, $class) === 1) {
            $query->whereBetween('status', [(int) $class[1] * 100, (int) $class[1] * 100 + 99]);
        } elseif ($status === 'none') {
            $query->whereNull('status');
        }

        if ($request->filled('indexable')) {
            $query->where('indexable', $request->boolean('indexable'));
        }

        if ($request->filled('check')) {
            $check = $request->string('check')->toString();
            $query->whereIn('id', AuditIssue::query()->select('page_id')->where('run_id', $run->id)->where('check', $check)->whereNotNull('page_id'));
        }

        foreach ((array) $request->input('f', []) as $field => $condition) {
            if (is_string($field) && is_string($condition)) {
                self::filter($query, $field, $condition);
            }
        }

        $sort = $request->string('sort')->toString();
        $key = ltrim($sort, '-');

        if ($key === 'issues') {
            $query->orderBy('issues_count', str_starts_with($sort, '-') ? 'desc' : 'asc');
        } elseif (PageColumns::filterable($key)) {
            $query->orderBy($key, str_starts_with($sort, '-') ? 'desc' : 'asc');
        }

        return $query->orderBy('id');
    }

    /**
     * @param  Builder<AuditPage>  $query
     */
    private static function filter(Builder $query, string $field, string $condition): void
    {
        if (! PageColumns::filterable($field)) {
            return;
        }

        [$op, $value] = array_pad(explode(':', $condition, 2), 2, '');

        if ($field === 'issues') {
            match ($op) {
                'gt' => $query->has('issues', '>', (int) $value),
                'lt' => $query->has('issues', '<', (int) $value),
                'eq' => $query->has('issues', '=', (int) $value),
                'empty' => $query->doesntHave('issues'),
                'filled' => $query->has('issues'),
                default => null,
            };

            return;
        }

        match ($op) {
            'empty' => $query->where(static fn (Builder $empty) => $empty->whereNull($field)->orWhere($field, '')),
            'filled' => $query->whereNotNull($field)->where($field, '<>', ''),
            'yes' => $query->where($field, true),
            'no' => $query->where($field, false),
            'contains' => $query->where($field, 'like', '%'.self::escape($value).'%'),
            'eq' => $query->where($field, $value),
            'gt' => $query->where($field, '>', is_numeric($value) ? (float) $value : $value),
            'lt' => $query->where(static fn (Builder $less) => $less->where($field, '<', is_numeric($value) ? (float) $value : $value)->when(
                PageColumns::ALL[$field] === 'number',
                static fn (Builder $none) => $none->orWhereNull($field),
            )),
            default => null,
        };
    }

    private static function escape(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
