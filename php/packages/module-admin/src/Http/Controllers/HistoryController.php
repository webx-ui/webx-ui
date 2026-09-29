<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\History\HistoryPresenter;
use WebxUi\Admin\History\HistoryReader;
use WebxUi\Admin\History\HistoryType;
use WebxUi\Admin\History\HistoryTypes;

/**
 * The journal of any record, through one address (WEBX_UI_HISTORY.md §5).
 *
 * The rules are the notes' (`NoteController`): the type in the address has to be one a module
 * registered, and the type says which permission its history is behind — the same one its list
 * is, since a record's history tells as much as the record.
 */
final class HistoryController
{
    public function __construct(
        private readonly HistoryTypes $types,
        private readonly HistoryReader $reader,
        private readonly HistoryPresenter $presenter,
    ) {}

    public function index(Request $request, string $type, int $id): JsonResponse
    {
        $found = $this->allowed($request, $this->types->find($type));

        $page = $this->reader->forSubject(
            $found,
            $id,
            $this->since($request->query('since')),
            is_string($request->query('field')) ? $request->query('field') : null,
            max(1, (int) $request->query('page', '1')),
        );

        // As `->paginate()` has it: the table and the feed on the other side read that shape.
        return new JsonResponse($page->through(fn (HistoryEntry $entry): array => $this->presenter->row($entry))->toArray());
    }

    public function run(Request $request, int $id): JsonResponse
    {
        $run = HistoryEntry::query()->where('event', HistoryEntry::RUN)->find($id);

        if (! $run instanceof HistoryEntry) {
            throw new NotFoundHttpException((string) __('webx-admin::history.missing'));
        }

        $this->allowed($request, $this->types->find($run->subject_type));

        $search = $request->query('search');

        $rows = $this->reader->rowsOf(
            $run,
            is_string($search) && ctype_digit($search) ? (int) $search : null,
            max(1, (int) $request->query('page', '1')),
        );

        return new JsonResponse([
            'run' => $this->presenter->row($run) + ['rows' => $run->rows()->count()],
            'rows' => $rows->through(fn (HistoryEntry $entry): array => $this->presenter->row($entry))->toArray(),
        ]);
    }

    private function allowed(Request $request, ?HistoryType $type): HistoryType
    {
        if ($type === null) {
            throw new NotFoundHttpException((string) __('webx-admin::history.no-type'));
        }

        if (! $type->allows($request->user())) {
            throw new AccessDeniedHttpException((string) __('webx-admin::history.forbidden'));
        }

        return $type;
    }

    private function since(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
