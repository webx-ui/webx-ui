<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Engine;

/**
 * The area formula of an option (§7.1): numbers, the keys of the size fields, `+ - * /`, a unary
 * minus and brackets — and nothing else.
 *
 * A tokenizer and a recursive descent, never `eval`: the formula is typed by an editor and read
 * on every page of the site, and a parser that only knows five operators cannot be talked into a
 * sixth. The JS copy (`packages/module-configurator/src/engine/`) is the same grammar; the parity
 * cases hold the two together.
 *
 *     $tree = Formula::parse('(w+d)*h/1e6');
 *     Formula::keys($tree);                                   // ['w', 'd', 'h']
 *     Formula::evaluate($tree, ['w' => 900, 'd' => 900, 'h' => 2000]);   // 3.6
 *
 * A tree is a list: `['n', 1.5]` a number, `['k', 'w']` a key, `['neg', $tree]` a minus, and
 * `['+', $left, $right]` (or `-`, `*`, `/`) an operation.
 */
final class Formula
{
    /** Longer than any area anybody writes; a limit so that a pasted novel is refused, not parsed. */
    public const int MAX_LENGTH = 500;

    /** Brackets inside brackets; deeper than this is not a formula a person wrote. */
    private const int MAX_DEPTH = 32;

    /** @var list<array{0: string, 1: string, 2: int}> kind, text, position */
    private array $tokens = [];

    private int $at = 0;

    private int $depth = 0;

    private function __construct(private readonly string $source) {}

    /**
     * @return list<mixed>
     *
     * @throws FormulaError
     */
    public static function parse(string $source): array
    {
        if (strlen($source) > self::MAX_LENGTH) {
            throw new FormulaError(self::MAX_LENGTH, 'longer than '.self::MAX_LENGTH.' characters');
        }

        $parser = new self($source);
        $parser->tokenize();

        if ($parser->tokens === []) {
            throw new FormulaError(0, 'empty');
        }

        $tree = $parser->expression();

        if ($parser->at < count($parser->tokens)) {
            $token = $parser->tokens[$parser->at];

            throw new FormulaError($token[2], "unexpected «{$token[1]}»");
        }

        return $tree;
    }

    /**
     * The keys a formula reads, each once, in the order they first appear.
     *
     * @param  list<mixed>  $tree
     * @return list<string>
     */
    public static function keys(array $tree): array
    {
        return match ($tree[0]) {
            'n' => [],
            'k' => [(string) $tree[1]],
            'neg' => self::keys((array) $tree[1]),
            default => array_values(array_unique([...self::keys((array) $tree[1]), ...self::keys((array) $tree[2])])),
        };
    }

    /**
     * The value of a formula. A key with no value — a hidden field — is 0; dividing by 0 gives 0,
     * not infinity: the visitor typed something odd, the page does not break (§7.1).
     *
     * @param  list<mixed>  $tree
     * @param  array<string, float|int>  $values
     */
    public static function evaluate(array $tree, array $values): float
    {
        switch ($tree[0]) {
            case 'n':
                return (float) $tree[1];
            case 'k':
                return (float) ($values[(string) $tree[1]] ?? 0);
            case 'neg':
                return -self::evaluate((array) $tree[1], $values);
        }

        $left = self::evaluate((array) $tree[1], $values);
        $right = self::evaluate((array) $tree[2], $values);

        return match ($tree[0]) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            default => $right == 0.0 ? 0.0 : $left / $right,
        };
    }

    private function tokenize(): void
    {
        $length = strlen($this->source);
        $at = 0;

        while ($at < $length) {
            $char = $this->source[$at];

            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                $at++;

                continue;
            }

            if (str_contains('+-*/()', $char)) {
                $this->tokens[] = ['op', $char, $at];
                $at++;

                continue;
            }

            if (preg_match('/\G(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/', $this->source, $match, 0, $at) === 1) {
                $this->tokens[] = ['n', $match[0], $at];
                $at += strlen($match[0]);

                continue;
            }

            if (preg_match('/\G[a-z_][a-z0-9_]*/', $this->source, $match, 0, $at) === 1) {
                $this->tokens[] = ['k', $match[0], $at];
                $at += strlen($match[0]);

                continue;
            }

            throw new FormulaError($at, 'unexpected «'.mb_substr(substr($this->source, $at), 0, 1).'»');
        }
    }

    /** @return list<mixed> */
    private function expression(): array
    {
        $tree = $this->term();

        while ($this->peek('+') || $this->peek('-')) {
            $operator = $this->tokens[$this->at++][1];
            $tree = [$operator, $tree, $this->term()];
        }

        return $tree;
    }

    /** @return list<mixed> */
    private function term(): array
    {
        $tree = $this->factor();

        while ($this->peek('*') || $this->peek('/')) {
            $operator = $this->tokens[$this->at++][1];
            $tree = [$operator, $tree, $this->factor()];
        }

        return $tree;
    }

    /** @return list<mixed> */
    private function factor(): array
    {
        $token = $this->tokens[$this->at] ?? null;

        if ($token === null) {
            throw new FormulaError(strlen($this->source), 'ends too early');
        }

        if (++$this->depth > self::MAX_DEPTH) {
            throw new FormulaError($token[2], 'nested too deep');
        }

        try {
            if ($token[0] === 'n') {
                $this->at++;

                return ['n', (float) $token[1]];
            }

            if ($token[0] === 'k') {
                $this->at++;

                return ['k', $token[1]];
            }

            if ($token[1] === '-') {
                $this->at++;

                return ['neg', $this->factor()];
            }

            if ($token[1] === '(') {
                $this->at++;
                $tree = $this->expression();

                if (! $this->peek(')')) {
                    $next = $this->tokens[$this->at] ?? null;

                    throw new FormulaError($next[2] ?? strlen($this->source), 'a bracket is not closed');
                }

                $this->at++;

                return $tree;
            }

            throw new FormulaError($token[2], "unexpected «{$token[1]}»");
        } finally {
            $this->depth--;
        }
    }

    private function peek(string $operator): bool
    {
        $token = $this->tokens[$this->at] ?? null;

        return $token !== null && $token[0] === 'op' && $token[1] === $operator;
    }
}
