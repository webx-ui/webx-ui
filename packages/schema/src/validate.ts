import type { Patch, ScreenError, ScreenNode, VisibilityCondition } from './types'

const NODE_KEYS = new Set([
  'id',
  'type',
  'name',
  'label',
  'help',
  'localized',
  'props',
  'children',
  'slot',
  'visible',
  'can',
])

const OPS = new Set(['add', 'remove', 'replace', 'move', 'set'])

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function checkCondition(value: unknown, path: string, errors: ScreenError[]): void {
  if (!isRecord(value)) {
    errors.push({ path, message: 'visible must be a boolean or a condition object' })
    return
  }
  if ('all' in value || 'any' in value) {
    const list = (value.all ?? value.any) as unknown
    if (!Array.isArray(list)) {
      errors.push({ path, message: '"all" / "any" must be an array of conditions' })
      return
    }
    list.forEach((item, index) => checkCondition(item, `${path}[${index}]`, errors))
    return
  }
  if (typeof value.when !== 'string') {
    errors.push({ path, message: 'a condition needs "when": the name of a field' })
    return
  }
  const forms = ['is', 'in', 'not'].filter((key) => key in value)
  if (forms.length !== 1) {
    errors.push({ path, message: 'a condition needs exactly one of "is", "in", "not"' })
  } else if (forms[0] === 'in' && !Array.isArray(value.in)) {
    errors.push({ path, message: '"in" must be an array' })
  }
}

function checkNode(value: unknown, path: string, errors: ScreenError[], seen: Set<string>): void {
  if (!isRecord(value)) {
    errors.push({ path, message: 'a node must be an object' })
    return
  }

  for (const key of Object.keys(value)) {
    if (!NODE_KEYS.has(key)) errors.push({ path, message: `unknown key "${key}"` })
  }

  if (typeof value.id !== 'string' || value.id === '') {
    errors.push({ path, message: '"id" is required and must be a non-empty string' })
  } else if (seen.has(value.id)) {
    errors.push({ path, message: `duplicate id "${value.id}"` })
  } else {
    seen.add(value.id)
  }

  if (typeof value.type !== 'string' || value.type === '') {
    errors.push({ path, message: '"type" is required and must be a non-empty string' })
  }

  for (const key of ['name', 'label', 'help'] as const) {
    if (key in value && typeof value[key] !== 'string') {
      errors.push({ path, message: `"${key}" must be a string` })
    }
  }
  if ('localized' in value && typeof value.localized !== 'boolean') {
    errors.push({ path, message: '"localized" must be a boolean' })
  }
  if ('props' in value && !isRecord(value.props)) {
    errors.push({ path, message: '"props" must be an object' })
  }
  if ('slot' in value && value.slot !== null && typeof value.slot !== 'string') {
    errors.push({ path, message: '"slot" must be a string or null' })
  }
  if ('can' in value && value.can !== null && typeof value.can !== 'string') {
    errors.push({ path, message: '"can" must be a string or null' })
  }
  if ('visible' in value && typeof value.visible !== 'boolean') {
    checkCondition(value.visible as VisibilityCondition, `${path}.visible`, errors)
  }

  if ('children' in value) {
    if (!Array.isArray(value.children)) {
      errors.push({ path, message: '"children" must be an array' })
    } else {
      value.children.forEach((child, index) =>
        checkNode(child, `${path}.children[${index}]`, errors, seen),
      )
    }
  }
}

/**
 * Checks a tree the way the JSON schema would, without a schema library: required
 * keys, closed key set, value types, and — what a schema cannot say — unique ids.
 * An empty list means the tree is sound.
 */
export function validateScreen(root: unknown): ScreenError[] {
  const errors: ScreenError[] = []
  if (!Array.isArray(root)) return [{ path: 'root', message: 'root must be an array of nodes' }]
  const seen = new Set<string>()
  root.forEach((node, index) => checkNode(node, `root[${index}]`, errors, seen))
  return errors
}

/** Same for a patch: each operation has the keys its `op` calls for. */
export function validatePatch(patch: unknown): ScreenError[] {
  const errors: ScreenError[] = []
  if (!Array.isArray(patch)) return [{ path: 'patch', message: 'a patch must be an array' }]

  patch.forEach((op, index) => {
    const path = `patch[${index}]`
    if (!isRecord(op)) {
      errors.push({ path, message: 'an operation must be an object' })
      return
    }
    if (typeof op.op !== 'string' || !OPS.has(op.op)) {
      errors.push({ path, message: '"op" must be one of add, remove, replace, move, set' })
      return
    }
    if (typeof op.target !== 'string' || op.target === '') {
      errors.push({ path, message: '"target" is required: the id of a node' })
    }
    if ((op.op === 'add' || op.op === 'replace') && !isRecord(op.node)) {
      errors.push({ path, message: `"${op.op}" needs a "node"` })
    } else if (op.op === 'add' || op.op === 'replace') {
      checkNode(op.node, `${path}.node`, errors, new Set())
    }
    if ('position' in op && !isPosition(op.position)) {
      errors.push({ path, message: '"position" must be first, last, before:<id> or after:<id>' })
    }
    if (op.op === 'move' && 'to' in op && typeof op.to !== 'string') {
      errors.push({ path, message: '"to" must be the id of the new parent' })
    }
    if (op.op === 'set') {
      for (const key of Object.keys(op)) {
        if (key !== 'op' && key !== 'target' && (!NODE_KEYS.has(key) || key === 'id')) {
          errors.push({ path, message: `"set" cannot change "${key}"` })
        }
      }
    }
  })

  return errors
}

function isPosition(value: unknown): boolean {
  return (
    typeof value === 'string' &&
    (value === 'first' ||
      value === 'last' ||
      value.startsWith('before:') ||
      value.startsWith('after:'))
  )
}

export type { Patch, ScreenNode }
