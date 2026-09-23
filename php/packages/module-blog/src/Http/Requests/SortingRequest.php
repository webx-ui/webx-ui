<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The new order of the rubrics, as a list of ids (§6, §11).
 *
 * Whole-list rather than "this one moved to there": a drag is one request either way, and a
 * list sent whole cannot end up half applied when two people reorder at once — the last one to
 * let go wins, which is what both of them see on screen.
 */
final class SortingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        /** @var array<mixed> $ids */
        $ids = (array) $this->input('ids', []);

        return array_values(array_map(intval(...), $ids));
    }
}
