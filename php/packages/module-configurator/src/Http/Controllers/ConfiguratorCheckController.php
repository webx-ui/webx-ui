<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Pricing\Quoter;
use WebxUi\Configurator\Schema\Problem;
use WebxUi\Configurator\Schema\Validator;

/**
 * The two questions the editor asks without writing anything: is this schema one a publication
 * takes, and what does this combination cost. Both behind `configurators.view` — reading a price
 * changes nothing.
 *
 * The editor draws its price with the JS engine as the person types; these are the server's
 * answers, which are the truth (decision 3) — the JSON mode checks a document here before it is
 * applied, and a quote here is what a request from the site will be priced at.
 */
final class ConfiguratorCheckController
{
    public function __construct(private readonly Quoter $quoter) {}

    /**
     * `{ schema, configurator? }` → `{ valid, errors }`. With a configurator, the pictures its
     * site already shows are not looked up again (`ChecksNewValues`).
     */
    public function validate(Request $request): JsonResponse
    {
        $known = [];
        $id = $request->input('configurator');

        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $known = Validator::references(Configurator::withTrashed()->find((int) $id)->schema ?? []);
        }

        $problems = $this->quoter->problems($request->input('schema'), $known);

        return ApiResponse::data([
            'valid' => $problems === [],
            'errors' => array_map(static fn (Problem $problem): array => $problem->toArray(), $problems),
        ]);
    }

    /**
     * `{ values, actions?, draft?, today? }` → the price of §7.4 with `normalized` and `hidden`,
     * or `{ errors }`. The live schema by default, the draft with `draft: true`; a configurator
     * that is not on the site has nothing live to price.
     */
    public function quote(Request $request, Configurator $configurator): JsonResponse
    {
        $values = $request->input('values', []);
        $actions = $request->input('actions', []);
        $today = $request->input('today');

        if (! is_array($values) || ($values !== [] && array_is_list($values))) {
            return $this->invalid('values', '`values` is an object: step key → option value, field key → number.');
        }

        if (! is_array($actions) || ! array_is_list($actions)) {
            return $this->invalid('actions', '`actions` is a list: [{"select": ["glass", "graphite"]}, {"set": ["h", 1900]}].');
        }

        if ($today !== null && (! is_string($today) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $today) !== 1)) {
            return $this->invalid('today', '`today` is a date YYYY-MM-DD.');
        }

        $schema = $request->boolean('draft') ? $configurator->editing()['schema'] : $configurator->liveSchema();

        if ($schema === null) {
            return $this->invalid('draft', (string) __('webx-configurator::configurator.not-live'));
        }

        try {
            return ApiResponse::data($this->quoter->quote($schema, $values, $actions, today: $today));
        } catch (InvalidArgumentException $invalid) {
            return $this->invalid('actions', $invalid->getMessage());
        }
    }

    private function invalid(string $field, string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
