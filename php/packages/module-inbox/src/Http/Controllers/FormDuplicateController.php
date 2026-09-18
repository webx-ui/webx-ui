<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Inbox\Http\Resources\FormResource;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;

/**
 * A copy of a form, with its questions and none of its answers.
 *
 * Switched off, and that is the point of it: a copy exists to be edited, and a second live
 * form answering at `contact-2` before anybody has looked at it is a form the site did not
 * mean to have. The recipients come along — they are nearly always the same people, and a
 * copy that silently notifies nobody is the failure this module is most afraid of.
 */
final class FormDuplicateController
{
    public function __invoke(Form $form): JsonResponse
    {
        $copy = DB::transaction(function () use ($form): Form {
            [$slug, $suffix] = $this->freeSlug($form->slug);

            $made = new Form([
                'slug' => $slug,
                // The same number as the address, in every language the original was named
                // in: a digit says "the second one" without being in anybody's language.
                'title' => $this->title($form, $suffix),
                'is_enabled' => false,
                'options' => $form->options ?? [],
            ]);

            $made->position = (int) Form::query()->max('position') + 1;
            $made->save();

            foreach ($form->fields as $field) {
                $made->fields()->create($this->copyOf($field));
            }

            return $made;
        });

        return ApiResponse::data(new FormResource($copy->load('fields')), 201);
    }

    /**
     * `contact-2`, then `contact-3`.
     *
     * @return array{string, int}
     */
    private function freeSlug(string $slug): array
    {
        $base = mb_substr((string) preg_replace('/-\d+$/', '', $slug), 0, 90);
        $taken = Form::query()->pluck('slug')->all();

        for ($suffix = 2; $suffix < 1000; $suffix++) {
            $candidate = "{$base}-{$suffix}";

            if (! in_array($candidate, $taken, true)) {
                return [$candidate, $suffix];
            }
        }

        return [$base.'-'.uniqid(), 0];
    }

    /**
     * @return array<string, string>
     */
    private function title(Form $form, int $suffix): array
    {
        $titles = [];

        foreach ($form->getTranslations('title') as $locale => $title) {
            $titles[$locale] = trim((string) preg_replace('/\s+\d+$/', '', (string) $title))." {$suffix}";
        }

        return $titles;
    }

    /**
     * @return array<string, mixed>
     */
    private function copyOf(Field $field): array
    {
        return [
            'name' => $field->name,
            'type' => $field->type,
            'title' => $field->getTranslations('title'),
            'placeholder' => $field->getTranslations('placeholder'),
            'help' => $field->getTranslations('help'),
            'options' => $field->options ?? [],
            'is_enabled' => $field->is_enabled,
            'is_required' => $field->is_required,
            'is_fullsize' => $field->is_fullsize,
            'in_table' => $field->in_table,
            'position' => $field->position,
        ];
    }
}
