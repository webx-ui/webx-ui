<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\BlockInput;

/**
 * A block type as the panel sends it: the row's own fields, and — when the editor touched
 * them — the content of the next version.
 *
 * `POST` needs a slug and a name and nothing else: a new type starts as an empty draft. `PUT`
 * takes whatever changed. The content travels under `content` so that a save of the settings
 * tab alone does not write a version that differs from the last one in nothing. The rules
 * themselves are {@see BlockInput}'s, shared with the agent's tools and the importer.
 */
final class BlockRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('block');

        return [
            ...BlockInput::rowRules($this->isMethod('POST'), $current instanceof Block ? $current->id : null),
            'content' => ['sometimes', 'array:'.implode(',', BlockVersion::CONTENT)],
            ...BlockInput::contentRules('content.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return BlockInput::messages();
    }

    /**
     * The row's fields, only the ones that were sent.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return BlockInput::values($this->all());
    }

    /**
     * The next version's content, or null when the request carried none.
     *
     * @return array<string, mixed>|null
     */
    public function content(): ?array
    {
        $content = $this->input('content');

        return is_array($content) ? BlockInput::content($content) : null;
    }

    public function comment(): ?string
    {
        return BlockInput::comment($this->input('comment'));
    }
}
