<?php

declare(strict_types=1);

namespace WebxUi\Users\Doctor;

use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Users\Fields\UserField;
use WebxUi\Users\Fields\UserFields;

/**
 * Whether the fields of the site on its visitors can work (spec §10.1, §10.2): the same refusal a
 * page meets — a taken name, a type without a field on the site — said here before a visitor
 * meets it; and which `unique` field is checked without an index (§10.3).
 */
final readonly class FieldsCheck implements Check
{
    private const string SUBJECT = 'Visitors';

    public function __construct(private UserFields $fields) {}

    public function run(): array
    {
        try {
            $fields = $this->fields->all();
        } catch (InvalidArgumentException $exception) {
            return [Diagnosis::fail(self::SUBJECT, $exception->getMessage())];
        }

        if ($fields === []) {
            return [Diagnosis::ok(self::SUBJECT, 'no fields of the site on the visitors.')];
        }

        $names = implode(', ', array_map(static fn (UserField $field): string => $field->name, $fields));
        $diagnoses = [Diagnosis::ok(self::SUBJECT, "fields of the site on the visitors: {$names}.")];

        foreach ($fields as $field) {
            if ($field->unique && ! Schema::hasColumn('users', $field->indexColumn())) {
                $diagnoses[] = Diagnosis::note(
                    self::SUBJECT,
                    "«{$field->name}» is unique and checked through the JSON of `extra`, without an index — "
                    ."fine for thousands of visitors; for a fast search run `php artisan webx:users:index-field {$field->name}`.",
                );
            }
        }

        return $diagnoses;
    }
}
