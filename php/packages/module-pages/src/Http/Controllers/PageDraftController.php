<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\PageForm;

/**
 * Throw away what is waiting and go back to what the site is showing.
 *
 * Offered only while the page is published with a draft on top: a page that was never published
 * has nothing behind its draft. What is dropped is not quite lost — the autosave ring still holds
 * the last few copies — but nothing in the panel lists them, so the question does not promise it.
 */
final class PageDraftController
{
    public function __invoke(Request $request, Page $page, PageForm $form): JsonResponse
    {
        $page->discardDraft();

        $id = $request->user()?->getAuthIdentifier();

        return ApiResponse::data($form->describe($page->refresh(), is_int($id) ? $id : null));
    }
}
