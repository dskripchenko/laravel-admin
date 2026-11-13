/**
 * The operations on a block tree: placement by the nesting rules and the
 * depth, moves (not into itself, with the index counted before the move),
 * duplicates with fresh refs, removal and its undo, the marks against the
 * published tree.
 */
import { describe, expect, it } from 'vitest'
import {
  acceptedTypes,
  allowedTypes,
  canMove,
  canPlace,
  canRemove,
  cloneWithNewRefs,
  countNodes,
  createNode,
  diffTrees,
  duplicateNode,
  fieldInheritance,
  findNode,
  insertNode,
  isPlacedByRules,
  makeRules,
  moveNode,
  nodeTitle,
  pathTo,
  removeNode,
  renameRefs,
  restoreNode,
  type BlockNode,
  type BlockTypeDef,
} from './tree'

const types: BlockTypeDef[] = [
  { type: 'hero', label: 'Hero', root: true, accepts: [], titleFrom: ['title'], fields: [] },
  { type: 'columns', label: 'Columns', root: true, accepts: ['column'], fields: [] },
  { type: 'column', label: 'Column', accepts: ['text', 'button'], fields: [] },
  { type: 'text', label: 'Text', accepts: ['button'], titleFrom: ['body'], fields: [], defaults: { body: 'Lorem' } },
  { type: 'button', label: 'Button', accepts: [], titleFrom: ['caption'], fields: [] },
]
const rules = makeRules(types, null, 4)

function tree(): BlockNode[] {
  return [
    { ref: 1, type: 'hero', data: { title: 'Welcome' }, children: [] },
    {
      ref: 2,
      type: 'columns',
      data: {},
      children: [
        { ref: 3, type: 'column', data: {}, children: [{ ref: 4, type: 'text', data: { body: '<p>Hi <b>there</b></p>' }, children: [] }] },
        { ref: 5, type: 'column', data: {}, children: [] },
      ],
    },
  ]
}

const refs = (nodes: BlockNode[]): unknown[] => nodes.map((n) => n.ref)

describe('placement rules', () => {
  it('takes the root types from the types marked root, or as given', () => {
    expect(rules.rootTypes).toEqual(['hero', 'columns'])
    expect(makeRules(types, ['text']).rootTypes).toEqual(['text'])
  })

  it('allows a root type at the top, an accepted type inside, nothing unknown', () => {
    expect(canPlace(rules, null, 'hero', 1)).toBe(true)
    expect(canPlace(rules, null, 'text', 1)).toBe(false)
    expect(canPlace(rules, 'columns', 'column', 2)).toBe(true)
    expect(canPlace(rules, 'columns', 'text', 2)).toBe(false)
    expect(canPlace(rules, 'column', 'nope', 3)).toBe(false)
  })

  it('keeps a block with its levels within the depth', () => {
    expect(canPlace(rules, 'text', 'button', 4)).toBe(true)
    expect(canPlace(rules, 'text', 'button', 5)).toBe(false)
    // A text with a button inside takes two levels: at depth 4 it would reach 5.
    expect(canPlace(rules, 'column', 'text', 4, 2)).toBe(false)
  })

  it('filters the palette by the place', () => {
    expect(allowedTypes(rules, null, 1).map((t) => t.type)).toEqual(['hero', 'columns'])
    expect(allowedTypes(rules, 'column', 3).map((t) => t.type)).toEqual(['text', 'button'])
    expect(allowedTypes(rules, 'hero', 2)).toEqual([])
  })

  it('tells a node placed outside the rules', () => {
    const nodes = tree()
    nodes[0].children.push({ ref: 9, type: 'button', data: {}, children: [] })
    expect(isPlacedByRules(rules, nodes, 4)).toBe(true)
    expect(isPlacedByRules(rules, nodes, 9)).toBe(false)
  })
})

