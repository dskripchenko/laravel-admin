/**
 * FilterEditor — the render function behind one filter's editor.
 *
 * It is a module rather than an SFC because the component is purely
 * render-based: it picks a control for the filter.type, with no template and
 * no state.
 */
import { defineComponent, h, type PropType } from 'vue'
import { trSafe as tr } from '../../stores/i18n'

export interface FilterOption {
  value: string | number
  label: string
}

export interface FilterDef {
  name: string
  label: string
  type: string
  icon?: string | null
  options?: FilterOption[] | null
  multiple?: boolean
  default?: unknown
  placeholder?: string
}

export const FilterEditor = defineComponent({
  name: 'FilterEditor',
  props: {
    filter: { type: Object as PropType<FilterDef>, required: true },
    draft: { type: null as unknown as PropType<unknown>, required: false, default: null as unknown },
    isChecked: {
      type: Function as PropType<(v: string | number) => boolean>,
      required: true,
    },
  },
  emits: ['toggle-option', 'set-draft'],
  setup(props, { emit }) {
    return () => {
      const f = props.filter

      // An OptionsFilter: single renders as radios, multiple as checkboxes.
      if (f.type === 'options' && f.options && f.options.length > 0) {
        return h(
          'div',
          { class: 'admin-toolbar__editor admin-toolbar__editor--options' },
          f.options.map((opt) =>
            h(
              'label',
              {
                key: String(opt.value),
                class: 'admin-toolbar__list-item admin-toolbar__list-item--checkbox',
              },
              [
                h('input', {
                  type: f.multiple ? 'checkbox' : 'radio',
                  checked: props.isChecked(opt.value),
                  onChange: () => emit('toggle-option', opt.value),
                }),
                h('span', null, opt.label),
              ],
            ),
          ),
        )
      }

      // A DateRangeFilter: two inputs of type=date.
      if (f.type === 'date_range') {
        const v = (props.draft ?? {}) as { from?: string; to?: string }
        return h('div', { class: 'admin-toolbar__editor admin-toolbar__editor--dates' }, [
          h('input', {
            type: 'date',
            value: v.from ?? '',
            class: 'admin-toolbar__input',
            placeholder: tr('От'),
            onChange: (e: Event) =>
              emit('set-draft', { ...v, from: (e.target as HTMLInputElement).value }),
          }),
          h('input', {
            type: 'date',
            value: v.to ?? '',
            class: 'admin-toolbar__input',
            placeholder: tr('До'),
            onChange: (e: Event) =>
              emit('set-draft', { ...v, to: (e.target as HTMLInputElement).value }),
          }),
        ])
      }

      // TrashedFilter — tri-state (without / with / only).
      if (f.type === 'trashed') {
        const options = [
          { value: 'without', label: tr('Без удалённых') },
          { value: 'with', label: tr('С удалёнными') },
          { value: 'only', label: tr('Только удалённые') },
        ]
        return h(
          'div',
          { class: 'admin-toolbar__editor admin-toolbar__editor--options' },
          options.map((opt) =>
            h(
              'label',
              {
                key: opt.value,
                class: 'admin-toolbar__list-item admin-toolbar__list-item--checkbox',
              },
              [
                h('input', {
                  type: 'radio',
                  checked: props.draft === opt.value,
                  onChange: () => emit('set-draft', opt.value),
                }),
                h('span', null, opt.label),
              ],
            ),
          ),
        )
      }

      // SwitcherFilter — yes/no.
      if (f.type === 'switcher') {
        return h('div', { class: 'admin-toolbar__editor' }, [
          h('label', { class: 'admin-toolbar__list-item admin-toolbar__list-item--checkbox' }, [
            h('input', {
              type: 'radio',
              checked: props.draft === true,
              onChange: () => emit('set-draft', true),
            }),
            h('span', null, tr('Да')),
          ]),
          h('label', { class: 'admin-toolbar__list-item admin-toolbar__list-item--checkbox' }, [
            h('input', {
              type: 'radio',
              checked: props.draft === false,
              onChange: () => emit('set-draft', false),
            }),
            h('span', null, tr('Нет')),
          ]),
        ])
      }

      // Default: text input (input / select_from_model / unknown type).
      return h('input', {
        type: 'text',
        value: props.draft ?? '',
        class: 'admin-toolbar__input',
        placeholder: f.placeholder ?? f.label,
        onChange: (e: Event) => emit('set-draft', (e.target as HTMLInputElement).value),
      })
    }
  },
})

export default FilterEditor
