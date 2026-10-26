<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Editing\LastChange;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorForm;
use WebxUi\Configurator\Panel\ConfiguratorWriter;
use WebxUi\Configurator\Panel\Refused;
use WebxUi\Configurator\Panel\Revision;
use WebxUi\Localization\Locales;

/**
 * The list, the record and its draft (§13.3). Every write goes through {@see ConfiguratorWriter},
 * the door the MCP tools use too, so the panel and an agent are held to the same rules.
 */
final class ConfiguratorController
{
    public function __construct(
        private readonly ConfiguratorWriter $writer,
        private readonly ConfiguratorForm $form,
    ) {}

    /**
     * The whole list, without pages: a site has a handful of configurators. `search` matches the
     * title in any language and the key; `trashed` lists the bin instead.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ConfiguratorForm::withHistory(Configurator::query())->orderBy('id');

        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(static function (Builder $where) use ($like): void {
                $where->whereTranslationLikeAny('title', $like)->orWhere('key', 'like', $like);
            });
        }

        return ApiResponse::data(
            $query->get()->map(fn (Configurator $configurator): array => $this->form->row($configurator))->values()->all(),
        );
    }

    public function show(Configurator $configurator): JsonResponse
    {
        return ApiResponse::data($this->form->describe($configurator));
    }

    /** A new configurator from a name and a key — a draft with no steps, opened in the editor. */
    public function store(Request $request, Locales $locales): JsonResponse
    {
        $title = $request->input('title');
        $title = is_string($title) ? [$locales->defaultCode() => trim($title)] : (is_array($title) ? $title : []);
        $key = $request->input('key');

        try {
            $configurator = $this->writer->create(is_string($key) ? trim($key) : '', $title, authorId: $this->author($request));
        } catch (Refused $refused) {
            return self::refused($refused);
        }

        return ApiResponse::data($this->form->describe($configurator->refresh()), 201);
    }

    /**
     * The draft: what is sent replaces what was there. A revision that is not the record's is
     * somebody else's save in between — 409 with the record as it is now, for the editor to merge.
     * A schema with problems is saved; the answer lists them (§9).
     */
    public function update(Request $request, Configurator $configurator): JsonResponse
    {
        $sent = $request->input('revision');

        if (is_string($sent) && $sent !== Revision::of($configurator)) {
            return new JsonResponse([
                'message' => (string) __('webx-configurator::configurator.conflict'),
                'data' => $this->form->describe($configurator),
                'changed' => LastChange::of($configurator, $request->user()),
            ], 409);
        }

        $values = $request->input('values');
        $values = is_array($values) ? $values : [];
        $changes = [];

        foreach (['key', 'title', 'schema', 'form'] as $field) {
            if (array_key_exists($field, $values)) {
                $changes[$field] = $values[$field];
            }
        }

        if (array_key_exists('schema', $changes) && (! is_array($changes['schema']) || ($changes['schema'] !== [] && array_is_list($changes['schema'])))) {
            return self::invalid('schema', (string) __('webx-configurator::errors.schema.invalid'));
        }

        if (array_key_exists('title', $changes) && ! is_array($changes['title'])) {
            $changes['title'] = is_string($changes['title']) ? [$this->default() => $changes['title']] : [];
        }

        if (array_key_exists('title', $changes)) {
            $changes['title'] = array_filter($changes['title'], static fn (mixed $words): bool => is_string($words) && trim($words) !== '');
        }

        if (array_key_exists('form', $changes)) {
            $changes['form'] = is_string($changes['form']) && trim($changes['form']) !== '' ? trim($changes['form']) : null;
        }

        if (array_key_exists('key', $changes) && ! is_string($changes['key'])) {
            return self::invalid('key', (string) __('webx-configurator::configurator.refused.key.invalid'));
        }

        try {
            /** @var array{title?: array<string, string>, schema?: array<string, mixed>, form?: string|null, key?: string} $changes */
            $this->writer->update($configurator, $changes, $this->author($request));
        } catch (Refused $refused) {
            return self::refused($refused);
        }

        return ApiResponse::data($this->form->describe($configurator->refresh()));
    }

    /** Into the bin: off the site at once, the key stays taken so the bin can bring it back. */
    public function destroy(Configurator $configurator): JsonResponse
    {
        $configurator->delete();

        return ApiResponse::noContent();
    }

    /** Out of the bin. Not model-bound: the binding of the other routes cannot see the bin. */
    public function restore(int $configurator): JsonResponse
    {
        $trashed = Configurator::withTrashed()->findOrFail($configurator);
        $trashed->restore();

        return ApiResponse::data($this->form->row($trashed->refresh()));
    }

    /**
     * A refusal of the writer under the name of the field it is about, in the panel's words: the
     * writer's own are written for an agent and name tools the person does not have.
     */
    public static function refused(Refused $refused): JsonResponse
    {
        $key = 'webx-configurator::configurator.refused.'.($refused->reason ?? '');
        $message = $refused->reason !== null && trans()->has($key) ? (string) __($key) : $refused->getMessage();

        return self::invalid($refused->field() ?? 'values', $message);
    }

    private static function invalid(string $field, string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }

    private function default(): string
    {
        return app(Locales::class)->defaultCode();
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
