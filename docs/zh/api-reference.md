---
title: API 参考
audience: developer
status: stable
locale: zh
translated_from: en/api-reference.md
translated_at: 2026-10-02
---

# API 参考

管理面板 SPA 通过 `/api/admin/...` 上的 JSON 与后端通信。所有响应都遵循
`dskripchenko/laravel-api` 的 `{success, payload}` 信封格式。前缀取决于
`config('laravel-api.prefix')`：设为 `api/v1` 时，管理 API 移至 `/api/v1/admin/...`。

## 信封格式

```json
{
  "success": true,
  "payload": { ... }
}
```

错误：

```json
{
  "success": false,
  "payload": {
    "errorKey": "validation",
    "message": "...",
    "messages": { "field": ["..."] }
  }
}
```

HTTP 状态码：200 / 304 / 401 / 403 / 404 / 422 / 429 / 500。

OpenAPI 3.0 规范自动生成，并在 `/api/admin/doc` 提供（使用 Scalar UI
渲染）——参见 [OpenAPI 规范](#openapi-规范)。

## 端点

基础前缀：`/api/admin/`。

### system

| 方法 | 路径 | 返回 |
|---|---|---|
| GET | `system/bootstrap` | SPA 初始载荷（CSRF、locale、主题、品牌、用户、manifestVersion）。公开。 |
| GET | `system/manifest` | 完整 manifest。需要认证。使用 ETag 缓存。 |
| GET | `system/me` | 当前管理员用户摘要。 |
| GET | `system/menu` | 侧边栏树（自定义 + 自动）。 |
| GET | `system/search` | 全局搜索（⌘K 面板）。 |
| GET | `system/locales` | 可用的 locale。公开。 |
| POST | `system/setLocale` | 保存用户 locale。公开。 |
| GET | `system/permissions` | 所有已注册的权限组。 |
| GET | `system/plugins` | 已加载的插件。 |
| GET | `system/status` | 顶栏的状态指示器（不缓存）。 |
| GET | `system/theme` | 当前主题。公开。 |
| POST | `system/setTheme` | 保存用户主题。公开。 |

### auth

| 方法 | 路径 | |
|---|---|---|
| POST | `auth/login` | 邮箱 + 密码 → 会话。启用 2FA 时，改为返回 `success: false`、`errorKey: two_factor_required` 以及一个 `challenge_token`（有效期 5 分钟）。 |
| POST | `auth/twoFactorChallenge` | `challenge_token` + TOTP 验证码。 |
| POST | `auth/twoFactorRecovery` | 恢复码。 |
| POST | `auth/logout` | |
| POST | `auth/forgotPassword` | |
| POST | `auth/resetPassword` | |
| POST | `auth/verifyEmail` | |
| POST | `auth/resendEmailVerification` | |
| POST | `auth/startImpersonation` | 管理员模拟另一个用户。 |
| POST | `auth/stopImpersonation` | |

### profile

| 方法 | 路径 | |
|---|---|---|
| GET | `profile/show` | |
| POST | `profile/update` | |
| POST | `profile/changePassword` | |
| GET | `profile/twoFactorStatus` | `enabled`、`confirmed_at`、`recovery_codes_remaining`。 |
| POST | `profile/twoFactorEnable` | 新的 `secret`、用于配置的 `qr_uri` 和 `recovery_codes`；在确认之前处于待定状态。 |
| POST | `profile/twoFactorConfirm` | 校验第一个验证码。 |
| POST | `profile/twoFactorDisable` | |
| POST | `profile/twoFactorRegenerateCodes` | |
| GET | `profile/tokensList` | （Sanctum） |
| POST | `profile/tokenCreate` | |
| POST | `profile/tokenRevoke` | |

### dashboard

| 方法 | 路径 | |
|---|---|---|
| GET | `dashboard/get?key={slug}` | 用户保存的布局（或 null）。 |
| POST | `dashboard/save` | 保存用户布局。 |
| POST | `dashboard/savePeriod` | 保存用户的时间段过滤器，不改动布局。 |
| POST | `dashboard/reset` | 删除用户的覆盖设置（恢复为 manifest 中的布局）。 |
| GET | `dashboard/widgets?key={slug}&period={p}` | 重新获取小部件数据（用于轮询和切换时间段）。 |

### resources（按 Resource，动态）

对每个已注册的 Resource，前缀为 `{slug}/`：

| 方法 | 路径 | |
|---|---|---|
| GET | `{slug}/meta` | Resource 元数据（字段、列、过滤器、Action、Screen）。 |
| POST | `{slug}/search` | 经过过滤、排序和分页的列表。请求体：`{filters, q, order: [{column, direction}], page, per_page, ids?, group_by?}`。 |
| POST | `{slug}/summary` | 列表的聚合值（sum/avg/count）。 |
| GET | `{slug}/read?id={id}` | 单条记录。 |
| POST | `{slug}/create` | |
| POST | `{slug}/update` | |
| POST | `{slug}/inlineUpdate` | 单字段补丁更新。 |
| POST | `{slug}/delete` | |
| POST | `{slug}/restore` | 仅用于带 `SoftDeletes` 的 Resource。 |
| POST | `{slug}/forceDelete` | 仅用于带 `SoftDeletes` 的 Resource。 |
| POST | `{slug}/replicate` | 仅当 `replicable()` 时。 |
| POST | `{slug}/reorder` | 仅当 `reorderable()` 时。 |
| GET/POST | `{slug}/export?format=csv` | `format`：`csv`（默认）、`xlsx`、`pdf`——取决于已安装的导出器；`columns[]` 用于缩小导出的列。 |
| POST | `{slug}/action` | 通用 Action 分发器。请求体：`{key, ids[], payload}`。 |
| GET | `{slug}/listScreen` | 编译后的 `GeneratedListScreen` 快照。 |
| GET | `{slug}/treeScreen` | 仅用于层级结构的 Resource。 |
| POST | `{slug}/tree` | 树节点（层级结构的 Resource）。 |
| GET | `{slug}/createScreen` | |
| GET | `{slug}/editScreen?id={id}` | |
| GET | `{slug}/viewScreen?id={id}` | |
| POST | `{slug}/listener` | 响应式表单部分（`Layout::listener`）；仅当表单中存在时注册。 |

Resource 不支持的 Action 不会被注册，请求时返回 404。

已保存视图：`{slug}_views/{list,create,update,delete}`——仅为 `savedViews()` 返回 `true` 的 Resource 注册。

### screens（按 Screen，动态）

对每个已注册的自定义 Screen，前缀为 `{slug}/`：

| 方法 | 路径 | |
|---|---|---|
| GET | `{slug}/state` | `Screen::compile()` 载荷。 |
| POST | `{slug}/runMethod` | 请求体：`{method, payload, parameters?}`。 |
| POST | `{slug}/listener` | 响应式表单部分。 |

### settings（按 SettingsResource，动态）

前缀 `settings_{slug}/`：

| 方法 | 路径 | |
|---|---|---|
| GET | `settings_{slug}/meta` | |
| GET | `settings_{slug}/read` | |
| POST | `settings_{slug}/update` | |

### audit

| 方法 | 路径 | |
|---|---|---|
| GET | `audit/list` | 所有审计日志条目（过滤条件：`subject_type`、`subject_id`、`actor_type`、`actor_id`、`event`、`from`、`to`）。 |
| GET | `audit/timeline?subject_type=&subject_id=` | 单条记录的时间线。 |

### notifications

| 方法 | 路径 | |
|---|---|---|
| GET | `notifications/list?type=all|unread|read` | |
| GET | `notifications/unread` | 供铃铛徽标轮询使用。 |
| POST | `notifications/markAsRead` | |
| POST | `notifications/markAllAsRead` | |
| POST | `notifications/destroy` | |

### import

| 方法 | 路径 | |
|---|---|---|
| POST | `import/upload` | 暂存 CSV/XLSX 文件。 |
| POST | `import/preview` | 表头 + 样本 + 自动映射。 |
| POST | `import/start` | 执行导入。 |
| GET | `import/status?id={id}` | 导入进程的进度。 |

### uploads

| 方法 | 路径 | |
|---|---|---|
| POST | `uploads/upload` | 通用文件上传。 |
| POST | `uploads/image` | 图片专用（由 Wysiwyg 使用）。 |
| GET | `uploads/serve?disk=&path=` | 提供已存储的文件。 |

### delayed（长时间运行的任务）

| 方法 | 路径 | |
|---|---|---|
| POST | `delayed/run` | 启动一个队列进程。仅限白名单中的处理器。 |
| GET | `delayed/status?uuid={u}` | 轮询进度。 |

## 缓存

- **Manifest**——ETag = manifest 的 `version`（包版本与载荷的 sha256；载荷按
  locale 和面板分别构建，因此也会随权限变化）。使用 `If-None-Match` 可获得 304。
- **Bootstrap**——不缓存（每个请求的 CSRF 都不同）。
- **其他端点**——没有 HTTP 缓存（需要认证，查询速度快）。

## 限流

所有管理端点默认 `240/min`（`config('admin.api.throttle')`，`'240,1'`），
认证端点另有额外限流：

- `auth/login`、`auth/twoFactorChallenge`、`auth/twoFactorRecovery`——5/min
  （`config('admin.auth.login_throttle')`，`'5,1'`）
- `auth/forgotPassword`——3/5min
- `auth/resendEmailVerification`——3/min

## OpenAPI 规范

```
GET /api/admin/doc            # Scalar UI（交互式）
GET /api/doc/admin            # 原始 JSON 规范（laravel-api 按版本提供的来源）
```

Scalar 页面默认开启；将 `config('admin.openapi.ui')`（env
`ADMIN_OPENAPI_UI`）设为 `scalar` 以外的任何值，则由 laravel-api 自身的
`/api/doc` 接管。

Resource 的操作按 Resource 分别生成文档。`create` 和 `update` 列出该 Resource
自己的字段——根据 `fields()` 和 `validationRules()` 构建，包含类型、格式、是否必填、
来自选项的枚举以及取值范围；`search`、`export`、`action`、`reorder`、`inlineUpdate`
以及设置组的 `update` 则记录依赖于 Resource 的部分（过滤器、可排序和可导出的列、
Action 键、可编辑的列、设置值）。服务于多个路由的控制器也可以这样做：声明
`@input [method]`，并在该方法中对
`Dskripchenko\LaravelApi\Services\OpenApi\OperationContext` 做类型提示——它会得知
正在描述的路由所对应的控制器键和 Action。

`php artisan api:lint --strict` 会检查这些标注，包括那些校验输入却没有声明任何输入的
Action（`input.undeclared`）。

## 另请参阅

- [架构](architecture.md)——manifest + 信封结构
- [前端扩展](frontend-extension.md)——添加自定义端点
