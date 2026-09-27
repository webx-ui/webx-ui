<?php

declare(strict_types=1);

namespace WebxUi\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use WebxUi\Admin\Screens\Types\SlugType;

/**
 * Starting an event: a title, and the address made out of it.
 *
 * One language — an event is translated in its own form, field beside field. Whether the address
 * is free only the registry can answer, since it sees categories and pages at once; it answers by
 * refusing the save (`OnConflict::Fail`), which arrives under `slug` like any other error.
 */
final class EventRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => SlugType::checks(),
        ];
    }

    public function title(): string
    {
        return trim((string) $this->input('title', ''));
    }

    /** The address, transliterated out of the title when the editor did not write one. */
    public function slug(): string
    {
        $slug = trim((string) $this->input('slug', ''));

        return $slug !== '' ? $slug : Str::slug($this->title());
    }
}
