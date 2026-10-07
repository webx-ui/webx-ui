<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use WebxUi\Pages\Models\Page;

/**
 * What the section itself writes: a name, an address and a place in the tree.
 *
 * Only in the site's main language — never the one the panel is open in, which need not be a
 * language the site has. A page is translated in its own form, field by field beside the rest
 * of its content, and a dialog that asked for ten titles before the page exists would be a
 * worse way to start one.
 */
final class PageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // The address is checked for shape here and for being free by the registry, which
            // is the only thing that can answer that — it sees every kind of entity.
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[\p{L}\p{N}]+(?:[-_][\p{L}\p{N}]+)*$/u'],
            // A parent that does not exist is a mistake in the form, under its field — not a 404
            // that reads as if the page being created were the thing missing. One in the bin
            // exists and is refused by the controller, which can say why.
            'parent_id' => ['nullable', 'integer', Rule::exists(Page::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.regex' => (string) __('webx-pages::errors.slug-shape')];
    }

    /**
     * The fields by the names the dialog gives them, so that a refusal reads «Заголовок», not
     * «title».
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => (string) __('webx-pages::page.field-title'),
            'slug' => (string) __('webx-pages::page.field-slug'),
            'parent_id' => (string) __('webx-pages::page.field-parent'),
        ];
    }

    public function title(): string
    {
        return trim((string) $this->input('title'));
    }

    /**
     * The address, made out of the name when the editor did not write one.
     *
     * `Str::slug` transliterates, so a Russian title gives a Latin address rather than a
     * percent-encoded one — which is what a site wants and what nobody wants to type by hand.
     */
    public function slug(): string
    {
        $slug = trim((string) $this->input('slug', ''));

        return $slug !== '' ? $slug : Str::slug($this->title());
    }

    public function parentId(): ?int
    {
        $parent = $this->input('parent_id');

        return is_numeric($parent) ? (int) $parent : null;
    }
}
