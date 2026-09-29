<?php

declare(strict_types=1);

namespace WebxUi\Admin\History;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * The kinds of record the journal is kept for, filled from service providers (§5).
 *
 * The same register the notes have (`NoteTypes`), and for the same reason: the journal's
 * address takes a type out of the URL, and the register is the white list of what may be named
 * there. It is also where the labels and the permission live, so that neither the endpoint nor
 * the agent has to guess either.
 *
 *     $types->register('catalog.product', Product::class,
 *         fields: ['price' => 'webx-catalog::product.price', 'name' => 'webx-catalog::product.name'],
 *         permission: ['catalog.view', 'catalog.manage']);
 *
 * A type without a model is allowed: a run over something that is not one table, or a record
 * the module writes with `History::record()` by hand.
 */
final class HistoryTypes
{
    /** @var array<string, HistoryType> */
    private array $types = [];

    /** @var array<class-string<Model>, string> */
    private array $models = [];

    /**
     * @param  (Closure(): void)|null  $first  what the frame does once there is a first type to
     *                                         keep a journal of: offer an agent the tools to read it
     */
    public function __construct(private ?Closure $first = null) {}

    /**
     * @param  class-string<Model>|null  $model
     * @param  array<string, string>  $fields  field → label (a translation key or words)
     * @param  string|list<string>  $permission  the permission, or several of which any will do
     * @param  string|null  $module  the module the type belongs to; what stands before the dot by default
     */
    public function register(
        string $type,
        ?string $model = null,
        array $fields = [],
        string|array $permission = [],
        ?string $module = null,
        ?string $label = null,
    ): HistoryType {
        if (preg_match('/^[a-z0-9_-]+(\.[a-z0-9_-]+)*$/', $type) !== 1 || strlen($type) > 64) {
            throw new InvalidArgumentException("[{$type}] is not a history type: lowercase words joined by dots, at most 64 characters.");
        }

        $permissions = array_values(array_filter((array) $permission, static fn (string $one): bool => trim($one) !== ''));

        // A type nobody is allowed to read is a journal nobody sees, and one anybody may read is
        // a leak; neither is what a forgotten argument should mean.
        if ($permissions === []) {
            throw new InvalidArgumentException("The history type [{$type}] needs the permission its records are read behind.");
        }

        $entry = new HistoryType(
            $type,
            $model,
            $fields,
            $permissions,
            $module ?? explode('.', $type)[0],
            $label,
        );

        $this->types[$type] = $entry;

        if ($this->first !== null) {
            $first = $this->first;
            $this->first = null;
            $first();
        }

        if ($model !== null) {
            $this->models[$model] = $type;
        }

        return $entry;
    }

    public function find(string $type): ?HistoryType
    {
        return $this->types[$type] ?? null;
    }

    /**
     * The type a model's rows are written under.
     *
     * Loud when there is none: a model that records history and was never registered would
     * otherwise write rows nobody can read, and that is found out a year later.
     */
    public function of(Model $model): HistoryType
    {
        $type = $this->models[$model::class] ?? null;

        if ($type === null) {
            throw new LogicException(sprintf(
                '%s records history but is not a registered history type; register it with HistoryTypes::register().',
                $model::class,
            ));
        }

        return $this->types[$type];
    }

    /** @return array<string, HistoryType> */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * The types this reader may see.
     *
     * @return array<string, HistoryType>
     */
    public function readable(mixed $reader): array
    {
        return array_filter($this->types, static fn (HistoryType $type): bool => $type->allows($reader));
    }

    public function forget(): void
    {
        $this->types = [];
        $this->models = [];
    }
}
