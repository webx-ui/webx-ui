<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

use Illuminate\Contracts\View\Factory;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\ComponentTagCompiler;
use InvalidArgumentException;

/**
 * What exists when compiling (COMPOSITIONS §6.1): the modules are known then, so the generated
 * view names a component by a static tag and asks nothing on a request. A node whose view,
 * component or directive is not there is left out, with a finding.
 */
class Probe
{
    private ?ComponentTagCompiler $components = null;

    public function __construct(
        private readonly Factory $views,
        private readonly BladeCompiler $blade,
    ) {}

    public function view(string $name): bool
    {
        return $this->views->exists($name);
    }

    public function component(string $name): bool
    {
        $this->components ??= new ComponentTagCompiler($this->blade->getClassComponentAliases(), $this->blade->getClassComponentNamespaces(), $this->blade);

        try {
            $this->components->componentClass($name);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function directive(string $name): bool
    {
        return array_key_exists($name, $this->blade->getCustomDirectives());
    }
}
