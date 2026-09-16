<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Pages\Http\Resources\PageResource;
use WebxUi\Pages\Models\Page;

/**
 * The same page again, beside the original and not on the site.
 *
 * One page, not the branch under it: copying a whole subtree is deliberately left out (§17),
 * and the useful half of it — "another one like this to start from" — is this.
 *
 * Unpublished, whatever the original is. A copy that appeared on the site the moment it was
 * made would be a second page saying the same thing to a search engine, which is the one thing
 * nobody wants from a duplicate.
 */
final class PageDuplicateController
{
    /** Enough for anyone copying the same page by hand; past that they mean something else. */
    private const ATTEMPTS = 20;

    public function __invoke(Page $page): JsonResponse
    {
        $copy = new Page;

        foreach ($page->getTranslations('title') as $locale => $title) {
            if (is_string($title) && $title !== '') {
                $copy->setTranslation('title', $locale, (string) __('webx-pages::page.copy-of', ['title' => $title], $locale));
            }
        }

        $copy->setAttribute('blocks', $page->blocks);

        $this->name($copy, $page);

        $copy->insertAfter($page);

        return ApiResponse::data(new PageResource($copy->refresh()->loadMissing('routes')), 201);
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
    private function name(Page $copy, Page $page): void
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

        foreach ($page->getTranslations('slug') as $locale => $slug) {
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
}
