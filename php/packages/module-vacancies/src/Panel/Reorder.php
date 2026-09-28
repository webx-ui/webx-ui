<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Panel;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * The one order vacancies have, the same in every group of the index — the panel's drag and an
 * agent's `vacancies_reorder` alike.
 *
 * Not the shared `Ordering::move()`, which numbers what it is given from the top: the ids are the
 * list as somebody sees it — the open ones, or all of them — and they take the places they hold
 * now, in the new order. The Open tab drags among the open ones, and the closed ones between them
 * stay where they were. One transaction.
 */
final class Reorder
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @param  list<int>  $ids
     *
     * @throws ValidationException under `ids`, when one of them is no vacancy
     */
    public function move(array $ids): void
    {
        $ids = array_values(array_unique($ids));

        if ($ids === [] || Vacancy::query()->withTrashed()->whereKey($ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['ids' => (string) __('webx-vacancies::errors.unknown-vacancy')]);
        }

        $this->db->transaction(static function () use ($ids): void {
            // Places shared by two rows (a list nobody dragged yet) are made a run first, in the
            // order the list shows them: handing a shared place out twice would tie them again.
            $all = Vacancy::query()->withTrashed()->orderBy('position')->orderBy('id')->pluck('position', 'id');

            if ($all->unique()->count() !== $all->count()) {
                foreach ($all->keys()->values() as $place => $id) {
                    Vacancy::query()->withTrashed()->whereKey($id)->update(['position' => $place]);
                }
            }

            /** @var list<int> $slots */
            $slots = Vacancy::query()->withTrashed()->whereKey($ids)->orderBy('position')->pluck('position')->map(intval(...))->all();

            foreach ($ids as $n => $id) {
                Vacancy::query()->withTrashed()->whereKey($id)->update(['position' => $slots[$n]]);
            }
        });
    }
}
