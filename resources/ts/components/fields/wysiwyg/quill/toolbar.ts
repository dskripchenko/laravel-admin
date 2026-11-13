/**
 * The toolbar of the Quill driver: Orchid's groups and palette, so a field
 * moved over from Orchid's Field\Quill gets the same buttons and the same
 * colours (and so the same `style="color: …"` in its HTML).
 */

/** Orchid's palette for the colour and background pickers; 'custom-color' asks for a value. */
export const COLORS = [
  '#000000', '#e60000', '#ff9900', '#ffff00', '#008a00', '#0066cc',
  '#9933ff', '#ffffff', '#facccc', '#ffebcc', '#ffffcc', '#cce8cc',
  '#cce0f5', '#ebd6ff', '#bbbbbb', '#f06666', '#ffc266', '#ffff66',
  '#66b966', '#66a3e0', '#c285ff', '#888888', '#a10000', '#b26b00',
  '#b2b200', '#006100', '#0047b2', '#6b24b2', '#444444', '#5c0000',
  '#663d00', '#666600', '#003700', '#002966', '#3d1466', 'custom-color',
]

/** Orchid's toolbar groups, by name. */
export const QUILL_TOOLBAR_GROUPS: Record<string, unknown[]> = {
  text: ['bold', 'italic', 'underline', 'strike', 'link', 'clean'],
  quote: ['blockquote', 'code-block'],
  color: [{ color: COLORS }, { background: COLORS }],
  header: [{ header: '1' }, { header: '2' }],
  list: [{ list: 'ordered' }, { list: 'bullet' }],
  format: [{ indent: '-1' }, { indent: '+1' }, { align: [] }],
  media: ['image', 'video'],
}

/** Orchid's default: Field\Quill without ->toolbar(). */
const DEFAULT_GROUPS = ['text', 'color', 'quote', 'header', 'list', 'format', 'media']

/** The toolbar container: Orchid group names are expanded, anything else is Quill's own. */
export function quillToolbar(toolbar: unknown): unknown[] {
  if (!Array.isArray(toolbar) || toolbar.length === 0) return DEFAULT_GROUPS.map(g => QUILL_TOOLBAR_GROUPS[g])
  if (toolbar.every(g => typeof g === 'string' && g in QUILL_TOOLBAR_GROUPS)) return (toolbar as string[]).map(g => QUILL_TOOLBAR_GROUPS[g])
  return toolbar
}
