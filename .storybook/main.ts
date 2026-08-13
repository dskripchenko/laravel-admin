import type { StorybookConfig } from '@storybook/vue3-vite'

/**
 * The showcase of the panel core's components.
 *
 * The point is to look at a screen at any width without a running backend and
 * without data: that is exactly how the layout defects invisible on a
 * developer's monitor come out. The stories live next to the components.
 */
const config: StorybookConfig = {
  stories: ['../resources/ts/**/*.stories.@(ts|tsx)'],
  framework: {
    name: '@storybook/vue3-vite',
    options: {},
  },
  // Edits to the ui tokens are picked up without a rebuild: the showcase is a
  // development tool rather than a shipped artifact.
  core: { disableTelemetry: true },
}

export default config
