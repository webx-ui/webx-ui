<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Seo\Faq\FaqExport;
use WebxUi\Seo\Faq\FaqImport;
use WebxUi\Seo\Faq\FaqSpreadsheet;

/**
 * Page FAQs in bulk (§18.5): the import with its preview and the export in the same flat format.
 * One page's questions are edited with its rule, on `/seo/urls/{id}`.
 *
 * Registered only while `webx-seo.faq.enabled` is on — otherwise both are a 404.
 */
final class SeoFaqController
{
    /** A preview unless `dry_run` is false: the file is read and checked either way. */
    public function import(Request $request, FaqImport $import): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:csv,txt,xlsx'],
            'mode' => ['nullable', Rule::in([FaqImport::REPLACE, FaqImport::APPEND])],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());

        try {
            $rows = FaqSpreadsheet::read((string) $file?->getRealPath(), $extension);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('webx-seo::faq.unreadable')]);
        }

        return ApiResponse::data($import->run(
            $rows,
            (string) $request->input('mode', FaqImport::REPLACE),
            $request->boolean('dry_run', true),
        ));
    }

    public function export(Request $request, FaqExport $export): BinaryFileResponse
    {
        $request->validate(['format' => ['nullable', Rule::in(['csv', 'xlsx'])]]);

        $format = (string) $request->input('format', 'csv');
        $path = tempnam(sys_get_temp_dir(), 'wxf').'.'.$format;

        $export->write($path, $format);

        return response()->download($path, 'faq.'.$format)->deleteFileAfterSend();
    }
}
