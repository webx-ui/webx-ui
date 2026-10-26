<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Schema;

/**
 * One thing wrong with a schema document: where (`steps.2.options.0.price.perM2`), what (a code
 * of §9) and the words for a person, in the language of the request.
 */
final class Problem
{
    /**
     * @param  array<string, string|int|float>  $params  what the message names
     */
    public function __construct(
        public readonly string $path,
        public readonly string $code,
        public readonly array $params = [],
    ) {}

    public function message(): string
    {
        $key = 'webx-configurator::errors.'.$this->code;

        if (! function_exists('trans')) {
            return $this->code;
        }

        $message = trans($key, $this->params);

        return is_string($message) && $message !== $key ? $message : (Validator::CODES[$this->code] ?? $this->code);
    }

    /**
     * @return array{path: string, code: string, message: string}
     */
    public function toArray(): array
    {
        return ['path' => $this->path, 'code' => $this->code, 'message' => $this->message()];
    }
}
