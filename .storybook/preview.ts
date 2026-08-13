import type { Preview } from '@storybook/vue3-vite'

import '@dskripchenko/ui/styles/all.css'
import '../resources/ts/styles/admin.css'

/**
 * The screen sizes are the same ones printable's CI checks: a phone at 402×874
 * (iPhone 17 Pro), a tablet at 768 and a desktop. The match is deliberate — a
 * defect found in the showcase reproduces in a test and the other way round.
 */
const preview: Preview = {
  parameters: {
    viewport: {
      options: {
        phone: { name: 'Телефон 402', styles: { width: '402px', height: '874px' } },
        tablet: { name: 'Планшет 768', styles: { width: '768px', height: '1024px' } },
        desktop: { name: 'Рабочий стол 1280', styles: { width: '1280px', height: '900px' } },
      },
    },
    backgrounds: { disable: true },
  },
  globalTypes: {
    theme: {
      description: 'Тема панели',
      defaultValue: 'light',
      toolbar: {
        title: 'Тема',
        items: [
          { value: 'light', title: 'Светлая' },
          { value: 'dark', title: 'Тёмная' },
        ],
      },
    },
  },
  decorators: [
    (story, context) => {
      // The panel's theme lives as an attribute on the root — the same way as
      // in the application, otherwise the showcase would show something other
      // than what the panel renders.
      document.documentElement.dataset.theme = context.globals.theme
      return { components: { story }, template: '<story />' }
    },
  ],
}

export default preview
