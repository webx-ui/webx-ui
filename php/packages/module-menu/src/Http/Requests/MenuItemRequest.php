<?php

declare(strict_types=1);

namespace WebxUi\Menu\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebxUi\Admin\Links\Link;
use WebxUi\Menu\Panel\ItemInput;

/**
 * One item of a menu, as its dialog sends it (§9).
 *
 * The rules are {@see ItemInput}'s, which is also what the agent's tools are checked against: a
 * menu item and a link field of a block are the same choice made in two places, and a second set
 * of rules here is the one that would drift. What is left for this request is reading what
 * arrived back out in the shapes the model wants.
 */
final class MenuItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ItemInput::rules((string) $this->route('key'));
    }

    /** Where it points, normalised the one way every link in the panel is normalised. */
    public function link(): Link
    {
        $link = $this->input('link');

        return Link::fromArray(is_array($link) ? $link : []);
    }

    /**
     * The label in each language, with the empty ones taken out — a language written and then
     * cleared is the same as one never written, and keeping `""` would make it a label.
     *
     * @return array<string, string>
     */
    public function titles(): array
    {
        $input = $this->input('title');
        $titles = [];

        foreach (is_array($input) ? $input : [] as $locale => $value) {
            if (is_string($value) && trim($value) !== '') {
                $titles[(string) $locale] = trim($value);
            }
        }

        return $titles;
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        $input = $this->input('locales');

        return array_values(array_unique(array_map(strval(...), is_array($input) ? $input : [])));
    }

    public function parentId(): ?int
    {
        $parent = $this->input('parent_id');

        return is_numeric($parent) ? (int) $parent : null;
    }
}
