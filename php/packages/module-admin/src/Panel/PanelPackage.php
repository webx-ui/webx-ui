<?php

declare(strict_types=1);

namespace WebxUi\Admin\Panel;

/**
 * One installed Composer package and the npm half that goes with it.
 *
 * A module of the panel is two packages installed separately — a Composer one on the server and
 * an npm one in the build — and until now nothing wrote down which belongs to which. Each
 * Composer package now says so itself, under `extra.webx`, and this is that block read back.
 */
final readonly class PanelPackage
{
    /**
     * @param  array<string, string>  $npm  Package name to the range the site should ask for.
     * @param  list<string>  $register  The calls that go into `modules: []`.
     */
    public function __construct(
        public string $name,
        public ?string $module = null,
        public array $npm = [],
        public ?string $import = null,
        public ?string $style = null,
        public array $register = [],
    ) {}

    /**
     * Read the block, taking only what is shaped the way it should be.
     *
     * A package free to declare nothing is the point: `nested-set` and `routing` have no npm
     * half at all, and a package written before this existed has to keep installing.
     *
     * @param  array<array-key, mixed>  $extra
     */
    public static function fromExtra(string $name, array $extra): ?self
    {
        $webx = $extra['webx'] ?? null;

        if (! is_array($webx)) {
            return null;
        }

        $panel = is_array($webx['panel'] ?? null) ? $webx['panel'] : [];

        $register = $panel['register'] ?? [];
        $register = is_array($register) ? $register : [$register];

        return new self(
            name: $name,
            module: is_string($webx['module'] ?? null) ? $webx['module'] : null,
            npm: array_filter(
                is_array($webx['npm'] ?? null) ? $webx['npm'] : [],
                static fn (mixed $range, mixed $package): bool => is_string($package) && is_string($range),
                ARRAY_FILTER_USE_BOTH,
            ),
            import: is_string($panel['import'] ?? null) ? $panel['import'] : null,
            style: is_string($panel['style'] ?? null) ? $panel['style'] : null,
            register: array_values(array_filter($register, is_string(...))),
        );
    }

    /** Whether this package puts anything into the panel's entry file. */
    public function wiresThePanel(): bool
    {
        return $this->import !== null || $this->style !== null || $this->register !== [];
    }

    /**
     * The names the registered calls start with — `seo` for `seo()`, `blog` for `...blog()`.
     *
     * What goes into the entry file is a call the site is free to hand arguments to:
     * `seo({ mediaField: WxMediaField })` is still the SEO module registered. Recognising the
     * name rather than the whole line is what keeps a second run from adding it again.
     *
     * @return list<string>
     */
    public function registeredNames(): array
    {
        $names = [];

        foreach ($this->register as $call) {
            if (preg_match('/([A-Za-z_$][\w$]*)\s*\(/', $call, $matches) === 1) {
                $names[] = $matches[1];
            }
        }

        return $names;
    }

    /** The module specifier an import line reads from, or null when the line is unfamiliar. */
    public function importSpecifier(): ?string
    {
        if ($this->import === null) {
            return null;
        }

        return preg_match('/from\s+[\'"]([^\'"]+)[\'"]/', $this->import, $matches) === 1
            ? $matches[1]
            : null;
    }

    /**
     * The names an import line brings in, for merging with a line that is already there.
     *
     * @return list<string>
     */
    public function importedNames(): array
    {
        if ($this->import === null || preg_match('/\{([^}]*)\}/', $this->import, $matches) !== 1) {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $matches[1]))));
    }
}
