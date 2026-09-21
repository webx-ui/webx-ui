<?php

declare(strict_types=1);

namespace WebxUi\Blog\Mcp;

use WebxUi\Blog\Models\Rubric;
use WebxUi\Mcp\Tool;
use WebxUi\Routing\Models\Route;

/**
 * The sections of the blog, for an agent that is about to file something (§13).
 *
 * One tool and it only looks. Rubrics are a menu — a site has eight of them and they are edited
 * once a quarter — so what an agent needs is to know which ones exist before it writes an
 * article, not a way to invent a ninth while doing it. Making and renaming them is a decision
 * about the navigation of the site, and that is a person's, on the screen built for it.
 *
 * Filing an article *into* a rubric is `articles_update`, because that is what it is: a value of
 * the article's own screen, checked where every screen is checked.
 */
final class RubricTools
{
    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        return [
            Tool::read(
                'list',
                'The rubrics of this blog, in the order they sit in the menu: what each is called in every '
                .'language, the address it answers at, whether it is visible on the site, and how many articles '
                .'are in it. The first rubric of an article is its main one — the one in the breadcrumbs and in '
                .'the RSS — so the order they are given in articles_update matters. Read this before filing '
                .'anything: an article goes into the rubrics that exist, and there is no tool here that makes '
                .'a new one.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'visible' => ['type' => 'boolean', 'description' => 'Only the ones a reader can reach. All of them when omitted.'],
                ]],
                permission: ['blog.articles.view', 'blog.articles.manage', 'blog.taxonomy.manage'],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = Rubric::query()->withCount('articles')->with('routes')->inMenuOrder();

        if (($arguments['visible'] ?? null) === true) {
            $query->visible();
        }

        $rubrics = $query->get();

        return [
            'count' => $rubrics->count(),
            'rubrics' => $rubrics->map(fn (Rubric $rubric): array => $this->row($rubric))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Rubric $rubric): array
    {
        $urls = [];

        foreach ($rubric->routes as $route) {
            if ($route->kind === Route::CANONICAL) {
                $urls[$route->locale] = '/'.$route->path;
            }
        }

        return [
            'id' => (int) $rubric->getKey(),
            'title' => $rubric->getTranslations('title'),
            'slug' => $rubric->getTranslations('slug'),
            // A language missing here is a language the rubric names no slug in, and therefore
            // has no address in (§9). A real state rather than something to paper over.
            'paths' => $urls,
            'is_visible' => (bool) $rubric->is_visible,
            'position' => (int) $rubric->position,
            // What makes a rubric refuse to be deleted, and the number the refusal names (§6).
            'articles_count' => (int) ($rubric->getAttribute('articles_count') ?? 0),
        ];
    }
}