describe('insert and move', () => {
  it('inserts where the rules allow and refuses elsewhere', () => {
    const nodes = tree()
    expect(insertNode(nodes, rules, 3, 1, { ref: 'b', type: 'button', data: {}, children: [] })).toBe(true)
    expect(refs(nodes[1].children[0].children)).toEqual([4, 'b'])
    expect(insertNode(nodes, rules, null, 0, { ref: 'x', type: 'button', data: {}, children: [] })).toBe(false)
    expect(insertNode(nodes, rules, 'missing', 0, { ref: 'y', type: 'hero', data: {}, children: [] })).toBe(false)
    expect(countNodes(nodes)).toBe(6)
  })

  it('moves within the rules, counting the index before the move', () => {
    const nodes = tree()
    // The text from the first column into the second.
    expect(moveNode(nodes, rules, 4, 5, 0)).toBe(true)
    expect(refs(nodes[1].children[1].children)).toEqual([4])
    expect(nodes[1].children[0].children).toEqual([])
    // The hero after the columns: index 2 among [hero, columns] before the move.
    expect(moveNode(nodes, rules, 1, null, 2)).toBe(true)
    expect(refs(nodes)).toEqual([2, 1])
  })

  it('refuses a move into itself, into a wrong parent or too deep', () => {
    const nodes = tree()
    expect(canMove(nodes, rules, 2, 3)).toBe(false) // into its own child
    expect(moveNode(nodes, rules, 4, null, 0)).toBe(false) // a text at the top
    expect(moveNode(nodes, rules, 3, 1, 0)).toBe(false) // a column into a hero
    expect(JSON.stringify(nodes)).toBe(JSON.stringify(tree()))

    const withButton = tree()
    withButton[1].children[0].children[0].children.push({ ref: 6, type: 'button', data: {}, children: [] })
    // The text with its button takes two levels: under the second column it reaches depth 4.
    expect(canMove(withButton, rules, 4, 5)).toBe(true)
    expect(canMove(withButton, makeRules(types, null, 3), 4, 5)).toBe(false)
  })
})

describe('duplicate, remove, undo', () => {
  it('duplicates a node with its children under new refs, right after it', () => {
    const nodes = tree()
    let n = 0
    const copy = duplicateNode(nodes, 3, () => `new-${++n}`)!
    expect(refs(nodes[1].children)).toEqual([3, 'new-1', 5])
    expect(copy.ref).toBe('new-1')
    expect(copy.children[0].ref).toBe('new-2')
    expect(copy.children[0].data).toEqual({ body: '<p>Hi <b>there</b></p>' })
    // A deep copy: changing it leaves the original alone.
    copy.children[0].data!.body = 'changed'
    expect(findNode(nodes, 4)!.data!.body).toBe('<p>Hi <b>there</b></p>')
  })

  it('drops the server meta from a copy', () => {
    const copy = cloneWithNewRefs({ ref: 1, type: 'hero', data: null, children: [], meta: { contentBlockId: 7 } }, () => 'n')
    expect(copy).toEqual({ ref: 'n', type: 'hero', data: null, children: [] })
  })

  it('removes a node and puts it back where it stood', () => {
    const nodes = tree()
    const removed = removeNode(nodes, 3)!
    expect(removed).toMatchObject({ parentRef: 2, index: 0 })
    expect(refs(nodes[1].children)).toEqual([5])
    expect(restoreNode(nodes, removed)).toBe(true)
    expect(JSON.stringify(nodes)).toBe(JSON.stringify(tree()))
    expect(removeNode(nodes, 'missing')).toBeNull()
  })

  it('creates a block from the type defaults', () => {
    const node = createNode(types[3], () => 'n1')
    expect(node).toEqual({ ref: 'n1', type: 'text', data: { body: 'Lorem' }, children: [] })
  })
})

describe('captions, path and marks', () => {
  it('takes the caption from titleFrom, without tags, else meta.title', () => {
    expect(nodeTitle(findNode(tree(), 4)!, types[3])).toBe('Hi there')
    expect(nodeTitle({ ref: 1, type: 'columns', data: {}, children: [], meta: { title: 'From server' } }, types[1])).toBe('From server')
    expect(nodeTitle({ ref: 1, type: 'columns', data: {}, children: [] }, types[1])).toBe('')
  })

  it('finds the path from the top', () => {
    expect(refs(pathTo(tree(), 4))).toEqual([2, 3, 4])
    expect(pathTo(tree(), 'missing')).toEqual([])
  })

  it('marks the new, changed and removed blocks against the published tree', () => {
    const base = tree()
    const nodes = tree()
    expect(diffTrees(nodes, base).changes).toBe(0)
    nodes[0].data = { title: 'Hello' } // changed data
    insertNode(nodes, rules, 5, 0, { ref: 'new-1', type: 'text', data: {}, children: [] }) // new
    removeNode(nodes, 4) // removed
    const diff = diffTrees(nodes, base)
    expect(diff.status.get('1')).toBe('changed')
    expect(diff.status.get('new-1')).toBe('new')
    expect(diff.status.has('2')).toBe(false)
    expect(diff.removed).toBe(1)
    expect(diff.changes).toBe(3)
  })

  it('counts the key order of the data as no change, a move as one', () => {
    const base = tree()
    const nodes = tree()
    nodes[0].data = { title: 'Welcome' }
    expect(diffTrees(nodes, base).changes).toBe(0)
    moveNode(nodes, rules, 1, null, 2)
    expect(diffTrees(nodes, base).status.get('1')).toBe('changed')
  })
})

