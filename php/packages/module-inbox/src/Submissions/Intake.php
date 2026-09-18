<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Submissions;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use WebxUi\Inbox\Exceptions\NoStatuses;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;
use WebxUi\Inbox\Storage\FileStore;

/**
 * Turning validated input into a submission (§6.4–6.5).
 *
 * The order matters and is the point of §2.10: the row and its files are written first, and
 * only then is anybody told about it. An SMTP server that is down must cost a notification and
 * not an enquiry.
 */
final class Intake
{
    public function __construct(
        private readonly FileStore $files,
        private readonly Config $config,
        private readonly Meta $meta,
    ) {}

    /**
     * @param  array<string, mixed>  $values  what arrived under `fields`, already validated
     */
    public function receive(Form $form, array $values, Request $request, string $source = Submission::SOURCE_WEB): Submission
    {
        $hash = $this->hash($form, $values);
        $submission = $this->find($form, $hash);

        if ($submission === null) {
            $submission = $this->create($form, $hash, $request, $source);
        } else {
            // The same thing again inside the window is the same submission, so what was
            // written for it the first time goes and is written again — otherwise a corrected
            // second attempt would leave both answers in the list.
            $submission->files->each->delete();
            $submission->values()->delete();
            $submission->forceFill(['meta' => $this->meta->of($request)])->save();
        }

        $this->write($submission, $form, $values, $request);

        return $submission->refresh();
    }

    /**
     * md5 of the answers, so the same form sent twice by one double click is one row.
     *
     * Sorted by key, because the order the browser sends fields in is not the module's to
     * rely on; files are left out, since the same file picked twice is two temporary paths
     * and two hashes for what a person would call one submission.
     *
     * @param  array<string, mixed>  $values
     */
    public function hash(Form $form, array $values): string
    {
        $subject = [];

        foreach ($form->liveFields as $field) {
            if ($field->type === FieldType::File) {
                continue;
            }

            $key = $field->key();
            $subject[$key] = $values[$key] ?? null;
        }

        ksort($subject);

        return md5($form->getKey().'|'.json_encode($subject, JSON_UNESCAPED_UNICODE));
    }

    /**
     * The same submission inside the window (§2.7).
     *
     * Without the window, the same enquiry sent again a month later would overwrite the first
     * one and take its date with it: one of the two people who wrote would silently stop
     * having written.
     */
    private function find(Form $form, string $hash): ?Submission
    {
        $window = (int) $this->config->get('webx-inbox.duplicate_window', 900);

        if ($window <= 0) {
            return null;
        }

        return Submission::query()
            ->where('form_id', $form->getKey())
            ->where('hash', $hash)
            ->where('created_at', '>=', Carbon::now()->subSeconds($window))
            ->latest('id')
            ->first();
    }

    private function create(Form $form, string $hash, Request $request, string $source): Submission
    {
        $status = Status::default();

        if ($status === null) {
            throw new NoStatuses;
        }

        $submission = Submission::query()->create([
            'form_id' => $form->getKey(),
            'status_id' => $status->getKey(),
            'hash' => $hash,
            'source' => $source,
            'meta' => $this->meta->of($request),
        ]);

        $submission->log(SubmissionEvent::CREATED, null, $status->key);

        return $submission;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function write(Submission $submission, Form $form, array $values, Request $request): void
    {
        foreach ($form->liveFields as $field) {
            $key = $field->key();

            if ($field->type === FieldType::File) {
                $this->writeFiles($submission, $form, $field, $request);

                continue;
            }

            if (! array_key_exists($key, $values) || $this->isEmpty($values[$key])) {
                continue;
            }

            [$value, $payload] = $this->readable($field, $values[$key]);

            $this->value($submission, $form, $field, $value, $payload);
        }
    }

    private function writeFiles(Submission $submission, Form $form, Field $field, Request $request): void
    {
        $uploads = $request->file('fields.'.$field->key());

        if ($uploads === null) {
            return;
        }

        /** @var list<UploadedFile> $uploads */
        $uploads = array_values(array_filter(
            is_array($uploads) ? $uploads : [$uploads],
            static fn ($upload): bool => $upload instanceof UploadedFile,
        ));

        if ($uploads === []) {
            return;
        }

        $names = [];
        $payload = [];

        foreach ($uploads as $upload) {
            $file = $this->files->store($upload, $submission, $field);

            $names[] = $file->name;
            $payload[] = ['id' => $file->getKey(), 'name' => $file->name, 'size' => $file->size];
        }

        // The answer is still written as text, so that a file field is one line in the mail
        // and one column in the export like every other field; the files themselves are
        // beside it.
        $this->value($submission, $form, $field, implode(', ', $names), $payload);
    }

    /**
     * @param  list<mixed>|array<string, mixed>|null  $payload
     */
    private function value(Submission $submission, Form $form, Field $field, string $value, ?array $payload): void
    {
        $submission->values()->create([
            'form_id' => $form->getKey(),
            'field_id' => $field->getKey(),
            'name' => $field->key(),
            // The snapshot (§2.2). Read in the language the submission arrived in, which is
            // the one the person who sent it was looking at.
            'label' => mb_substr((string) $field->title, 0, 255),
            'type' => $field->type->value,
            'value' => $value,
            'payload' => $payload,
        ]);
    }

    /**
     * What a person will read, and beside it the structure they will not.
     *
     * Choices become their labels, because the value of a `select` is `plan-b` and what
     * belongs in the mail is "Plan B"; the raw values stay in the payload for anything that
     * has to match them later.
     *
     * @return array{0: string, 1: list<mixed>|null}
     */
    private function readable(Field $field, mixed $value): array
    {
        $choices = $field->choices();

        if (is_array($value)) {
            $raw = array_values(array_filter($value, 'is_scalar'));
            $labels = array_map(
                static fn ($one): string => $choices[(string) $one] ?? (string) $one,
                $raw,
            );

            return [implode(', ', $labels), $raw];
        }

        if ($field->type === FieldType::Consent) {
            return [(string) trans('webx-inbox::values.consented'), null];
        }

        $one = (string) $value;

        return [$choices[$one] ?? $one, $choices === [] ? null : [$one]];
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || (is_string($value) && trim($value) === '');
    }
}
