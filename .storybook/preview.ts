import type { Preview } from '@storybook/vue3-vite'

import '@dskripchenko/ui/styles/all.css'
import '../resources/ts/styles/admin.css'

/**
 * Размеры экранов те же, что проверяет CI printable: телефон 402×874
 * (iPhone 17 Pro), планшет 768 и рабочий стол. Совпадение намеренное —
 * дефект, найденный в витрине, воспроизводится тестом и наоборот.
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
      // Тема панели живёт атрибутом на корне — тем же способом, что и в
      // приложении, иначе витрина показывала бы не то, что печатает панель.
      document.documentElement.dataset.theme = context.globals.theme
      return { components: { story }, template: '<story />' }
    },
  ],
}

export default preview
