# 参与贡献

感谢你考虑为 `dskripchenko/laravel-admin` 出一份力。本文说明工作流程、代码风格，
以及代码评审时的期望。

> 🌐 [English](../../.github/CONTRIBUTING.md) · [Deutsch](../de/contributing.md) · [Русский](../ru/contributing.md) · **中文**

## 组件目录

```bash
npm run storybook        # http://localhost:6007
npm run storybook:build  # 同样内容的静态产物——CI 检查的正是这个
```

组件目录能在任意宽度下渲染界面，既不需要后端也不需要数据；开发者显示器上看不见的
布局缺陷，就是这样暴露出来的。切换器里的宽度与 printable 的 CI 所检查的一致
（手机 402、平板 768、桌面 1280）：在这里发现的缺陷能在测试中复现，反之亦然。

story 与组件放在一起，命名为 `*.stories.ts`。

## 快速开始（本地开发）

```bash
git clone https://github.com/dskripchenko/laravel-admin.git
cd laravel-admin
composer install
npm install
```

构建与检查：

```bash
npm run build              # 前端生产构建
vendor/bin/pest            # 后端测试（801+）
npm test                   # 前端测试（319+）
npx vue-tsc --noEmit       # 类型检查
vendor/bin/pint            # PHP 代码风格（自动修复）
vendor/bin/phpstan analyse # 静态分析（level 5）
```

## 仓库结构

| 目录 | 内容 |
|---|---|
| `src/` | PHP 源码（Resource、Screen、Field、Layout、Action、Widget、Menu 等） |
| `resources/ts/` | 基于 Vue 3 与 TypeScript 的单页应用 |
| `resources/views/` | 外壳的 Blade 模板 |
| `config/` | 默认配置（`admin.php`） |
| `database/migrations/` | 内置迁移 |
| `routes/` | 后台路由（通过 `AdminServiceProvider` 注册） |
| `tests/` | Pest 测试（Feature + Unit + Fixtures） |
| `docs/` | 本文档树（`{en,ru,de,zh}/`） |

## 分支与提交

- 从 `main` 拉分支。
- 一个 pull request 只做一件事，尽量小。
- 提交信息用祈使句，并带上范围前缀：
  ```
  feat(dashboard): widget polling по Widget::refresh
  fix(notifications): graceful fallback if table missing
  docs(menu): add MenuNode::dashboard examples
  test(screen): cover runMethod 422 path
  refactor(widget): inline rowSpan resolver
  chore: bump @dskripchenko/wysiwyg ^0.2.7
  ```
- 借助 AI 完成的工作，欢迎附上 `Co-Authored-By` 尾注。

## 代码风格

### PHP

- **PHP 8.5+**，每个文件开头声明 `strict_types`。
- 由 **Pint** 格式化（`vendor/bin/pint`），提交前请运行。
- **PHPStan level 5.** 不要放宽类型，也不要用 `@phpstan-ignore` 消声——
  去修根因（若是历史遗留，请说明原因）。
- 类型通过 `use` 导入，不要在代码里写完整类名。
- 公有方法的 `@param`/`@return` 文档块只在类型本身不够时才写：泛型数组、
  callable 形态之类。

### TypeScript 与 Vue

- 一律使用 `<script setup lang="ts">`，不用 Options API。
- composable 放在 `composables/`（camelCase，`use*` 前缀）。
- props 用 `withDefaults(defineProps<Props>(), { ... })`。
- 类名遵循类 BEM 风格：`.admin-{component}__{element}--{modifier}`。
- 前端的基础组件取自 `@dskripchenko/ui`——不要自己写原始标记，
  用 `UidButton`、`UidInput` 等。

### CSS

- 只用 CSS 自定义属性（`var(--uid-...)`）。不用 Tailwind、SCSS 或 CSS-in-JS。
- 不要 `<style scoped>`——主题需要穿透进去。

## 测试

- **后端**：Pest（`vendor/bin/pest`）。`src/` 的结构镜像到 `tests/Feature/`
  与 `tests/Unit/`。fixture 放在 `tests/Fixtures/`（由 Composer classmap 自动加载，
  全局命名空间）。
- **前端**：Vitest + jsdom + `@vue/test-utils`。测试文件与被测对象相邻
  （`Component.test.ts`）。
- 不要 mock 数据库——用内存中的 SQLite，`Orchestra\Testbench` 已经配好。
- Playwright 的端到端冒烟测试在 `demo/e2e-full-flow.mjs`。

## 发布

包由同一个标签发往两个仓库：Composer 读标签本身，npm 读 `package.json`。
两者允许不同步——只改动 PHP 的变更打标签，但不发 npm 版本。

1. 更新 `CHANGELOG.md`。
2. 若前端有改动，在同一个提交里提升 `package.json` 的 `version`；没改就别动。
3. 打标签 `vX.Y.Z` 并推送。

推送标签会触发 `Publish to npm`。只有当 `package.json` 写的正是该标签的版本、
且该版本尚未进入仓库时才会发布。其余情况——纯 PHP 的标签、对已发布标签的重跑——
都会在不发布的情况下以绿色结束，好让历史记录如实反映实际发生了什么。

发布前会先跑 `typecheck` 与 `test`：标签可能落在分支 CI 从未见过的提交上，
而发坏的版本无法从 npm 撤回。

如果标签推得比版本更新更早，就在 `main` 上提升版本，然后手动触发
`Publish to npm`（`workflow_dispatch`）——同样的检查依然生效。

## 配套包

本仓库是**核心**。配套包（`starter`、`health`、`jobs`、`media`、`pulse`、
`search`、`quill`、`tinymce`）各自独立成库，通过 composer 依赖本包；
在 API 破坏性变更之前，版本契约为 `^1.x`。

## 报告缺陷

请使用 GitHub issues，并附上：
- Laravel、PHP 与本包的版本；
- 最小复现（代码片段或仓库链接）；
- 你期望的结果与实际结果；
- 控制台报错与堆栈（若有）。

## 安全

安全问题请发邮件至 `denskrp90@gmail.com`，不要开公开 issue。

## 许可证

参与贡献即表示你同意你的成果依据本项目的
[MIT 许可证](../../LICENSE)发布。
