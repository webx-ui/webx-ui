<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Inbox\Fields\ConditionPlan;
use WebxUi\Inbox\Http\Requests\SortingRequest;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Panel\Sorting;

/**
 * The order the questions are asked in.
 *
 * Scoped to the form in the address: the ids arrive from a list the panel drew, and a list
 * drawn from one form must not be able to renumber another one's.
 */
final class FieldSortingController
{
    public function __invoke(SortingRequest $request, Form $form): JsonResponse
    {
        // A field is not dragged above a field its condition names, nor that field below it
        // (FORM_STEPS §4.2): checked on the order as it would be, before it is written.
        ConditionPlan::assertAll(ConditionPlan::layout(self::ordered($form->fields, $request->ids())));

        Sorting::apply($form->fields()->getQuery()->reorder(), $request->ids());

        return ApiResponse::noContent();
    }

    /**
     * The fields in the order `Sorting::apply` would leave them: the ones named take their place
     * in the list, the rest keep theirs.
     *
     * @param  iterable<Field>  $fields
     * @param  list<int>  $ids
     * @return list<Field>
     */
    private static function ordered(iterable $fields, array $ids): array
    {
        $places = array_flip($ids);
        $rows = [];

        foreach ($fields as $field) {
            $rows[] = [$places[(int) $field->getKey()] ?? $field->position, (int) $field->getKey(), $field];
        }

        usort($rows, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_column($rows, 2);
    }
}
