<?php

declare(strict_types=1);

namespace WebxUi\Audit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Audit\AuditSettings;
use WebxUi\Settings\Settings;

/**
 * The section's settings screen (§8): its values out, and back in checked by the screen's own
 * field types — the way the site's settings are saved, into the same store, under `audit.*`.
 * Reading needs `audit.view`, saving `audit.manage`: an SEO who runs the audit is not thereby
 * someone who changes the site's name.
 */
final class SettingsController
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ScreenRegistry $screens,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::data(['values' => $this->values()]);
    }

    public function update(Request $request, ScreenValues $values): JsonResponse
    {
        $input = $request->input('values');
        $user = $request->user();

        $stored = $values->validate(
            AuditSettings::SCREEN,
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
        );

        $this->settings->save($stored);

        return ApiResponse::data(['values' => $this->values()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function values(): array
    {
        $keys = array_map(static fn (array $node): string => (string) $node['name'], $this->screens->fields(AuditSettings::SCREEN));

        return array_intersect_key($this->settings->raw(), array_flip($keys));
    }
}
