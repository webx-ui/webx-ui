<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the selection bar does to a pile of tags at once (§10).
 *
 * One endpoint for the three, and not three: raking tags over means selecting thirty of them
 * and opening or deleting the lot, and thirty requests for one gesture is thirty chances to
 * end up half done. Merging is not here — it asks two more questions and has a dialog of its
 * own ({@see TagMergeRequest}).
 */
final class TagMassRequest extends FormRequest
{
    public const INDEX = 'index';

    public const NOINDEX = 'noindex';

    public const DELETE = 'delete';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in([self::INDEX, self::NOINDEX, self::DELETE])],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        /** @var array<mixed> $ids */
        $ids = (array) $this->input('ids', []);

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    public function action(): string
    {
        return (string) $this->input('action');
    }
}
