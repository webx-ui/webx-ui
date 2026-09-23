<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Merging tags into one (§6, §11).
 *
 * Three answers, and the dialog asks for all three out loud: which tags are going, which one
 * stays, and whether the addresses that existed should go on answering. The last is a checkbox
 * rather than something done quietly, because a redirect is a permanent fact about the site and
 * a tag made by mistake this morning does not deserve one.
 */
final class TagMergeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'keep' => ['required', 'integer'],
            'redirect' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The tags that are going: what was sent, without the one that stays.
     *
     * Filtered here rather than refused, because the screen selects the tags and then asks
     * which of them to keep — so the surviving tag is always in the list, and a request that
     * named it twice would be the panel doing exactly what it was designed to do.
     *
     * @return list<int>
     */
    public function merged(): array
    {
        /** @var array<mixed> $ids */
        $ids = (array) $this->input('ids', []);

        return array_values(array_filter(
            array_unique(array_map(intval(...), $ids)),
            fn (int $id): bool => $id !== $this->keep(),
        ));
    }

    public function keep(): int
    {
        return (int) $this->input('keep');
    }

    public function redirect(): bool
    {
        return $this->boolean('redirect');
    }
}
