/**
 * The flat config for ESLint 10.
 *
 * The tiers:
 *   1. JS base (eslint:recommended) — the core rules
 *   2. TypeScript (typescript-eslint) — typed-aware checks for resources/ts/**
 *   3. Vue (eslint-plugin-vue@10) — the SFC parser plus the vue rules
 *   4. Prettier compat — switches off the formatting rules that conflict with
 *      reasonable defaults (chosen over autoformatting so as not to breed noise
 *      in the diffs)
 */

import js from '@eslint/js'
import tseslint from 'typescript-eslint'
import vue from 'eslint-plugin-vue'
import prettier from 'eslint-config-prettier'
import globals from 'globals'

export default [
  // 1. JS-base
  js.configs.recommended,

  // 2. TypeScript
  ...tseslint.configs.recommended,

  // 3. Vue (SFC support)
  ...vue.configs['flat/recommended'],

  // 4. Project-specific overrides
  {
    files: ['resources/ts/**/*.{ts,vue}'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.es2022,
      },
      parserOptions: {
        parser: tseslint.parser,
      },
    },
    rules: {
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],
      'vue/component-name-in-template-casing': ['error', 'PascalCase'],
      'vue/max-attributes-per-line': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/multiline-html-element-content-newline': 'off',
      'vue/html-self-closing': 'off',
      'vue/attributes-order': 'warn',
      'vue/require-default-prop': 'off',
      'vue/require-explicit-emits': 'warn',
      'vue/multi-word-component-names': 'off',
      'vue/no-undef-components': 'off',
    },
  },

  // 5. Test-files: relax some rules
  {
    files: ['resources/ts/**/*.test.ts', 'resources/ts/**/*.spec.ts'],
    languageOptions: {
      globals: {
        ...globals.node,
        ...globals.browser,
      },
    },
    rules: {
      '@typescript-eslint/no-explicit-any': 'off',
      '@typescript-eslint/no-unused-expressions': 'off',
      // Tests often hold inline mock components (a Stub component, Provider,
      // Reader, Captured) — that is the norm for unit tests.
      'vue/one-component-per-file': 'off',
      'vue/require-prop-types': 'off',
    },
  },

  // 6. Prettier compat — must come last.
  prettier,

  // Ignored paths
  {
    ignores: [
      'dist/**',
      'node_modules/**',
      'vendor/**',
      'coverage/**',
      'docs/design_handoff_laravel_admin/**',
      'packages/**',
    ],
  },
]
