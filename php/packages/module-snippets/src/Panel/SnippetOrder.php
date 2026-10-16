<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Panel;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use WebxUi\Snippets\Models\Snippet;
use WebxUi\Snippets\Output\Codes;
use WebxUi\Snippets\Places;

/**
 * A place in the order it was dragged into (§7.2): the whole place, numbered from zero, the way
 * the banners write a place.
 *
 * A snippet dragged in from another place moves with it — written through the model, so the
 * journal says where it went. The positions of the rest are written past the model events in
 * one go, and the cache is forgotten once, after the transaction.
 */
final class SnippetOrder
{
    public function __construct(
        private readonly Places $places,
        private readonly Codes $codes,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @param  list<int>  $ids
     *
     * @throws ValidationException
     */
    public function reorder(string $place, array $ids): void
    {
        if (! $this->places->has($place)) {
            throw ValidationException::withMessages(['place' => (string) __('webx-snippets::errors.place-undeclared', ['place' => $place])]);
        }

        $ids = array_values(array_unique($ids));
        $snippets = Snippet::query()->whereKey($ids)->get()->keyBy('id');
        $missing = array_values(array_diff($ids, $snippets->keys()->map(intval(...))->all()));

        if ($missing !== []) {
            throw ValidationException::withMessages(['ids' => (string) __('webx-snippets::errors.unknown', ['ids' => implode(', ', $missing)])]);
        }

        $this->db->transaction(static function () use ($place, $ids, $snippets): void {
            foreach ($ids as $position => $id) {
                /** @var Snippet $snippet */
                $snippet = $snippets->get($id);

                if ($snippet->place !== $place) {
                    $snippet->forceFill(['place' => $place])->save();
                }

                Snippet::query()->whereKey($id)->update(['position' => $position]);
            }
        });

        $this->codes->forget();
    }
}
