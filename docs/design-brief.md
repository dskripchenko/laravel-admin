# Design Brief: laravel-admin SPA

> Этот документ — input для design-сессии в Claude. Скопируй весь файл в
> новый чат с дизайн-системой. Он самодостаточный: вся информация о
> продукте, экранах, состояниях и компонентах внутри.

---

## 1. Контекст продукта

**Что:** `dskripchenko/laravel-admin` — конструктор админ-панелей для
Laravel-приложений. Похож по позиционированию на Filament / Nova / Backpack /
MoonShine, но с собственным фронтендом и backend-стеком.

**Как работает:** разработчик-пользователь декларативно описывает
Resource-классы (модели + поля + колонки + фильтры + actions) на PHP. Backend
компилирует это в JSON-манифест и REST API. Наша задача — построить SPA,
которая по этому манифесту автоматически рендерит весь интерфейс.

**Ключевое отличие от типовых админок:** UI рендерится **из JSON**, не из
жёстко закодированных страниц. Манифест содержит структуру (`type`, `props`,
`children`), фронт парсит и собирает Vue-компоненты. Это значит дизайн
должен быть **системным** — каждый компонент должен работать в любом
контексте, не только на «странице товаров».

**Целевая аудитория конечного юзера:**
- Контент-менеджеры, операторы, support-команды.
- Интенсивная работа с табличными данными: фильтры, массовые операции,
  inline-edit, экспорты.
- Каждый день, несколько часов в день. Скорость + плотность важнее
  «вау-эффекта».

**Технологический контекст** (для понимания, не для отрисовки):
- Vue 3 + TypeScript + Pinia + vue-router.
- Используем собственный UI-кит `@dskripchenko/ui` (70+ Vue-компонентов с
  CSS-vars). Дизайн-система, в которую попадёт результат, унаследует
  его токены — но в рамках этого design-брифа можно работать со своими.
- WYSIWYG — Tiptap. Загрузка файлов — drag-n-drop.

---

## 2. Цели дизайна

1. **Информационная плотность.** Это рабочий инструмент. Таблица должна
   уметь показать 30+ строк × 8+ колонок без скролла на 1080p.
2. **Скорость операций.** Все типичные действия — за 1-2 клика. Bulk-actions,
   keyboard-shortcuts, inline-edit, saved-views.
3. **Низкая когнитивная нагрузка.** Длительные сессии. Минимум анимаций,
   минимум всплывающего шума, чёткая иерархия.
4. **Гибкость.** Пользователь может настроить под себя: видимость колонок,
   порядок, ширина, сохранённые фильтры, кастомизация dashboard'а.
5. **Light + Dark — равноправные.** Не «тёмная тема как пристройка», а две
   полноценные палитры. Большинство админов работают в dark.
6. **Доступность.** Контраст ≥ 4.5:1 для основного текста, focus-states на
   всех интерактивных элементах, keyboard navigation, screen-reader labels.

---

## 3. Информационная архитектура

### Глобальная навигация (всегда видна в shell'е)

```
Sidebar (collapsible)
├── Brand / Logo
├── Tenant switcher (опционально, если multi-tenant)
├── Главное меню — генерируется из manifest:
│   ├── Группа "Контент"
│   │   ├── Articles (Resource)
│   │   ├── Categories (Resource)
│   │   └── Media (Resource)
│   ├── Группа "Аналитика"
│   │   └── Dashboard (custom Screen)
│   └── Группа "Настройки"
│       ├── Users (Resource)
│       ├── Roles (Resource)
│       └── Blog Settings (SettingsResource)
└── Footer: версия, ссылка на docs

Top bar
├── Breadcrumbs (auto-generated from route)
├── Global search (cmd+K) — опционально (sister-pack)
├── Notification bell (badge с unread count)
├── Theme switcher (light/dark/system)
├── Locale switcher (ru/en/...)
└── User menu (avatar) → Profile / Logout / Impersonate-banner
```

