<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Editing\HeldRevision;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorForm;
use WebxUi\Configurator\Panel\ConfiguratorWriter;
use WebxUi\Configurator\Panel\Refused;
use WebxUi\Configurator\Schema\Problem;

/**
 * On the site and off it, and the draft thrown away — the record's state, not its content. Each
 * answers with the editor's whole record: the status, the revision and the problems all move.
 */
final class ConfiguratorPublicationController
{
    public function __construct(
        private readonly ConfiguratorWriter $writer,
        private readonly ConfiguratorForm $form,
    ) {}

    /**
     * Put the draft on the site — refused with 422 and every problem of the schema while it has
     * any (§9), and with 409 when the editor held a revision somebody has written over since.
     */
    public function publish(Request $request, Configurator $configurator): JsonResponse
    {
        $stale = HeldRevision::conflict($request, 'configurators', (string) $configurator->getKey());

        if ($stale !== null) {
            return $stale;
        }

        try {
            $this->writer->publish($configurator, $this->author($request), EntityVersion::SOURCE_PANEL);
        } catch (Refused $refused) {
            if ($refused->problems === []) {
                return ConfiguratorController::refused($refused);
            }

            $message = (string) __('webx-configurator::configurator.publish-refused', ['count' => count($refused->problems)]);

            return new JsonResponse([
                'message' => $message,
                'errors' => ['schema' => [$message]],
                'problems' => array_map(static fn (Problem $problem): array => $problem->toArray(), $refused->problems),
            ], 422);
        }

        return ApiResponse::data($this->form->describe($configurator->refresh()));
    }

    public function unpublish(Request $request, Configurator $configurator): JsonResponse
    {
        $stale = HeldRevision::conflict($request, 'configurators', (string) $configurator->getKey());

        if ($stale !== null) {
            return $stale;
        }

        $configurator->unpublish();

        return ApiResponse::data($this->form->describe($configurator->refresh()));
    }

    /** Back to what the site shows; nothing to throw away is an answer, not an error. */
    public function discard(Configurator $configurator): JsonResponse
    {
        if ($configurator->hasDraft() && ($configurator->isPublished() || $configurator->versions()->published()->exists())) {
            $configurator->discardDraft();
        }

        return ApiResponse::data($this->form->describe($configurator->refresh()));
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
