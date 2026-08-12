import type { LayoutNode } from '../render/LayoutRenderer.vue'

/**
 * Подпись статуса для шапки формы ресурса.
 *
 * Шапка печатала СЫРОЕ значение (`active`) рядом с полностью русской формой,
 * хотя select двумя строками ниже показывал «Активен». Подписи приходят в
 * манифесте уже переведёнными (`Localize::options`) — переводить нечего,
 * достаточно в них заглянуть.
 *
 * Живёт отдельным модулем, а не внутри SFC, чтобы проверяться тестом: внутри
 * компонента логика была непроверяемой, так дефект и уехал.
 */

/** Ищет поле по имени в дереве произвольной вложенности (вкладки → строки → поля). */
export function findFieldNode(
  nodes: LayoutNode[],
  name: string,
): Record<string, unknown> | null {
  for (const node of nodes) {
    const n = node as unknown as Record<string, unknown>
    if (n.kind === 'field' && n.name === name) return n

    const items = (n.items ?? n.children) as LayoutNode[] | undefined
    if (Array.isArray(items)) {
      const hit = findFieldNode(items, name)
      if (hit !== null) return hit
    }
  }

  return null
}

/**
 * Формат подписей допускает и карту `value => label`, и список
 * `[{value, label}]` — поддержаны оба, иначе часть ресурсов осталась бы с
 * машинным значением.
 *
 * Без подходящей подписи возвращается само значение: машинное слово в шапке
 * лучше пустоты — по нему хотя бы видно, в каком состоянии запись.
 */
export function resolveStatusLabel(
  nodes: LayoutNode[],
  value: string | null,
): string | null {
  if (value === null) return null

  const options = findFieldNode(nodes, 'status')?.options

  if (Array.isArray(options)) {
    const hit = options.find(
      (o) => String((o as Record<string, unknown>)?.value) === value,
    ) as Record<string, unknown> | undefined
    if (typeof hit?.label === 'string') return hit.label
  } else if (options !== null && typeof options === 'object') {
    const hit = (options as Record<string, unknown>)[value]
    if (typeof hit === 'string') return hit
  }

  return value
}