### Карта экранов

| # | Экран | Тип | Описание |
|---|---|---|---|
| 1 | **Login** | Public | Email + password + "remember me", «Забыли пароль?» |
| 2 | **2FA Challenge** | Public | После login если 2FA включена. 6-значный код или recovery-code |
| 3 | **Forgot Password / Reset** | Public | Двухстраничный флоу |
| 4 | **Email Verification** | Public | Информационная страница |
| 5 | **Resource List** | Protected | Таблица с фильтрами, sort, pagination, bulk |
| 6 | **Resource Create** | Protected | Форма с динамическими Field |
| 7 | **Resource Edit** | Protected | Та же форма + delete + custom-actions |
| 8 | **Resource View** | Protected | Read-only Infolist + AuditTrail |
| 9 | **Custom Screen / Dashboard** | Protected | Произвольный layout из widget'ов |
| 10 | **Settings (singleton)** | Protected | Форма без table-уровня |
| 11 | **Profile** | Protected | Личные данные + смена пароля + 2FA setup + API tokens |
| 12 | **Notification Center (drawer)** | Protected | Список уведомлений из bell |
| 13 | **Audit Trail (timeline)** | Protected | Встроенный layout на view-странице |
| 14 | **Import Wizard (4-step)** | Protected | Upload → Mapping → Preview → Run |
| 15 | **Saved Views Manager** | Protected | Modal/dropdown в shell таблицы |
| 16 | **403 / 404 / 500 / Maintenance** | Both | Стандартные error-страницы |

---

## 4. Детализация ключевых экранов

### 4.1. Login

- Centered card, max-width 400px.
- Поля: Email, Password, "Запомнить меня" чекбокс.
- Кнопка primary "Войти" (full width).
- Ссылка "Забыли пароль?" → reset-flow.
- Logo + product name в заголовке.
- В углу страницы — theme/locale switcher (доступны до логина).
- Состояния: idle, loading (кнопка spin'ит), error (под полями: неверные
  данные, заблокирован, rate-limit).

### 4.2. Shell (главный layout protected-страниц)

**Desktop (>1024px):**
- Левый sidebar 240px, fixed.
- Top bar 56px высотой, sticky.
- Контент: max-width none, padding 24px, scrollable.
- Sidebar collapsible до 56px (только icons) — состояние persists в localStorage.

**Tablet (640-1024px):**
- Sidebar по дефолту collapsed (only icons), на hover/click expand'ится overlay'ем.

**Mobile (<640px):**
- Sidebar полностью скрыт, кнопка-гамбургер в top-bar открывает overlay.
- Top-bar упрощён.

**Impersonation banner** (если активна): полоска в самом верху страницы,
warning-цвет, текст «Вы вошли как Имя Юзера» + кнопка «Выйти из режима».
Над всем — даже над top-bar.

### 4.3. Resource List

Самый важный экран — пользователь проводит здесь 70% времени.

**Header области:**
- Заголовок (например «Статьи») + count.
- Command bar справа: кнопка primary "Создать", меню «Ещё» (Export, Import).
- Saved views dropdown слева от Создать («Все», «Мои черновики», «Опубликованные» — пользовательские пресеты).

**Filters bar:**
- Горизонтальная панель под header'ом.
- Каждый Filter — компонент: input/select/date-range/options/switcher/trashed.
- Кнопка «Сбросить» если есть активные.
- Free-text search (?q=...) — отдельным полем с иконкой лупы.
- Группа filters справа: «Группировать по» (если group-by включён),
  «Колонки» (видимость + порядок), «Saved view» (сохранить текущий стейт).

**Bulk-toolbar** (появляется когда есть selected rows):
- Заменяет filters-bar или появляется поверх.
- Слева: count выбранных, чекбокс «выбрать все на странице» / «выбрать все 1234 записей».
- Справа: bulk-actions (Удалить, Опубликовать, Экспортировать, ...).

