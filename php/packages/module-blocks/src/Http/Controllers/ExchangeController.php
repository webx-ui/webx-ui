<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blocks\Panel\CallCycle;
use WebxUi\Blocks\Panel\Exchange;
use WebxUi\Blocks\Panel\Importer;

/**
 * Export and import from the section (§17.1): the command's files, moved by a person.
 *
 * The export answers with the pack as data and the panel saves it as a file: the browser makes
 * the download, so the request stays an ordinary authenticated call. The import takes the file's
 * contents the same way — the panel reads the file it was given — and is asked twice: once with
 * `dry_run` to show what would happen, then for real. Both answers are the same rows.
 */
final class ExchangeController
{
    public function export(Request $request): JsonResponse
    {
        $slugs = $request->query('slugs', []);
        $slugs = is_array($slugs) ? array_values(array_filter($slugs, 'is_string')) : [];

        $packed = Exchange::pack($slugs, $request->boolean('draft'));

        return new JsonResponse([
            'data' => $packed['pack'],
            'skipped' => $packed['skipped'],
            'missing' => $packed['missing'],
        ]);
    }

    public function import(Request $request, Importer $importer): JsonResponse
    {
        $name = $request->input('name');
        $name = is_string($name) && $name !== '' ? basename($name) : 'blocks.json';
        $documents = Exchange::read($request->input('file'), $name);

        if ($documents === null || $documents === []) {
            return $this->refuse((string) __('webx-blocks::exchange.not-a-pack'));
        }

        try {
            $rows = $importer->import($documents, $request->boolean('dry_run'), $request->boolean('publish'));
        } catch (CallCycle $cycle) {
            return $this->refuse((string) __('webx-blocks::exchange.cycle', ['path' => implode(' → ', $cycle->path)]));
        }

        return ApiResponse::data($rows);
    }

    private function refuse(string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'errors' => ['file' => [$message]]], 422);
    }
}
