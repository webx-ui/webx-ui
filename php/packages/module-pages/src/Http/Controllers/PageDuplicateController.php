<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Pages\Http\Resources\PageResource;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\Editors;
use WebxUi\Pages\Panel\PageForm;

/**
 * The same page again, beside the original and not on the site.
 *
 * One page, not the branch under it: copying a whole subtree is deliberately left out (§17),
 * and the useful half of it — "another one like this to start from" — is this.
 *
 * Unpublished, whatever the original is. A copy that appeared on the site the moment it was
 * made would be a second page saying the same thing to a search engine, which is the one thing
 * nobody wants from a duplicate.
 *
 * What is copied is what the editor of the original sees — its draft laid over its columns —
 * not the published columns alone: a page never published keeps its content only in the draft
 * and copied empty, and one with edits waiting copied what the site showed before them. The copy
 * starts life as a draft written by whoever pressed the button, so the list says who made it.
 *
 * The SEO card stays behind on purpose: a canonical address or a description copied as it is
 * would make the copy claim to be the original.
 */
final class PageDuplicateController
{
    /** Enough for anyone copying the same page by hand; past that they mean something else. */
    private const ATTEMPTS = 20;

    public function __invoke(Request $request, Page $page, PageForm $form): JsonResponse
    {
        $shown = $page->hasDraft() ? $page->withDraft() : $page;
        $copy = new Page;

        foreach ($shown->getTranslations('title') as $locale => $title) {
            if (is_string($title) && $title !== '') {
                $copy->setTranslation('title', $locale, (string) __('webx-pages::page.copy-of', ['title' => $title], $locale));
            }
        }

        $copy->setAttribute('blocks', $shown->blocks);

        $this->name($copy, $shown, $page);

        $copy->getConnection()->transaction(function () use ($copy, $page, $form, $request): void {
            $copy->insertAfter($page);

            // Through the editor's own save with nothing changed: the draft is the page's values,
            // and the autosave it writes carries the author.
            $form->save($copy->refresh(), [], null, $this->author($request));
        });

        $copy->refresh()->loadMissing('routes');

        return ApiResponse::data(new PageResource($copy, Editors::of([$copy])), 201);
    }

    /**
     * A free address for the copy, in every language the original has one in.
     *
     * Asked of the siblings rather than of the registry: the address of a page is its
     * ancestors' slugs and then its own, so a slug no brother or sister uses is an address
     * nobody in this branch has. Another kind of entity may still be standing on it, and then
     * the registry refuses the save with a 422 — which is the policy of the type (§5) and not
     * something to work around here.
     */
    private function name(Page $copy, Page $shown, Page $page): void
    {
        /** @var list<string> $taken */
        $taken = [];

        foreach ($page->siblings()->get() as $sibling) {
            foreach ($sibling->getTranslations('slug') as $slug) {
                if (is_string($slug)) {
                    $taken[] = $slug;
                }
            }
        }

        foreach ($shown->getTranslations('slug') as $locale => $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            $copy->setTranslation('slug', $locale, $this->free($slug, $taken));
        }
    }

    /**
     * @param  list<string>  $taken
     */
    private function free(string $slug, array $taken): string
    {
        $candidate = $slug.'-copy';

        for ($attempt = 2; in_array($candidate, $taken, true) && $attempt <= self::ATTEMPTS; $attempt++) {
            $candidate = $slug.'-copy-'.$attempt;
        }

        return $candidate;
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