**Table:**
- Sticky header, virtualized scroll если >100 rows.
- Колонки: чекбокс selection (если есть bulk), затем data-колонки, в конце row-actions (kebab или inline-кнопки).
- Sort-индикаторы на sortable колонках.
- Inline-edit: двойной клик по ячейке → input-replacement, Enter сохранить, Esc отмена.
- Полосатость или плотные разделители — выбор.
- Hover на row подсвечивает её.
- Spec-presets для типов колонок:
  - `text` — обычный текст с truncate + tooltip на overflow
  - `badge` — цветной чип (статусы: active/draft/banned/archived)
  - `icon` — иконка вместо текста
  - `date` / `datetime` — форматированная дата с tooltip полной
  - `money` — выровнено по правому краю, с currency-символом
  - `boolean` — иконка ✓/✗ или Yes/No с цветом
  - `bytes` — "1.2 MB"
  - `image` — превью с фиксированным размером
  - `link` — clickable, открывает URL
  - `color` — swatch + hex рядом

**Footer таблицы:**
- Слева: pagination (1 2 3 ... 10 Next, либо infinite-scroll).
- Центр: per-page selector (25 / 50 / 100).
- Справа: total count, **summary row** (sum/avg/count/min/max по configured колонкам — отдельная sticky row под table).

**Group-by режим:**
- Если включён, перед table — chips-bar с count'ами по группам: «Active 142, Draft 23, Archived 5».
- Клик по chip фильтрует.

**Polling indicator:**
- Если Resource::polling включён — маленький pulsing-dot в header'е "обновлено N сек назад".

### 4.4. Resource Create / Edit Form

- Header: заголовок («Создать статью» / «Редактировать: Название»).
- Command bar: «Сохранить» (primary), «Сохранить и продолжить», «Отмена», «Удалить» (destructive, только в edit), custom-actions.
- Body: layout из manifest'а. Ключевые типы:
  - **Rows** — поля сверху-вниз.
  - **Columns** — горизонтальный grid (12 колонок, поле занимает N).
  - **Tabs** — вкладки.
  - **Block** — секция с заголовком + опциональным описанием.
  - **Group** — нескольких полей под одним именем (адрес: city + street).
  - **Repeater** — повторяющийся набор полей с add/remove/reorder.
- Sticky save-bar внизу при scroll'е длинной формы.
- Unsaved-changes prompt при попытке покинуть страницу.

**Field типы** (всего 30+, отрисовать как минимум представители каждой группы):
1. **Текстовые**: Input (text/email/url/password), Number, Textarea, Code (с подсветкой синтаксиса).
2. **Выбор**: Select (single + multi с searchable), Combobox (creatable), Radio, Checkbox (single + group), Switcher (toggle).
3. **Дата/время**: DatePicker, DateRange (с presets «Сегодня / Эта неделя»), TimePicker.
4. **Прочее**: ColorPicker (palette + hex/rgb), Slider (с marks), Rating (звёзды), FileUpload (drag-n-drop), ImageCropper.
5. **Связи**: RelationSelect (single/multi с async-search), RelationTable (inline-таблица HasMany), MorphSwitcher (type + id).
6. **Контент**: Wysiwyg (Tiptap toolbar + image-upload), Markdown (split-view edit/preview), Slug (auto-from), KeyValue (assoc array редактор), TagsInput.
7. **Иерархия**: TreeSelect, Cascader (страна → город), Builder (page-builder с blocks).
8. **Многоязычные**: TranslatableInput (вкладки/dropdown языков с input-ом внутри).

Каждый Field имеет: label, опциональный help-текст, required-индикатор, error-state (под полем красный текст), disabled-state.

### 4.5. Resource View (read-only)

