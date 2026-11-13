/**
 * What the rows of the block tree share: the editor, the rules, the folded
 * nodes, the drag in progress and the palette's opener. BlockTreeLayout
 * provides it; BlockTreeNode reads it at any depth.
 */
import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { BlockTreeEditor } from './useBlockTreeEditor'
import type { BlockRef, TreeRules } from './tree'

export interface PaletteTarget {
  parentRef: BlockRef | null
  index: number
}

export interface BlockTreeContext {
  editor: BlockTreeEditor
  rules: () => TreeRules
  collapsed: Set<string>
  toggle: (ref: BlockRef) => void
  dragging: Ref<BlockRef | null>
  openPalette: (target: PaletteTarget) => void
}

const Key: InjectionKey<BlockTreeContext> = Symbol('admin.block-tree')

export function provideBlockTree(ctx: BlockTreeContext): void {
  provide(Key, ctx)
}

export function useBlockTree(): BlockTreeContext {
  const ctx = inject(Key)
  if (!ctx) throw new Error('useBlockTree() outside of a block tree')
  return ctx
}
