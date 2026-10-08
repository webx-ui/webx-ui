<?php

declare(strict_types=1);

namespace WebxUi\Admin\Editing;

use Illuminate\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A publication — or any other change of state from an editor — that carries the revision the
 * editor held.
 *
 * Publishing puts on the site whatever the draft holds now, and that may be an edit the person
 * pressing the button never saw: a colleague's, or an agent's, waiting in a notice they did not
 * pull in. The editor asks first and shows whose it is; this is the server's half, for the
 * moment between that question and the click: a revision that is no longer the draft's is
 * answered 409 with who changed it, and nothing is published.
 *
 * A request without a revision goes through, as a save without one does: a script, a list's
 * row menu, an editor of an older panel.
 */
final class HeldRevision
{
    public static function conflict(Request $request, string $entity, string $id): ?JsonResponse
    {
        $sent = $request->input('revision');

        if (! is_string($sent) || $sent === '') {
            return null;
        }

        $found = Container::getInstance()->make(EditedRecords::class)->find($entity, $id);

        if ($found === null || $found['revision'] === $sent) {
            return null;
        }

        $changed = LastChange::of($found['model'], $request->user());

        return new JsonResponse([
            'message' => (string) __('webx-admin::editing.stale-publish', [
                'name' => $changed['author'] ?? __('webx-admin::editing.somebody'),
            ]),
            'revision' => $found['revision'],
            'changed' => $changed,
        ], 409);
    }
}
