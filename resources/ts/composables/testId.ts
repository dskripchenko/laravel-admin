/**
 * The `data-testid` of the panel's elements, for end-to-end tests: stable
 * names made of names and keys (a field's name, a record's key, an action's
 * name, a block's ref), never of the interface's text.
 *
 * A part is put into kebab-case: `firstName`, `first_name`, `user.email` →
 * `first-name`, `first-name`, `user-email`; parts are joined with `-`, empty
 * ones are left out:
 *
 *     testIdOf('field', 'firstName')       // 'field-first-name'
 *     testIdOf('resource-row', 15)         // 'resource-row-15'
 *     testIdOf('block-tree-node', 'new-1a2b') // 'block-tree-node-new-1a2b'
 *
 * The same rule is easy to repeat in a test: see docs/en/testing.md, "data-testid for end-to-end tests".
 */

/** One part of a test id: kebab-case, letters and digits only. */
export function testIdPart(value: unknown): string {
  if (value === null || value === undefined) return ''
  return String(value)
    .replace(/([\p{Ll}\p{N}])(\p{Lu})/gu, '$1-$2')
    .replace(/(\p{Lu}+)(\p{Lu}\p{Ll})/gu, '$1-$2')
    .toLowerCase()
    .replace(/[^\p{L}\p{N}]+/gu, '-')
    .replace(/^-+|-+$/g, '')
}

/** The parts in kebab-case, joined with `-`; null, undefined and empty parts are skipped. */
export function testIdOf(...parts: unknown[]): string {
  return parts.map(testIdPart).filter((p) => p !== '').join('-')
}
