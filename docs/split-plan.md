# Split Plan: monorepo → 10 repositories

## Зафиксированные решения

| # | Решение |
|---|---|
| 1 | Без сохранения git-истории — clean copy в каждый split-repo |
| 2 | Только Packagist для PHP-зависимостей; root `composer.json` чистится от `repositories: path` |
| 3 | Frontend Quill/Tinymce встраивается в main npm `@dskripchenko/laravel-admin` как subpath-exports (`./quill`, `./tinymce`); split-repo'ы PHP-only |
| 4 | Все 9 PHP-пакетов сразу регистрируются на Packagist |
| 5 | Demo: один репозиторий, локальный quick-start + deployable showcase в одном (вариант C) |

## Итоговый список репозиториев

| # | GitHub | Composer | npm | Тип |
|---|--------|----------|-----|-----|
| 1 | `dskripchenko/laravel-admin` | `dskripchenko/laravel-admin` | `@dskripchenko/laravel-admin` | core (PHP + frontend) |
| 2 | `dskripchenko/laravel-admin-starter` | `dskripchenko/laravel-admin-starter` | — | sister-pack |
| 3 | `dskripchenko/laravel-admin-health` | `dskripchenko/laravel-admin-health` | — | sister-pack |
| 4 | `dskripchenko/laravel-admin-jobs` | `dskripchenko/laravel-admin-jobs` | — | sister-pack |
| 5 | `dskripchenko/laravel-admin-media` | `dskripchenko/laravel-admin-media` | — | sister-pack |
| 6 | `dskripchenko/laravel-admin-pulse` | `dskripchenko/laravel-admin-pulse` | — | sister-pack |
| 7 | `dskripchenko/laravel-admin-search` | `dskripchenko/laravel-admin-search` | — | sister-pack |
| 8 | `dskripchenko/laravel-admin-quill` | `dskripchenko/laravel-admin-quill` | — | sister-pack PHP-only |
| 9 | `dskripchenko/laravel-admin-tinymce` | `dskripchenko/laravel-admin-tinymce` | — | sister-pack PHP-only |
| 10 | `dskripchenko/laravel-admin-demo` | — *(application)* | — | demo / showcase |

## Открытые вопросы (требуют ответа перед стартом)

1. Существует ли уже `github.com/dskripchenko/laravel-admin`? (От ответа зависит — создавать 9 или 10 пустых repo на этапе E3.)
2. Стартовая версия sister-pack'ов: `0.1.0` (early-dev) или `1.0.0` (stable вместе с core)?
3. (Закрыто: `gh` CLI не установлен — репозитории создаются вручную через github.com.)

## Этапы исполнения

### Stage 0 — pre-split refactor (в текущем monorepo)
- Перенести `packages/quill/resources/ts/` → `resources/ts/components/fields/wysiwyg/quill/`
- Перенести `packages/tinymce/resources/ts/` → `resources/ts/components/fields/wysiwyg/tinymce/`
- `vite.config.ts`: multi-entry build (`index`, `quill`, `tinymce`)
- `package.json`: subpath-exports `./quill` + `./tinymce`; убрать `workspaces`; quill/tinymce npm-deps → optional peer
- Удалить `packages/{quill,tinymce}/{resources,package.json,vite.config.*}`
- Root `composer.json`: убрать секции `repositories: path`, `require-dev: dskripchenko/laravel-admin-*`, `autoload-dev` маппинг pack tests
- Build + tests + phpstan + pint clean
- Commit `"chore: pre-split — frontend Quill/Tinymce → core npm subpath-exports"`

### Stage 1 — extract sister-packs (только подготовка директорий)
Для каждого из 8 packs создать `<workspace>/<pack>/` со структурой:
```
src/                      # копия packages/<pack>/src/
config/                   # копия packages/<pack>/config/
database/                 # копия packages/<pack>/database/  (если есть)
routes/                   # копия packages/<pack>/routes/    (если есть)
tests/                    # копия packages/<pack>/tests/
composer.json             # из packages/<pack>/composer.json + правки (см. ниже)
phpunit.xml.dist          # из packages/<pack>/
README.md                 # из packages/<pack>/
LICENSE                   # копия из core (MIT)
.gitignore                # стандартный (vendor/, .phpunit.cache, etc.)
.gitattributes
phpstan.neon              # как в core, level 5
pint.json                 # копия из core
```
Правки в `composer.json` каждого pack:
- `dskripchenko/laravel-admin: ^1.0` (вместо `^1.0|@dev`)
- Добавить `phpstan/phpstan` и `larastan/larastan` в `require-dev`
- Убрать `pestphp/*` (sister-packs используют PHPUnit, не Pest — см. memory)

После подготовки каждый pack: `composer install && phpunit -c phpunit.xml.dist` зелёный.

