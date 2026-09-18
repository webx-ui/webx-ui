<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use WebxUi\Inbox\Http\Resources\AdminBrief;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Submissions\ListQuery;

/**
 * The list as a spreadsheet, under whatever filter is on (§12).
 *
 * Every field of the form is a column and not only the ones the list shows: a column is chosen
 * to make a list readable on screen, and an export is what somebody opens precisely because the
 * screen did not have room. The answers come from the relation rather than from `ListQuery`'s
 * subqueries for the same reason — one per field would be forty subqueries on a long form.
 *
 * Streamed, and in chunks: an export is the one request in the panel whose size is decided by
 * how successful the site has been.
 */
final class SubmissionExportController
{
    /** How many rows are held in memory at once. */
    private const CHUNK = 500;

    public function __invoke(Request $request, Form $form): StreamedResponse
    {
        /** @var list<Field> $fields */
        $fields = $form->fields()->get()->all();

        $query = ListQuery::for($form)->build($request)->with('values');

        $name = $form->slug.'-'.Carbon::now()->format('Y-m-d-His').'.csv';

        return new StreamedResponse(function () use ($query, $fields): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // Excel reads a UTF-8 file as the local code page unless it is told, and a column
            // of Cyrillic names opened as mojibake is an export nobody trusts again.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $this->heading($fields));

            $query->chunk(self::CHUNK, function ($submissions) use ($out, $fields): void {
                foreach ($submissions as $submission) {
                    fputcsv($out, $this->row($submission, $fields));
                }
            });

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @param  list<Field>  $fields
     * @return list<string>
     */
    private function heading(array $fields): array
    {
        $row = [
            (string) __('webx-inbox::panel.id'),
            (string) __('webx-inbox::panel.received'),
            (string) __('webx-inbox::panel.status'),
            (string) __('webx-inbox::panel.assignee'),
        ];

        foreach ($fields as $field) {
            // The label, and the machine name beside it: the label is what a person reads and
            // the name is what a script matching columns can rely on (§2.6).
            $label = (string) $field->title;
            $row[] = $label === '' ? $field->key() : $label.' ('.$field->key().')';
        }

        return $row;
    }

    /**
     * @param  list<Field>  $fields
     * @return list<string>
     */
    private function row(Submission $submission, array $fields): array
    {
        $answers = [];

        foreach ($submission->values as $value) {
            $answers[$value->name] = (string) $value->value;
        }

        $assignee = AdminBrief::of($submission->assignee);

        $row = [
            (string) $submission->getKey(),
            $submission->created_at?->toDateTimeString() ?? '',
            (string) $submission->status->title,
            $assignee['name'] ?? '',
        ];

        foreach ($fields as $field) {
            $row[] = $answers[$field->key()] ?? '';
        }

        return $row;
    }
}
