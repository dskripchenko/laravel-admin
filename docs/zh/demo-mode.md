---
title: 演示模式
audience: developer
status: stable
locale: zh
translated_from: en/demo-mode.md
translated_at: 2026-10-02
---

# 演示模式

演示模式会把一个安装实例变成公开的演示站点：访客可以一键以某个演示账号登录，
而那些可能让一位访客把站点弄坏、影响下一位访客的操作会被拒绝。该模式默认关闭。

```dotenv
ADMIN_DEMO=true
```

## 演示账号

在 `config/admin.php` 中列出账号；登录页会为每个账号显示一个
“以……身份登录”按钮：

```php
'demo' => [
    'enabled' => (bool) env('ADMIN_DEMO', false),
    'accounts' => [
        [
            'label' => '管理员',
            'email' => 'admin@demo.test',
            'password' => 'demo',
            'description' => '完全访问',
        ],
        [
            'label' => '编辑',
            'email' => 'editor@demo.test',
            'password' => 'demo',
            'description' => '仅内容，不含设置',
        ],
    ],
],
```

点击按钮会填写表单并通过普通的登录端点登录，因此适用于登录的一切——限流、
被禁用的账号、2FA——在这里同样适用。`label` 和 `description` 会经过翻译器处理，
因此它们可以是翻译键。

> **警告** 这些密码会发送给登录页的每一位访客。只列出演示账号，切勿列出真实账号，
> 并且只授予您愿意展示的权限。

这些账号本身就是普通用户：请在 seeder 中创建它们。

## 只读保护

开启 `readonly` 时（启用演示模式后默认开启），API 会以 `403` 和
`errorKey: demo_readonly` 拒绝一组操作。面板会以 toast 提示显示这一拒绝——
“演示模式：此操作已禁用。”——而不是通用错误。

```php
'demo' => [
    'readonly' => (bool) env('ADMIN_DEMO_READONLY', true),

    // API Action，格式为 `controller.action` 模式；`*` 匹配任意内容。
    'blocked' => [
        'profile.update',
        'profile.changePassword',
        'profile.twoFactorEnable',
        'profile.twoFactorConfirm',
        'profile.twoFactorDisable',
        'profile.twoFactorRegenerateCodes',
        'profile.tokenCreate',
        'profile.tokenRevoke',
        'auth.startImpersonation',
        'import.*',
        'settings_*.update',
    ],

    // 对这些模型的 Resource 的写入会被拒绝。null：面板的用户模型和
    // Role——谁都无法把下一位访客锁在门外。
    'protected_models' => null,
    'protected_actions' => [
        'create', 'update', 'inlineUpdate', 'replicate', 'reorder',
        'delete', 'restore', 'forceDelete', 'action',
    ],

    // 超过此大小的上传文件会被拒绝；0 关闭该限制。
    'max_upload_kb' => 2048,
],
```

默认值涵盖的内容：

| 操作 | 原因 |
|---|---|
| 修改个人资料和密码 | 下一位访客将无法登录 |
| 启用或禁用 2FA、重新生成恢复码 | 同上 |
| 创建和撤销 API 令牌 | 令牌的寿命比演示会话更长 |
| 模拟登录（impersonation） | 会打开站点上的所有账号 |
| 对用户和角色的写入 | 删除演示账号或剥夺其权限 |
| 设置 | 设置由所有访客共享 |
| 导入 | 批量写入 |
| 大文件上传 | 磁盘空间 |

其余一切——创建、编辑和删除您的演示记录——仍然开放；这正是访客前来体验的内容。

### 调整列表

这些列表就是普通的配置，因此宿主可以添加或删除条目：

```php
// 允许修改个人资料，同时也拒绝 "articles" 的批量 Action。
'blocked' => [
    'profile.changePassword',
    'articles.action',
    'settings_*.update',
],

// 再保护一个模型。
'protected_models' => [
    App\Models\User::class,
    Dskripchenko\LaravelAdmin\Permission\Models\Role::class,
    App\Models\Tenant::class,
],
```

Resource 的 controller 是它的 slug（`articles.update`），设置页面的是
`settings_{slug}`，Screen 的是它的 slug（`reports.runMethod`）。内置 controller：
`auth`、`profile`、`system`、`dashboard`、`audit`、`import`、
`uploads`、`notifications`、`delayed`。

`readonly` 按请求检查，因此也可以在运行时切换——在中间件中，针对特定用户：

```php
config(['admin.demo.readonly' => ! $request->user()?->is_staff]);
```

## 横幅

通过安装横幅 `admin.notice` 告诉访客他们所在的是什么样的站点。它由 shell
绘制，显示在面板上方以及登录页上：

```dotenv
ADMIN_NOTICE="演示站点：数据每小时重置一次"
ADMIN_NOTICE_HREF=https://example.com/docs
ADMIN_NOTICE_COUNTDOWN_LABEL="距离重置"
```

`countdown_to`（ISO-8601）会添加一个倒计时；请在中间件中设置它，因为缓存的配置
会把计算出来的值冻结：

```php
config(['admin.notice.countdown_to' => now()->startOfHour()->addHour()->toIso8601String()]);
```

## 重置数据

包本身不会重置站点：请调度您自己的命令来恢复数据库（`migrate:fresh --seed`、
转储、快照）并重新创建演示账号。

```php
// routes/console.php
Schedule::command('migrate:fresh --seed --force')->hourly();
```
