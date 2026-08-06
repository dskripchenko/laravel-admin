# Contributing

Thanks for considering a contribution to `dskripchenko/laravel-admin`!
This document covers the workflow, code style and review expectations.

> 🌐 [English](CONTRIBUTING.md) · [Русский](../docs/ru/contributing.md) · [Deutsch](../docs/de/contributing.md) · [中文](../docs/zh/contributing.md)

## Витрина компонентов

```bash
npm run storybook        # http://localhost:6007
npm run storybook:build  # то же статикой, этим же проверяет CI
```

Витрина показывает экран на любой ширине без поднятого бэкенда и без
данных — так вскрываются дефекты раскладки, которых не видно на
разработческом мониторе. Размеры экранов в переключателе те же, что
проверяет CI printable (телефон 402, планшет 768, рабочий стол 1280):
дефект, найденный здесь, воспроизводится тестом и наоборот.

Истории лежат рядом с компонентами — `*.stories.ts`.

## Quick start (local development)

```bash
git clone https://github.com/dskripchenko/laravel-admin.git
cd laravel-admin
composer install
npm install
```

Build:

```bash
npm run build              # production frontend bundle
vendor/bin/pest            # backend tests (801+ tests)
npm test                   # frontend tests (319+ tests)
npx vue-tsc --noEmit       # type-check
vendor/bin/pint            # PHP code style (auto-fix)
vendor/bin/phpstan analyse # static analysis (level 5)
```

## Repository layout

| Directory | Contents |
|---|---|
| `src/` | PHP source (Resource, Screen, Field, Layout, Action, Widget, Menu, etc.) |
| `resources/ts/` | Vue 3 + TypeScript SPA bundle |
| `resources/views/` | Blade shell template |
| `config/` | Default config (`admin.php`) |
| `database/migrations/` | Built-in migrations |
| `routes/` | Admin routes (registered through `AdminServiceProvider`) |
| `tests/` | Pest tests (Feature + Unit + Fixtures) |
| `docs/` | This documentation tree (`{en,ru,de,zh}/`) |

## Branch / commit conventions

- Branch from `main`.
- One topic per PR. Keep them small.
- Commit message: imperative mood, scope prefix:
  ```
  feat(dashboard): widget polling по Widget::refresh
  fix(notifications): graceful fallback if table missing
  docs(menu): add MenuNode::dashboard examples
  test(screen): cover runMethod 422 path
  refactor(widget): inline rowSpan resolver
  chore: bump @dskripchenko/wysiwyg ^0.2.7
  ```
- Co-Authored-By trailer is welcome for AI-assisted work.

## Code style

### PHP

- **PHP 8.5+**, strict types declared at the top of every file.
- **Pint** is the formatter (`vendor/bin/pint`). Run before committing.
- **PHPStan level 5**. Don't widen types or add `@phpstan-ignore` to
  silence — fix the underlying issue (or document why if pre-existing).
- Type-only imports `use`, not inline FQCN.
- Public methods have docblock `@param`/`@return` only when types alone
  aren't enough (generic arrays, callable shapes, etc.).

### TypeScript / Vue

- `<script setup lang="ts">` everywhere. No Options API.
- Composables in `composables/` (camelCase, `use*` prefix).
- Props with `withDefaults(defineProps<Props>(), { ... })`.
- Class names follow BEM-ish: `.admin-{component}__{element}--{modifier}`.
- Frontend uses `@dskripchenko/ui` for primitives — don't write raw
  HTML widgets, use `UidButton`/`UidInput`/etc.

### CSS

- CSS custom properties only (`var(--uid-...)`). No Tailwind, no SCSS,
  no CSS-in-JS.
- No `<style scoped>` — themes need to penetrate.

## Testing

- **Backend**: Pest (`vendor/bin/pest`). Mirror `src/` structure under
  `tests/Feature/` and `tests/Unit/`. Fixtures in `tests/Fixtures/`
  (autoloaded via composer classmap; global namespace).
- **Frontend**: Vitest + jsdom + `@vue/test-utils`. Test files next to
  the SUT (`Component.test.ts`).
- Don't mock the database — use SQLite in-memory (already configured
  by `Orchestra\Testbench`).
- E2E smoke (Playwright) lives in `demo/e2e-full-flow.mjs`.

## Releasing

The package ships to two registries from one tag. Composer reads the tag
itself; npm reads `package.json`. They are allowed to diverge — a change
that touches only PHP gets a tag without an npm release.

1. Update `CHANGELOG.md`.
2. If the frontend changed, bump `version` in `package.json` in the same
   commit. If it didn't, leave it alone.
3. Tag `vX.Y.Z` and push the tag.

Pushing the tag runs `Publish to npm`, which publishes only when
`package.json` names exactly the tag's version and that version is not in
the registry yet. Anything else — a PHP-only tag, a re-run of a tag that
already shipped — ends green without publishing, so the history stays
honest about what actually happened.

Publishing runs `typecheck` and `test` first: a tag can sit on a commit
the branch CI never saw, and a bad version cannot be taken back out of
npm.

If a tag was pushed before `package.json` caught up, bump the version on
`main` and start `Publish to npm` by hand (`workflow_dispatch`) — the same
guards apply.

## Sister-packs

This repository is the **core**. Sister-packs (`starter`, `health`,
`jobs`, `media`, `pulse`, `search`, `quill`, `tinymce`) live in
separate repositories. They depend on this package via composer; the
versioning contract is `^1.x` until an API break.

## Reporting bugs

Use GitHub issues. Include:
- Laravel version, PHP version, package version
- Minimal reproduction (code snippet or repository link)
- What you expected vs. what happened
- Console errors / stack trace (if any)

## Security

Email security reports to `denskrp90@gmail.com` rather than opening
public issues.

## License

By contributing you agree your work is licensed under the project
[MIT License](../LICENSE).