- Layout — Infolist (read-only entries).
- Entries: TextEntry, BadgeEntry, IconEntry, ColorEntry, KeyValueEntry, RepeatableEntry, ImageEntry, RelationEntry (clickable), MapEntry.
- Опционально внизу — AuditTrail timeline (см. ниже).

### 4.6. Dashboard

- 12-column grid из widget'ов.
- Каждый widget занимает size = 1..12 колонок.
- Drag-n-drop reorder + resize (опционально).
- Widget типы:
  - **StatsOverview** — горизонтальная панель из KPI-карточек (label + value + delta-trend + иконка/цвет).
  - **Chart** — линейный/столбчатый/круговой/area/radar.
  - **RecentList** — таблица последних N записей с linkTo.
  - **Table** — полноценная таблица как widget.
  - **Heatmap** — двумерная матрица (день недели × час).
  - **Gauge** — спидометр с зонами (green/yellow/red).
  - **Markdown** — статический текстовый блок.
  - **Iframe** — embed Grafana/status-page.
- В каждом widget'е: title-bar + опциональный refresh-indicator + меню «Скрыть/Настроить».

### 4.7. Profile

Левый sidebar с табами: «Основное», «Безопасность», «API токены».

- **Основное:** name, email, locale, theme, avatar.
- **Безопасность:** смена пароля (current + new + confirm), 2FA setup
  (QR-код с otpauth-URI + secret для manual-ввода + recovery codes), кнопка
  «Отключить 2FA» с re-auth.
- **API токены:** таблица токенов (name, abilities, last_used_at, expires_at,
  кнопка Revoke), кнопка «Создать токен» → modal с name + abilities (multi-select)
  + expires_in_days. После создания — alert с plain-text token «Скопируйте сейчас,
  больше не покажем».

### 4.8. Notification Center (drawer)

Right-side drawer 400px ширины, появляется при клике на bell.

- Header: «Уведомления (3)», кнопка «Прочитать все», иконка settings (опц).
- Tabs: «Все» / «Непрочитанные» / «Прочитанные».
- Список items: иконка по level (info/success/warning/error), title (bold если unread), body (1-2 строки), timestamp (relative «5 мин назад»).
- Click по item → переход на url (если задан) + автоматически markAsRead.
- Hover показывает кнопку «×» удалить.
- Empty-state: «Нет уведомлений» + иллюстрация.

### 4.9. Import Wizard (4 шага)

Полноэкранный wizard. В header — progress-indicator из 4 шагов с текущим highlighted.

1. **Загрузка файла** — drop-zone + accept (CSV/TSV/XLSX) + max-size.
2. **Сопоставление колонок** — двухколоночный layout: слева headers из файла + sample value, справа dropdown с Field-name. Auto-mapping подсвечен зелёным.
3. **Предпросмотр** — таблица первых 20 строк с применённым mapping'ом + warnings под невалидными.
4. **Импорт** — progress-bar + counter "Обработано N / Создано M / Ошибок K". После завершения — список ошибок с возможностью скачать как CSV.

Кнопки snizu: «Назад» / «Далее» / «Запустить» / «Закрыть».

### 4.10. Audit Trail (timeline)

Встраиваемый компонент на view-странице.

- Vertical timeline.
- Каждое событие — карточка: иконка по типу события, actor avatar+name, summary («Изменил статус с Draft на Published»), timestamp, expandable diff (before/after table).
- Group by date headers.
- Filter в header: actor, event-type, date-range.
- Pagination или infinite scroll.

---

## 5. Состояния и edge cases

**Каждый экран должен проработать:**
- **Loading** — skeleton-loader (не spinner) для tables/forms; для actions — кнопка с inline-spinner.
- **Empty** — иллюстрация + текст «Записей нет» + кнопка-CTA «Создать первую».
- **Error** — toast (для action-fail), inline message (для form validation), full-page (для 500/network).
- **Permission denied** — кнопки/items не отображаются вообще (не disabled).
- **Network offline** — banner сверху «Соединение потеряно» + retry кнопка.
- **Long content** — truncate с tooltip на full text.
- **Large lists** — virtualized scroll, не lazy-pagination.