### Stage 2 — demo skeleton
Чистый Laravel 12 host-проект в `<workspace>/demo/`:
```
app/
├── Admin/
│   ├── Resources/
│   │   ├── ArticleResource.php       # CRUD + WYSIWYG + tags
│   │   ├── ProductResource.php       # CRUD + media gallery
│   │   └── OrderResource.php         # CRUD + status workflow
│   └── Dashboards/
│       └── MainDashboard.php         # stat widgets + recent table
├── Models/
│   ├── Article.php
│   ├── Product.php
│   └── Order.php
└── Providers/
    └── AppServiceProvider.php

database/
├── migrations/
│   ├── *_create_articles_table.php
│   ├── *_create_products_table.php
│   └── *_create_orders_table.php
└── seeders/
    └── DemoSeeder.php                # ~50 fake records через faker

config/
└── admin.php                         # все 8 packs в plugins[]

deploy/
├── Dockerfile
├── docker-compose.yml                # app + mysql + redis
├── .env.production.example
├── nginx.conf
└── forge.md                          # пошаговый guide для Forge

composer.json                         # require: laravel-admin + 8 packs (Packagist)
package.json                          # @dskripchenko/laravel-admin
README.md                             # local quick-start + demo URL + credentials
```

### Stage 3 — GitHub repos (вручную через github.com)
Создать публичные пустые репозитории (без README/LICENSE/.gitignore при создании):
- 9 PHP-пакетов (если main уже существует — 8) + demo

### Stage 4 — push & публикация
В каждой подготовленной директории:
```bash
git init
git add .
git commit -m "chore: initial extract from monorepo"
git branch -M main
git remote add origin git@github.com:dskripchenko/<name>.git
git push -u origin main
git tag v1.0.0  # (или 0.1.0 — см. open question #2)
git push --tags
```
В core repo дополнительно:
- Удалить `packages/`
- Commit `"chore: split sister-packs to separate repositories"`
- Тэг `v1.0.0`
- Push

### Stage 5 — Packagist
- packagist.org → Submit для каждого из 9 GitHub-repo (не demo)
- Настроить GitHub webhook auto-update в каждом repo (Settings → Webhooks → Packagist)
- Порядок: сначала core, потом 8 packs

## Предлагаемая структура локального dev-каталога

После split-а текущий `/Users/dskripchenko/www/forge/laravel-admin/` становится **workspace-каталогом**, внутри которого живут 10 git-репозиториев как сиблинги. Каждый подкаталог — независимый git-repo, workspace сам **не** под версионкой.

```
/Users/dskripchenko/www/forge/laravel-admin/        # workspace root (НЕ git)
│
├── core/                                           # repo: dskripchenko/laravel-admin
│   ├── src/
│   ├── resources/ts/                               # core frontend + Quill/Tinymce subpath
│   ├── tests/
│   ├── config/
│   ├── database/
│   ├── routes/
│   ├── docs/
│   ├── examples/
│   ├── public/
│   ├── composer.json
│   ├── package.json
│   ├── vite.config.ts
│   ├── phpstan.neon
│   ├── pint.json
│   └── README.md
│
├── starter/                                        # repo: laravel-admin-starter
├── health/                                         # repo: laravel-admin-health
├── jobs/                                           # repo: laravel-admin-jobs
├── media/                                          # repo: laravel-admin-media
├── pulse/                                          # repo: laravel-admin-pulse
├── search/                                         # repo: laravel-admin-search
├── quill/                                          # repo: laravel-admin-quill   (PHP-only)
├── tinymce/                                        # repo: laravel-admin-tinymce (PHP-only)
│   (структура каждого: src/ tests/ config/ composer.json phpunit.xml.dist README.md ...)
│
├── demo/                                           # repo: laravel-admin-demo
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── deploy/
│   ├── public/
│   ├── resources/
│   ├── composer.json
│   ├── package.json
│   └── README.md
│
├── .gitignore                                      # ignore *  except workspace tooling
├── README.md                                       # описание workspace и команд
├── Makefile                                        # batch-команды на все 10
└── workspace.code-workspace                        # multi-root VSCode (опционально)
```

### Локальная dev-композиция

`demo/composer.json` для локальной разработки имеет dev-time переопределение через `repositories: path`, чтобы изменения в core/sister-packs подхватывались моментально без публикации:

```json
{
    "require": {
        "dskripchenko/laravel-admin": "^1.0",
        "dskripchenko/laravel-admin-starter": "^1.0",
        "dskripchenko/laravel-admin-health": "^1.0",
        "dskripchenko/laravel-admin-jobs": "^1.0",
        "dskripchenko/laravel-admin-media": "^1.0",
        "dskripchenko/laravel-admin-pulse": "^1.0",
        "dskripchenko/laravel-admin-search": "^1.0",
        "dskripchenko/laravel-admin-quill": "^1.0",
        "dskripchenko/laravel-admin-tinymce": "^1.0"
    },
    "repositories": [
        { "type": "path", "url": "../core",    "options": { "symlink": true } },
        { "type": "path", "url": "../starter", "options": { "symlink": true } },
        { "type": "path", "url": "../health",  "options": { "symlink": true } },
        { "type": "path", "url": "../jobs",    "options": { "symlink": true } },
        { "type": "path", "url": "../media",   "options": { "symlink": true } },
        { "type": "path", "url": "../pulse",   "options": { "symlink": true } },
        { "type": "path", "url": "../search",  "options": { "symlink": true } },
        { "type": "path", "url": "../quill",   "options": { "symlink": true } },
        { "type": "path", "url": "../tinymce", "options": { "symlink": true } }
    ]
}
```
> Эта секция `repositories` живёт ТОЛЬКО в локальном клоне demo (не коммитим в `dskripchenko/laravel-admin-demo`). Для production-deploy demo'a — стандартный `composer install` тянет с Packagist.

### Frontend в demo

`demo/package.json`:
```json
{
    "dependencies": {
        "@dskripchenko/laravel-admin": "file:../core"
    }
}
```
> На production-deploy: `"@dskripchenko/laravel-admin": "^1.0"` (с npm). Локально через `file:` или `npm link ../core`.

### Workspace tooling

`Makefile` в корне workspace для batch-операций по всем 10 репо:

```makefile
PACKS := core starter health jobs media pulse search quill tinymce demo
PHP_PACKS := core starter health jobs media pulse search quill tinymce

install:
	@for d in $(PACKS); do \
	  echo "==> $$d"; (cd $$d && composer install); \
	done
	@cd core && npm install
	@cd demo && npm install

test:
	@for d in $(PHP_PACKS); do \
	  echo "==> phpunit $$d"; \
	  if [ "$$d" = "core" ]; then (cd $$d && vendor/bin/pest); \
	  else (cd $$d && vendor/bin/phpunit); fi; \
	done
	@cd core && npm test

stan:
	@for d in $(PHP_PACKS); do \
	  echo "==> phpstan $$d"; (cd $$d && vendor/bin/phpstan analyse --memory-limit=512M); \
	done

pint:
	@for d in $(PHP_PACKS); do \
	  echo "==> pint $$d"; (cd $$d && vendor/bin/pint); \
	done

status:
	@for d in $(PACKS); do \
	  echo "==> $$d"; (cd $$d && git status -sb); \
	done

pull:
	@for d in $(PACKS); do \
	  echo "==> pull $$d"; (cd $$d && git pull --ff-only); \
	done

demo:
	@cd demo && php artisan serve & cd demo && npm run dev

build-frontend:
	@cd core && npm run build
```

### VSCode multi-root workspace

`workspace.code-workspace`:
```json
{
    "folders": [
        { "path": "core",    "name": "🏛 core" },
        { "path": "starter", "name": "📦 starter" },
        { "path": "health",  "name": "📦 health" },
        { "path": "jobs",    "name": "📦 jobs" },
        { "path": "media",   "name": "📦 media" },
        { "path": "pulse",   "name": "📦 pulse" },
        { "path": "search",  "name": "📦 search" },
        { "path": "quill",   "name": "📦 quill" },
        { "path": "tinymce", "name": "📦 tinymce" },
        { "path": "demo",    "name": "🚀 demo" }
    ],
    "settings": {
        "files.exclude": {
            "**/vendor": true,
            "**/node_modules": true,
            "**/dist": true,
            "**/.phpunit.cache": true
        }
    }
}
```

### Workspace `.gitignore`

```
# Workspace сам не под git — все 10 подкаталогов независимы.
# Этот .gitignore защищает от случайного `git init` в корне workspace.
*
!.gitignore
!README.md
!Makefile
!SPLIT_PLAN.md
!workspace.code-workspace
```

## Risk checklist

- [ ] После Stage 0 build/tests должны остаться green перед split — иначе откат
- [ ] При extract'е каждого pack проверить отсутствие hidden зависимостей от неперенесённых файлов (например, `Dskripchenko\LaravelAdmin\Tests\...` namespace в pack-тестах)
- [ ] composer.json packs строго `require` Packagist-версию core (`^1.0`), а НЕ `@dev` — на свежем install сломается иначе
- [ ] Порядок публикации на Packagist: сначала core, потом packs (packs зависят от core)
- [ ] Перед удалением `packages/` в core — backup всего monorepo (zip)
- [ ] CI/CD — на момент написания плана нет; добавить GitHub Actions (тесты + Pint + PHPStan) в каждый repo на отдельной задаче после split-а
