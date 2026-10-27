import { describe, expect, it } from 'vitest'
import { parseAffected, plan } from './gate-affected.mjs'

const answer = (fields) =>
  parseAffected(
    Object.entries({
      node: 'none',
      node_filters: '',
      node_tests: '',
      docs: 'false',
      php: 'none',
      php_tests: '',
      manticore: 'false',
      ...fields,
    })
      .map(([key, value]) => `${key}=${value}`)
      .join('\n'),
  )

const names = (steps) => steps.filter((step) => !step.skipped).map((step) => step.name)
const skipped = (steps) => steps.filter((step) => step.skipped).map((step) => step.name)
const find = (steps, name) => steps.find((step) => step.name === name)

describe('gate-affected', () => {
  it('reads the answer of affected.mjs, values with spaces and = included', () => {
    expect(parseAffected('node=some\nnode_filters=--filter a --filter b\nx=a=b\n')).toEqual({
      node: 'some',
      node_filters: '--filter a --filter b',
      x: 'a=b',
    })
  })

  it('runs only lint for a text-only change', () => {
    const steps = plan(answer({}), { base: 'abc', changed: ['docs/a.md'] })
    expect(names(steps)).toEqual(['names: tree', 'names: commits', 'prettier: changed files'])
    expect(skipped(steps)).toEqual([
      'eslint',
      'js: build, typecheck, test',
      'docs: build',
      'php: validate, lint, analyse, test',
      'php: Manticore',
    ])
    expect(find(steps, 'prettier: changed files').args).toContain('docs/a.md')
  })

  it('tests the PHP packages reached and builds the docs, as the catalogue stage did', () => {
    const steps = plan(
      answer({
        node: 'some',
        node_filters: '--filter @webx-ui/docs',
        docs: 'true',
        php: 'some',
        php_tests: 'packages/module-admin/tests packages/module-catalog/tests',
      }),
      {
        base: 'abc',
        changed: ['php/packages/module-catalog/src/A.php', 'apps/docs/guide/a.md'],
        typecheckFilters: ['--filter', '@webx-ui/docs'],
      },
    )
    expect(skipped(steps)).toEqual(['eslint', 'js: test', 'php: Manticore'])
    expect(find(steps, 'js: typecheck').args).toEqual(['--filter', '@webx-ui/docs', 'typecheck'])
    expect(find(steps, 'php: test')).toMatchObject({
      cwd: 'php',
      args: [
        '-d',
        'memory_limit=-1',
        'vendor/bin/phpunit',
        'packages/module-admin/tests',
        'packages/module-catalog/tests',
      ],
    })
    expect(names(steps)).toContain('docs: build')
  })

  it('lints the changed scripts and tests the reached npm packages by path', () => {
    const steps = plan(
      answer({ node: 'some', node_tests: 'packages/module-blog/ packages/module-admin/' }),
      {
        base: 'abc',
        changed: ['packages/module-blog/src/A.vue', 'packages/module-blog/README.md'],
      },
    )
    expect(find(steps, 'eslint: changed files').args).toEqual([
      'exec',
      'eslint',
      '--no-warn-ignored',
      'packages/module-blog/src/A.vue',
    ])
    expect(find(steps, 'js: test').args).toEqual([
      'test',
      'packages/module-blog/',
      'packages/module-admin/',
    ])
  })

  it('runs everything without a base, Manticore on a real server that may not skip', () => {
    const steps = plan(answer({ node: 'all', docs: 'true', php: 'all', manticore: 'true' }), {
      base: null,
      changed: [],
    })
    expect(skipped(steps)).toEqual([])
    expect(names(steps)).not.toContain('names: commits')
    expect(find(steps, 'eslint').args[0]).toBe('lint')
    expect(find(steps, 'prettier').args[0]).toBe('format:check')
    expect(find(steps, 'js: test').args).toEqual(['test'])
    expect(find(steps, 'php: test').args).toEqual(['-d', 'memory_limit=-1', 'vendor/bin/phpunit'])
    expect(find(steps, 'php: Manticore').args).toContain('--fail-on-skipped')
    expect(find(steps, 'php: Manticore').env.MANTICORE_TEST_HOST).toBeTruthy()
  })
})
