<?php

declare(strict_types=1);

namespace WebxUi\Admin\SiteModules;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Every name a site module goes by, made from the one a person typed (SITE_MODULES §5).
 *
 * What the site, the panel, the database and an agent see is named as it would be in the
 * monorepo — the id, the tables, the permissions, `webx-<id>`; only the three names of the
 * packaging differ: `site/module-<id>`, `Site\<Studly>\` and `@site/module-<id>`.
 */
final readonly class ModuleName
{
    /**
     * Words PHP will not take as the name of a class.
     *
     * @var list<string>
     */
    private const RESERVED = [
        'abstract', 'array', 'bool', 'callable', 'case', 'class', 'clone', 'default', 'echo', 'enum',
        'false', 'float', 'function', 'global', 'include', 'int', 'interface', 'iterable', 'list',
        'match', 'mixed', 'namespace', 'never', 'new', 'null', 'object', 'parent', 'print', 'require',
        'self', 'static', 'string', 'switch', 'trait', 'true', 'use', 'void',
    ];

    private function __construct(
        /** `offices`: the id of the section, of its permissions and of `webx-offices`. */
        public string $id,
        /** `Offices`: the namespace `Site\Offices\` and the classes of the module. */
        public string $studly,
        /** `Office`: the one record. */
        public string $model,
    ) {}

    /**
     * @throws InvalidArgumentException A name that would not make an id, a namespace and a class.
     */
    public static function from(string $given): self
    {
        $given = trim($given);

        if (preg_match('/^[A-Za-z][A-Za-z0-9-]*$/', $given) !== 1) {
            throw new InvalidArgumentException("[{$given}] is not a name for a module: Latin letters, digits and hyphens, a letter first — offices, office-hours.");
        }

        $id = Str::kebab(Str::studly($given));

        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $id) !== 1) {
            throw new InvalidArgumentException("[{$given}] does not make an id: offices, office-hours.");
        }

        $studly = Str::studly($id);
        $model = Str::studly(Str::singular($id));

        foreach ([$studly, $model] as $class) {
            if (in_array(strtolower($class), self::RESERVED, true)) {
                throw new InvalidArgumentException("[{$given}] makes the class [{$class}], a word PHP keeps for itself. Pick another name.");
            }
        }

        return new self($id, $studly, $model);
    }

    /** `site/module-offices`. */
    public function package(): string
    {
        return 'site/module-'.$this->id;
    }

    /** `@site/module-offices`. */
    public function npm(): string
    {
        return '@site/module-'.$this->id;
    }

    /** `offices`, `office_hours`: the table, and the prefix of the MCP tools. */
    public function table(): string
    {
        return str_replace('-', '_', $this->id);
    }

    /** `modules/offices`, from the root of the site. */
    public function folder(): string
    {
        return 'modules/'.$this->id;
    }

    /** `Site\Offices\`. */
    public function namespace(): string
    {
        return 'Site\\'.$this->studly.'\\';
    }

    /**
     * What a stub's `{{ … }}` stands for.
     *
     * @return array<string, string>
     */
    public function replacements(): array
    {
        return [
            '{{ id }}' => $this->id,
            '{{ Studly }}' => $this->studly,
            '{{ Model }}' => $this->model,
            '{{ model }}' => Str::lower(Str::headline($this->model)),
            '{{ models }}' => Str::lower(Str::headline($this->studly)),
            '{{ Title }}' => Str::headline($this->studly),
            '{{ table }}' => $this->table(),
            '{{ tool }}' => $this->table(),
            '{{ function }}' => Str::camel($this->id),
            '{{ CONST }}' => Str::upper($this->table()),
        ];
    }
}
