<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Panel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use WebxUi\Snippets\Models\Snippet;
use WebxUi\Snippets\Places;
use WebxUi\Snippets\SnippetValues;

/**
 * The editor's screen on the server side: what its fields hold, and what a save writes (§7.3).
 *
 * No draft (decision 4): a save is what the site prints, at once. What the answer adds to the
 * record is `warnings` — the tags of a whole document in the code, which do not stop the save.
 */
final class SnippetForm
{
    public function __construct(
        private readonly SnippetValues $values,
        private readonly Places $places,
        private readonly ConnectionInterface $db,
        private readonly Application $app,
        private readonly Config $config,
    ) {}

    /**
     * A snippet, the values of its screen, and what to look at in its code.
     *
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    public function describe(Snippet $snippet, array $warnings = []): array
    {
        return [
            'snippet' => $this->row($snippet),
            'values' => $this->valuesOf($snippet),
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function valuesOf(Snippet $snippet): array
    {
        return [
            'name' => $snippet->name,
            'place' => $snippet->place,
            'code' => $snippet->code,
            'consent' => $snippet->consent,
            'only_paths' => $snippet->only_paths ?? [],
            'except_paths' => $snippet->except_paths ?? [],
            'locales' => $snippet->locales ?? [],
            'on_stage' => $snippet->on_stage,
            'enabled' => $snippet->enabled,
        ];
    }

    /**
     * One snippet as a row of the list and the head of its editor.
     *
     * @return array<string, mixed>
     */
    public function row(Snippet $snippet): array
    {
        return [
            'id' => (int) $snippet->id,
            'name' => $snippet->name,
            'place' => $snippet->place,
            'place_declared' => $this->places->has($snippet->place),
            'consent' => $snippet->consent,
            'only_paths' => $snippet->only_paths ?? [],
            'except_paths' => $snippet->except_paths ?? [],
            'locales' => $snippet->locales ?? [],
            'on_stage' => $snippet->on_stage,
            'enabled' => $snippet->enabled,
            // Switched on and still not printed: this copy prints only the codes marked "on dev too" (§5.5).
            'silent_here' => $snippet->enabled && ! $snippet->on_stage && ! $this->printsEverything(),
            'position' => $snippet->position,
            'bytes' => strlen($snippet->code),
            'updated_at' => $snippet->updated_at?->toAtomString(),
            'deleted_at' => $snippet->deleted_at?->toAtomString(),
        ];
    }

    private function printsEverything(): bool
    {
        $environments = array_values(array_filter((array) $this->config->get('webx-snippets.environments', ['production']), is_string(...)));

        return $environments !== [] && $this->app->environment($environments);
    }

    /**
     * Check what came in and write it — a new snippet or an existing one.
     *
     * @param  array<string, mixed>  $input
     * @return array{snippet: Snippet, warnings: list<string>}
     *
     * @throws ValidationException
     */
    public function save(Snippet $snippet, array $input): array
    {
        $values = $this->values->check(array_intersect_key($input, array_flip(SnippetValues::FIELDS)), $snippet);

        $snippet = $this->db->transaction(static function () use ($snippet, $values): Snippet {
            $snippet->fill($values)->save();

            return $snippet->refresh();
        });

        return ['snippet' => $snippet, 'warnings' => $this->values->warnings($snippet->code)];
    }
}