describe('the rules of a node: meta.accepts and meta.locked', () => {
  it('takes the types allowed inside from the node, over its type', () => {
    const column: BlockNode = { ref: 3, type: 'column', data: {}, children: [], meta: { accepts: ['button'] } }
    expect(acceptedTypes(rules, column)).toEqual(['button'])
    expect(allowedTypes(rules, column, 3).map((t) => t.type)).toEqual(['button'])
    expect(canPlace(rules, column, 'text', 3)).toBe(false)
    // [] — nothing inside.
    expect(allowedTypes(rules, { ...column, meta: { accepts: [] } }, 3)).toEqual([])
    // Without meta.accepts — the type's rule, as before.
    expect(allowedTypes(rules, { ...column, meta: null }, 3).map((t) => t.type)).toEqual(['text', 'button'])
  })

  it('refuses an insert or a drop into a node whose meta.accepts leaves the type out', () => {
    const nodes = tree()
    nodes[1].children[1].meta = { accepts: ['button'] }
    expect(insertNode(nodes, rules, 5, 0, { ref: 'x', type: 'text', data: {}, children: [] })).toBe(false)
    expect(canMove(nodes, rules, 4, 5)).toBe(false)
    expect(insertNode(nodes, rules, 5, 0, { ref: 'b', type: 'button', data: {}, children: [] })).toBe(true)
  })

  it('keeps a locked node as it stands: no children added, no move, no removal', () => {
    const nodes = tree()
    nodes[1].children[0].meta = { locked: true } // the first column, with the text in it
    expect(allowedTypes(rules, nodes[1].children[0], 3)).toEqual([])
    expect(insertNode(nodes, rules, 3, 0, { ref: 'b', type: 'button', data: {}, children: [] })).toBe(false)
    expect(canMove(nodes, rules, 3, 2)).toBe(false) // the locked node itself
    expect(canMove(nodes, rules, 4, 5)).toBe(false) // its child, out of it
    expect(canMove(nodes, rules, 4, 3)).toBe(false) // nor reordered in it
    expect(canRemove(nodes, 3)).toBe(false)
    expect(canRemove(nodes, 4)).toBe(false)
    expect(duplicateNode(nodes, 4)).toBeNull()
    // The unlocked parent goes as a whole; the locked node is still placed by the rules.
    expect(canRemove(nodes, 2)).toBe(true)
    expect(isPlacedByRules(rules, nodes, 4)).toBe(true)
  })
})

describe('inherited fields and renamed refs', () => {
  const node = (title: unknown, inherited: boolean): BlockNode => ({
    ref: 1,
    type: 'hero',
    data: { title },
    children: [],
    meta: { fields: { title: { inherited, base: 'Base title' } } },
  })

  it('tells an inherited field from an overridden one by the value against the base', () => {
    expect(fieldInheritance(node('Base title', true), 'title')).toBe('inherited')
    expect(fieldInheritance(node('Mine', true), 'title')).toBe('overridden')
    expect(fieldInheritance(node('Base title', false), 'title')).toBe('overridden')
    expect(fieldInheritance(node('Base title', true), 'other')).toBeNull()
    expect(fieldInheritance({ ref: 1, type: 'hero', data: {}, children: [] }, 'title')).toBeNull()
  })

  it('renames the refs in place, at any depth', () => {
    const nodes = tree()
    nodes[1].children[1].ref = 'new-a'
    nodes[1].children[1].children.push({ ref: 'new-b', type: 'text', data: {}, children: [] })
    const n = renameRefs(nodes, new Map<string, string | number>([['new-a', 50], ['new-b', 51], ['missing', 52]]))
    expect(n).toBe(2)
    expect(refs(nodes[1].children)).toEqual([3, 50])
    expect(refs(nodes[1].children[1].children)).toEqual([51])
  })
})
