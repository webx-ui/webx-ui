<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Catalog\Exchange\ExchangeFiles;
use WebxUi\Catalog\Exchange\ExchangeFormats;
use WebxUi\Catalog\Exchange\ExchangeProfile;
use WebxUi\Catalog\Exchange\Exporter;
use WebxUi\Catalog\Exchange\Importer;

/**
 * Saved profiles of the exchange (§8.2 of the exchange spec). What a profile says is checked the
 * way a run checks it — a mapping of columns the caller may use, settings that exist — so that a
 * profile never saves what its first run would refuse.
 */
final class ExchangeProfileController
{
    public function __construct(
        private readonly Importer $importer,
        private readonly Exporter $exporter,
        private readonly ExchangeFormats $formats,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ExchangeProfile::query()->orderBy('name');

        if (in_array($request->query('direction'), [ExchangeProfile::IMPORT, ExchangeProfile::EXPORT], true)) {
            $query->where('direction', $request->query('direction'));
        }

        return ApiResponse::data($query->get()->map(static fn (ExchangeProfile $profile): array => $profile->toResponse())->all());
    }

    public function show(int $profile): JsonResponse
    {
        return ApiResponse::data($this->find($profile)->toResponse());
    }

    public function store(Request $request): JsonResponse
    {
        $profile = new ExchangeProfile;
        $profile->forceFill($this->validated($request, null))->save();

        return ApiResponse::data($profile->toResponse(), 201);
    }

    public function update(Request $request, int $profile): JsonResponse
    {
        $found = $this->find($profile);
        $found->forceFill($this->validated($request, $found))->save();

        return ApiResponse::data($found->toResponse());
    }

    public function destroy(int $profile): JsonResponse
    {
        $this->find($profile)->delete();

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ExchangeProfile $current): array
    {
        $validated = $request->validate([
            'name' => [$current === null ? 'required' : 'sometimes', 'string', 'max:120'],
            'direction' => [$current === null ? 'required' : 'prohibited', 'in:import,export'],
            'format' => [$current === null ? 'required' : 'sometimes', 'string', 'in:'.implode(',', $this->formats->keys())],
            'options' => ['nullable', 'array'],
            'mapping' => ['nullable', 'array'],
        ]);

        $direction = (string) ($current->direction ?? $validated['direction']);
        $can = $this->can($request);
        $values = array_intersect_key($validated, array_flip(['name', 'direction', 'format']));

        if ($direction === ExchangeProfile::IMPORT) {
            if (! $can('catalog.manage')) {
                throw ExchangeFiles::refused('direction', 'forbidden', ['permission' => 'catalog.manage']);
            }

            $options = $this->importer->options((array) ($validated['options'] ?? $current->options ?? []));
            $values['options'] = $options;

            if (array_key_exists('mapping', $validated) || $current === null) {
                $values['mapping'] = $this->importer->mapping((array) ($validated['mapping'] ?? []), (string) $options['key'], $can);
            }

            return $values;
        }

        $values['options'] = (array) ($validated['options'] ?? $current->options ?? []);

        if (array_key_exists('mapping', $validated) || $current === null) {
            /** @var list<string> $codes */
            $codes = array_values(array_filter((array) ($validated['mapping'] ?? []), is_string(...)));
            $values['mapping'] = $this->exporter->codes($codes, $can);
        }

        return $values;
    }

    private function find(int $id): ExchangeProfile
    {
        return ExchangeProfile::query()->find($id) ?? throw new NotFoundHttpException;
    }

    /**
     * @return callable(string): bool
     */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
