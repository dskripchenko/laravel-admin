import type { StorybookConfig } from '@storybook/vue3-vite'

/**
 * Витрина компонентов ядра панели.
 *
 * Смысл — посмотреть экран на любой ширине без поднятого бэкенда и без
 * данных: именно так вскрываются дефекты раскладки, которых не видно на
 * разработческом мониторе. Истории лежат рядом с компонентами.
 */
const config: StorybookConfig = {
  stories: ['../resources/ts/**/*.stories.@(ts|tsx)'],
  framework: {
    name: '@storybook/vue3-vite',
    options: {},
  },
  // Правки в токенах ui подхватываются без пересборки: витрина — это
  // инструмент разработки, а не поставляемый артефакт.
  core: { disableTelemetry: true },
}

export default config
