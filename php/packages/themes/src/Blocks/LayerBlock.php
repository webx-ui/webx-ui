<?php

declare(strict_types=1);

namespace WebxUi\Themes\Blocks;

/**
 * One block type as a layer carries it: a `blocks/<slug>.json` in the format `webx:blocks:export`
 * writes (spec §9.2). `below` is the type of the same slug it overrides, if a lower layer has one.
 */
final readonly class LayerBlock
{
    /**
     * @param  array<string, mixed>  $document
     */
    public function __construct(
        public string $slug,
        public string $layer,
        public string $file,
        public array $document,
        public ?LayerBlock $below = null,
    ) {}

    public function styles(): string
    {
        return is_string($this->document['styles'] ?? null) ? $this->document['styles'] : '';
    }

    public function template(): string
    {
        return is_string($this->document['template'] ?? null) ? $this->document['template'] : '';
    }

    /**
     * @return list<mixed>
     */
    public function schema(): array
    {
        return is_array($this->document['schema'] ?? null) ? array_values($this->document['schema']) : [];
    }

    /** The 1-based line of the file where the `styles` value starts, to turn a line inside it into one of the file. */
    public function stylesLine(): ?int
    {
        return $this->line('styles');
    }

    /** The same for the `template` value. */
    public function templateLine(): ?int
    {
        return $this->line('template');
    }

    private function line(string $key): ?int
    {
        $source = (string) @file_get_contents($this->file);
        $at = strpos($source, '"'.$key.'"');

        return $at === false ? null : substr_count($source, "\n", 0, $at) + 1;
    }
}