**Toasts:**
- Top-right corner.
- Уровни: info (нейтральный), success (зелёный), warning (жёлтый), error (красный).
- Auto-dismiss 5 сек, hover паузит.
- Stack с reorder.

**Confirmation modals:**
- Destructive actions (delete, force-delete) — обязательно с modal'ом.
- Текст: title + body (обоснование) + 2 кнопки: «Отмена» (secondary) + «Удалить» (destructive primary).
- Опционально: input-confirmation «Введите DELETE чтобы подтвердить».

**Modal-actions с формой** (например «Отправить уведомление»):
- Modal с fields внутри, submit идёт на server.
- Validation errors показываются в modal'е, не закрывая его.

---

## 6. Design system constraints

- **Цвета:** light + dark, две полные палитры.
  - Brand-color из config'а (не hard-coded).
  - Status colors: success/info/warning/error.
  - Neutral scale (50-900) для backgrounds, borders, text.
  - Контраст ≥ 4.5:1 на основном тексте.
- **Типографика:** один sans-serif стек. Размеры: 12 (caption), 14 (body), 16 (title-md), 20 (title-lg), 24 (h1).
  Веса: 400 / 500 / 600.
- **Spacing:** шкала 4-8-12-16-24-32-48 px.
- **Radius:** 4 / 6 / 8 / 12 px.
- **Иконки:** outline-style, 16/20/24px размеры. Бесплатный набор (Lucide / Heroicons).
- **Анимации:** ≤200ms, easing ease-out. Не отвлекают.
- **Density:** comfortable (default) и compact (опц. в settings).

---

## 7. Что нужно на выходе

Не финальный код продакшена — wireframe-уровень с проработанной UX-логикой:

1. **Полноценный design** для 5 ключевых экранов: Login, Shell (с sidebar
   развёрнутым и свёрнутым), Resource List (с filters, bulk-mode, group-by
   режимом), Resource Form, Dashboard.
2. **Вторичные экраны** одной картинкой: Resource View, Profile, Settings,
   Import Wizard (4 шага), Notification Drawer, 2FA Challenge.
3. **Компонентная библиотека**:
   - Все Field-типы (по 1 примеру каждого).
   - Все Layout-типы (Tabs, Modal, Wizard, Accordion, Drawer и т.д.).
   - Все Action типы (Button primary/secondary/destructive, BulkAction, ModalAction, DropDown).
   - Все Widget типы.
3. **States:** идеальные + loading + empty + error для каждого ключевого
   экрана.
4. **Light + dark** для всех экранов.
5. **Mobile-вариант** для Shell + Resource List + Form (как минимум).
6. **Iconography** — какие иконки используем для каких action'ов и
   статусов.
7. **Design tokens** — экспортируемые в CSS-vars (для интеграции с
   `@dskripchenko/ui`).

---

## 8. Out of scope

- Не дизайнить landing-page продукта (это отдельный сайт пакета).
- Не дизайнить CLI/admin-tool страницы (admin:install, admin:user — терминал).
- Не делать иллюстрации (используем Storyset / Undraw / иконки).
- Не дизайнить email-шаблоны (transactional emails — отдельная задача).
- Sister-packs (cmd+K search, мedia library, healthchecks UI и др.) — не сейчас.

---

## 9. Источники / референсы

Положительные:
- **Linear** — плотность, скорость, keyboard-driven, минимализм.
- **Filament** (Laravel admin) — подходящая UX-парадигма для нашего домена.
- **Notion** — отличные inline-edit паттерны.
- **Supabase Studio** — табличный workflow.

Что **не делаем**:
- Не Material Design (слишком шумный для рабочего инструмента).
- Не Tailwind UI (слишком generic).
- Не yandex/sber-style (слишком крупная типографика, мало плотности).
