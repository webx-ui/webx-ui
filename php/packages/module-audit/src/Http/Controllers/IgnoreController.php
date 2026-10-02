<?php

declare(strict_types=1);

namespace WebxUi\Audit\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Checks\CheckTexts;
use WebxUi\Audit\Runs\AuditIgnore;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\Ignores;

/**
 * The hiding rules (decision 9): the list, a new rule with its reason, and a rule removed —
 * which shows its findings again. `dry_run` on a new rule answers how many findings of the last
 * run it would hide, and hides nothing.
 */
final class IgnoreController
{
    public function __construct(
        private readonly Ignores $ignores,
        private readonly AuditChecks $checks,
    ) {}

    public function index(): JsonResponse
    {
        $hidden = AuditIssue::query()
            ->whereNotNull('ignored_by')
            ->selectRaw('ignored_by, count(*) as total')
            ->groupBy('ignored_by')
            ->pluck('total', 'ignored_by');

        return ApiResponse::data(AuditIgnore::query()->orderByDesc('id')->get()->map(fn (AuditIgnore $rule): array => [
            ...$rule->toPanel(),
            'title' => $this->title($rule->check),
            'hidden' => (int) ($hidden[$rule->id] ?? 0),
        ])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'check' => ['required', 'string', 'max:64'],
            'pattern' => ['nullable', 'string', 'max:2048'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $check = (string) $validated['check'];
        $pattern = trim((string) ($validated['pattern'] ?? ''));

        // The count comes before the reason: the dialog shows it while the reason is being typed.
        if ($request->boolean('dry_run')) {
            return ApiResponse::data(['hidden' => $this->ignores->preview($check, $pattern)]);
        }

        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];
        $rule = $this->ignores->add($check, $pattern, (string) $reason, self::who($request));

        return ApiResponse::data([...$rule->toPanel(), 'title' => $this->title($check)], 201);
    }

    public function destroy(AuditIgnore $ignore): JsonResponse
    {
        $this->ignores->remove($ignore);

        return ApiResponse::data(['id' => $ignore->id]);
    }

    /** Who hid it, the way the history says it: a name rather than an id nobody remembers. */
    public static function who(Request $request): ?string
    {
        $user = $request->user('cms') ?? $request->user();

        if ($user === null) {
            return null;
        }

        $name = $user instanceof Model ? ($user->getAttribute('name') ?? $user->getAttribute('email')) : null;

        return is_string($name) && $name !== '' ? $name : (string) $user->getAuthIdentifier();
    }

    private function title(string $check): string
    {
        $found = $this->checks->get($check);

        return $found === null ? $check : CheckTexts::of($found)['title'];
    }
}
