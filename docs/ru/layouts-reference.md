---
title: Каталог layout'ов
audience: developer
status: stable
locale: ru
translated_from: en/layouts-reference.md
translated_at: 2026-10-02
---

# Каталог layout'ов

Layout — это контейнер для полей и других layout'ов, который умеет себя
отрисовать. Layout'ы вкладываются друг в друга на любую глубину.

## Краткая сводка

| Класс | Фабрика | Назначение |
|---|---|---|
| `Rows` | `Layout::rows([...])` | Вертикальный стек |
| `Columns` | `Layout::columns([...])` | Колонки равной ширины |
| `Block` | `Layout::block($title, [...])` | Секция с заголовком |
| `Tabs` | `Layout::tabs(['Label' => [...], ...])` | Вкладки |
| `Wizard` + `Step` | `Layout::wizard([Layout::step(...), ...])` | Многошаговая форма |
| `Modal` | `Layout::modal($title, [...])` | Содержимое в модальном окне |
| `Drawer` | `Layout::drawer($title, [...])` | Выезжающая боковая панель |
| `Wrapper` | `Layout::wrapper([...])` | Простая группа-`<div>` |
| `Accordion` | `Layout::accordion(['Section' => [...]])` | Сворачиваемые секции |
| `Infolist` | `Layout::infolist([...])` | Вывод «ключ — значение» только для чтения |
| `Dashboard` | `Layout::dashboard([...])` | Сетка в 12 колонок (её использует `DashboardScreen`) |
| `View` | `Layout::view('component-name', $props)` | Собственный Vue-компонент |
| `Markdown` | `Layout::markdown($text)` | Отрисованный markdown: заголовки с якорями, оглавление, подсвеченный код, таблицы, выноски |
| `Code` | `Layout::code($code, 'php')` | Подсвеченный блок кода с копированием |
| `Flow` | `Layout::flow('process')` | Процесс или машина состояний с живым мониторингом, переключателями и переходом в подпроцессы |
| `Diagram` | `Layout::diagram('schema')` | Диаграмма из текста: Mermaid, PlantUML, DOT, D2, DBML, Structurizr, карта Уордли |
| `Bpmn` / `Dmn` | `Layout::bpmn('process_xml')` | Модель процесса BPMN или решений DMN, только чтение, с панорамой, масштабом и подсветкой элементов |
| `Drawio` | `Layout::drawio('network')` | Файл draw.io (diagrams.net), нарисованный как SVG |
| `AsyncApi` | `Layout::asyncapi('spec')` | Справочник AsyncAPI: серверы, каналы, операции, сообщения |
| `Kanban` | `Layout::kanban('board')` | Канбан-доска в разметке, редактируемая через метод экрана |
| `Calendar` | `Layout::calendar('schedule')` | Календарь событий с повторами в разметке, редактируемый через метод экрана |
| `Video` | `Layout::video('/media/intro.mp4')` | Видеоплеер: качества, HLS, субтитры, главы, превью кадров |
| `Audio` | `Layout::audio([...])` | Аудиоплеер: дорожка или плейлист, волновая форма, мини-вариант |
| `AuditTrail` | `AuditTrail::for(User::class)` | Лента аудита показанной записи |
| `Listener` | `Layout::listener([...])->listen([...])` | Часть формы, которую сервер перерисовывает при изменении отслеживаемых полей |
| `ResourceTable` | `ResourceTable::for(ItemResource::class)` | Таблица записей другого ресурса, принадлежащих редактируемой |
| `ResourceIndex` | `Layout::resourceIndex(OrderResource::class)` | Живая страница списка ресурса (таблица или дерево) на экране |

## Примеры

### Rows

```php
Rows::make([
    Input::make('title'),
    Textarea::make('body'),
    Select::make('status')->options([...]),
]),
```

### Columns

```php
Columns::make([
    Input::make('first_name'),
    Input::make('last_name'),
])->ratios([1, 1]),
```

Для колонок разной ширины — `->ratios([2, 1])`.

### Block (секция с заголовком)

```php
Block::make('Profile', [
    Input::make('name'),
    Input::make('email'),
])->description('Personal data'),
```

### Tabs

```php
Tabs::make([
    'General' => [
        Input::make('title'),
        Textarea::make('description'),
    ],
    'SEO' => [
        Input::make('meta_title'),
        Textarea::make('meta_description'),
    ],
    'Translations' => [
        TranslatableInput::make('title'),
    ],
]),
```

Активная вкладка хранится локально на странице; при переключении
состояние формы не теряется.

### Wizard (многошаговая форма)

```php
Layout::wizard([
    Layout::step('Account', [
        Input::make('email')->required(),
        Input::make('password')->type('password')->required(),
    ]),
    Layout::step('Profile', [
        Input::make('name'),
        DatePicker::make('birthday'),
    ]),
    Layout::step('Done', [
        Layout::view('summary-step', ['fields' => [...]]),
    ]),
]),
```

Фронтенд рисует шапку `UidStepper` с кнопками «Назад»/«Далее». Переход
вперёд возможен только после валидации: поля шага проверяются по их
собственным правилам (`required`, `email`, `numeric`, `integer`, `min`,
`max`, `in`; всё остальное проверяет сервер) и по правилам шага из
`->rules([...])`.

```php
Layout::wizard([...])
    ->submit('finish')          // the last step's button calls this screen method
    ->freeForm()                // steps may be visited in any order
    ->persistKey('onboarding'), // progress survives a reload (localStorage)
```

Без `freeForm()` в степпере кликабельны только уже пройденные шаги. С
`persistKey()` текущий шаг и введённые значения сохраняются в браузере до
отправки визарда; пароли и файлы не сохраняются никогда.

### Modal / Drawer

Для action'ов:

```php
ModalAction::make('Set price')
    ->method('setPrice')
    ->fields([
        Number::make('price')->required(),
    ]),
```

Или как layout на Screen'е, который открывается action'ом, ссылающимся на
его id:

```php
public function layout(): array
{
    return [
        Layout::modal('Edit', [
            Input::make('title'),
        ])
            ->withId('edit-modal')
            ->size('lg')               // sm | md | lg | xl | full
            ->dismissable(false)       // no cross, no overlay click, no Escape
            ->footer([
                Button::make('Cancel')->withName('cancel'),
                Button::make('Save')->method('save')->primary(),
            ]),
    ];
}

public function commandBar(): array
{
    return [Button::make('Edit')->opens('edit-modal')];
}
```

Action в футере, у которого есть метод, закрывает окно после успешного
выполнения метода; action с именем `close` или `cancel` просто закрывает
его. `Layout::drawer()` работает так же — включая `->dismissable()` и
`->footer([...])`, — плюс `->position('left'|'right'|'top'|'bottom')` и
`->size()` (`sm`, `md`, `lg`, `xl` или CSS-длина).

### Wrapper

Простой элемент, на который можно повесить CSS:

```php
Layout::wrapper([
    Input::make('title'),
    Input::make('slug'),
])->className('two-col-grid')->tag('section'),
```

`tag()` принимает `div` (по умолчанию), `section`, `article`, `aside`,
`header`, `footer`, `main`, `nav`, `fieldset` и `span`.

### Accordion

```php
Layout::accordion([
    'Personal' => [Input::make('name')],
    'Billing' => [Input::make('card_last4')->readonly()],
])->multi(),
```

`->multi()` позволяет держать открытыми несколько секций сразу. Изначально
все секции свёрнуты; `->section('Title', [...], defaultOpen: true)`
добавляет секцию, открытую с самого начала.

### Infolist (только чтение)

Для режима `view` (`ResourceViewPage`, собственный Screen):

```php
Layout::infolist([
    TextEntry::make('title'),
    BadgeEntry::make('status')->colors(['published' => 'success', 'draft' => 'default']),
    KeyValueEntry::make('meta'),
])->layout('rows'),  // or 'columns', 'grid'
```

Типы записей: `TextEntry`, `BadgeEntry`, `IconEntry`, `KeyValueEntry`,
`ImageEntry`, `RelationEntry`, `RepeatableEntry`, `MapEntry`,
`ColorEntry`.

`FlowEntry` показывает сохранённую модель процесса только для чтения (вид [поля `Flow`](fields-reference.md#flow)): `FlowEntry::make('workflow')->height(420)`.

`VideoEntry` и `AudioEntry` проигрывают файл записи — URL, путь в хранилище (с `disk()`), загруженный файл или список: `AudioEntry::make('call_recording')->disk('local')` (однострочный мини-плеер; `->full()->waveform()` — полный), `VideoEntry::make('clip_path')->disk('public')->posterFrom('cover_path')`. См. [Video и Audio](#video-и-audio-медиаплееры).

На собственном Screen'е записи читают state экрана.

### AuditTrail

```php
AuditTrail::for(\App\Models\User::class)
    ->fromState('user_id')          // the state key holding the id; 'id' by default
    ->limit(20)
    ->withPermission('admin.audit'),
```

Показывает ленту аудита записи (`GET /audit/timeline`); пока в state нет
id, ничего не выводится.

### Dashboard

```php
Layout::dashboard([
    StatsOverviewWidget::make()->title('Articles')->size(3),
    ChartWidget::make()->title('Daily')->size(8)->rowSpan(2),
    // ...
]),
```

(Его использует `DashboardScreen`, но layout Dashboard можно разместить
внутри любого Screen'а.)

### Listener (реактивная часть формы)

Listener следит за некоторыми полями формы. Когда одно из них меняется,
SPA дожидается паузы во вводе (по умолчанию 300 мс), отправляет текущее
состояние формы на сервер и заменяет дочерние элементы listener'а теми,
что сервер отрисовал для этого состояния. Необязательный обработчик может
также вернуть значения, которые вольются в форму.

```php
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Layout\Layout;

public function layout(): array
{
    return [
        Layout::rows([
            Select::make('country_id')->fromModel(Country::class),

            // Children as a closure: rendered against the current state.
            Layout::listener(fn (array $state) => [
                Select::make('city_id')
                    ->title('City')
                    ->fromModel(City::where('country_id', $state['country_id'] ?? null)),
            ])->listen('country_id'),

            Number::make('price'),
            Number::make('quantity'),

            // A handler computing values: a public method of the screen.
            Layout::listener([
                Number::make('total')->readonly(),
            ])->listen(['price', 'quantity'])->handler('recalculateTotal'),
        ]),
    ];
}

/** Returns a patch: the keys of the form state to change. */
public function recalculateTotal(array $state, Request $request): array
{
    return ['total' => round((float) ($state['price'] ?? 0) * (int) ($state['quantity'] ?? 0), 2)];
}
```

| Метод | Назначение |
|---|---|
| `Layout::listener(array\|Closure $children)` | Статичные дочерние элементы или `fn (array $state): array` |
| `->listen(string\|array $fields)` | Имена отслеживаемых полей |
| `->handler(string\|Closure $handler)` | Публичный метод screen'а/ресурса или замыкание; вызывается как `(array $state, Request $request)` и возвращает патч состояния (`array`, `Repository` или `null`) |
| `->debounce(int $ms)` | Пауза перед запросом, по умолчанию 300 |
| `->withId(string $id)` | Явный id; нужен, только если два listener'а с обработчиками-замыканиями следят за одними и теми же полями |

Что происходит при каждом изменении: обработчик запускается с присланным
состоянием, его патч вливается в это состояние, и дочерние элементы
отрисовываются по результату. Ответ содержит и патч, и дочерние элементы.
SPA вливает патч в форму (пропуская поля, которые пользователь успел
изменить, пока шёл запрос), заменяет дочерние элементы на месте — поля,
оставшиеся на своих позициях, сохраняют фокус, — отменяет устаревшие
запросы и показывает ошибки тостом (а для `ValidationException` — под
полями).

**В форме ресурса** listener кладётся в `formLayout()`, а строковый
обработчик — это публичный метод Resource:

```php
public function formLayout(string $context): array
{
    return [
        Select::make('country_id')->fromModel(Country::class),
        Layout::listener(fn (array $state) => [
            Select::make('city_id')->fromModel(City::where('country_id', $state['country_id'] ?? null)),
        ])->listen('country_id'),
        Layout::listener([Number::make('total')->readonly()])
            ->listen(['price', 'quantity'])
            ->handler('recalculateTotal'),
    ];
}

public function recalculateTotal(array $state): array
{
    return ['total' => ($state['price'] ?? 0) * ($state['quantity'] ?? 0)];
}
```

Валидация и сохранение по-прежнему читают `fields()`: поле, которое
рисует listener, должно быть объявлено и там. Манифест строится без
записи, поэтому замыкание сначала получает пустое состояние и должно
переживать отсутствие ключей. Если форма редактирования открывается с уже
заполненными отслеживаемыми полями, listener один раз просит сервер
отрисовать дочерние элементы для этой записи (не меняя значений).

**Эндпоинт и безопасность.** Screen'ы получают
`POST /api/admin/{screen}/listener` под `permission()` экрана; ресурсы —
`POST /api/admin/{resource}/listener` (только если в их форме есть
listener'ы), который требует `view` плюс `create` или `update` в
зависимости от контекста формы. Тело запроса — `{listener, state, context?, id?}`,
ответ — `{listener, state, layouts}`. Запрос называет listener по его id и
никогда не называет метод: запустить можно только обработчики, объявленные
listener'ом в `layout()` этого экрана или `formLayout()` этого ресурса, а
зарезервированные методы screen'а (`query`, `layout`, …) отклоняются.

При обслуживании запроса listener'а `layout()` экрана вызывается без
`query()`, поэтому listener'ы не должны зависеть от свойств, которые
выставляет `query()`.

### ResourceTable (встроенная таблица другого ресурса)

```php
use Dskripchenko\LaravelAdmin\Layout\ResourceTable;

public function formLayout(string $context): array
{
    if ($context !== 'update') {
        return [];
    }

    return [
        Layout::tabs([
            'Основное' => $this->fields(),
            'Элементы' => [
                ResourceTable::for(DictionaryItemResource::class)
                    ->foreignKey('dictionary_id')   // колонка дочерней записи, указывающая на родителя
                    ->parentField('id')             // колонка родителя, которую она хранит; по умолчанию 'id'
                    ->hideColumns(['dictionary_id'])
                    ->features(['create' => true, 'delete' => true, 'bulkDelete' => true]),
            ],
        ]),
    ];
}
```

Показывает на странице редактирования родителя записи дочернего ресурса,
которые ему принадлежат. Колонки берутся из манифеста дочернего ресурса (без
перечисленных в `hideColumns()`), до 100 строк загружаются через
`POST /{child}/search` с `filters: {foreign_key: parent[parent_field]}`, а
ячейки колонок с `editable()` редактируются прямо в таблице. `features()`
включает (по умолчанию всё выключено):

- `create` — строку-черновик сверху, которая сохраняется через
  `POST /{child}/create` с уже заполненным внешним ключом;
- `delete` — кнопку удаления в каждой строке (`POST /{child}/delete`);
- `bulkDelete` — выбор строк и массовое удаление.

Каждый запрос проверяется по правам самого дочернего ресурса (`.view`,
`.create`, `.update`, `.delete`).

Внешний ключ попадает в запрос только через фильтр, объявленный в дочернем
ресурсе на эту колонку, — без него таблица покажет записи всех родителей.
Объявите в дочернем ресурсе фильтр с точным совпадением:

```php
public function filters(): array
{
    return [
        QueryFilter::for('dictionary_id')
            ->using(fn ($query, $value) => $query->where('dictionary_id', $value)),
    ];
}
```

Размещайте таблицу в `formLayout('update')`: у создаваемой записи ещё нет
ключа, и до сохранения таблица пуста.

### ResourceIndex (живой список ресурса на экране)

```php
use Dskripchenko\LaravelAdmin\Layout\Layout;

public function layout(): array
{
    return [
        Layout::markdown('Что показывает таблица…'),
        Layout::resourceIndex(OrderResource::class),
    ];
}
```

Сама страница списка ресурса — поиск, фильтры, сортировка, действия над
строками и выделением, правка в ячейках, порядок, корзина или дерево для
иерархического ресурса — встроенная в экран. Щелчок по строке открывает
запись, как на странице списка. Пользователю без права `view` на ресурс не
показывается. Одна на экран: состояние списка общее.

### Markdown

Блок markdown, который рисует встроенный рендерер панели. Исходник
экранируется до того, как появляется разметка, поэтому сырой HTML
показывается текстом и никогда не выполняется; ссылки и картинки могут вести
только на http(s), `mailto:`, относительные адреса и якоря.

```php
Layout::markdown(file_get_contents(base_path('docs/ru/getting-started.md')))
    ->toc()                              // оглавление по заголовкам h2/h3
    ->linkBase('/admin/screens/')        // куда ведут относительные ссылки
    ->imageBase('/docs-assets/')         // откуда грузятся относительные картинки
```

`Layout::markdown()` принимает и callable — он вызывается при сериализации
экрана; удобно, когда текст читается с диска или из базы:

```php
Layout::markdown(fn () => Page::whereSlug($slug)->value('body'))
```

Текст уходит как есть: языковую версию выбирайте сами, например по
`app()->getLocale()`.

Что умеет рендерер:

| Синтаксис | Результат |
|---|---|
| `# Заголовок` … `###### Заголовок` | Заголовки с якорями: `## Быстрый старт` → `#быстрый-старт` (буквы любого алфавита сохраняются, повторы получают `-1`, `-2`) |
| Блоки кода в тройных обратных кавычках с языком | Подсвеченный код с кнопкой копирования; язык берётся из ограждения (`php`, `js`, `ts`, `json`, `bash`, `sql`, `html`, `vue`, `css`, …) |
| Блоки ` ```mermaid ` | Диаграммы в синтаксисе Mermaid: flowchart, sequence, state, ER, классы, C4, pie, gantt, timeline, XY-график и интеллект-карта, рисуются в SVG пакетом [@dskripchenko/diagrams](https://github.com/dskripchenko/diagrams) |
| Блоки ` ```openapi ` | API-виджеты: запрос, описанный на YAML или JSON (или целый OpenAPI-документ), становится операцией с try-it, примерами кода, типами TypeScript и PDF — пакетом [@dskripchenko/openapi](https://github.com/dskripchenko/openapi) |
| Блоки ` ```flow ` | Процесс только для чтения: модель в JSON или исходник Mermaid `stateDiagram-v2` / `flowchart` — пакетом [@dskripchenko/flow](https://github.com/dskripchenko/flow) |
| Блоки ` ```plantuml `, ` ```dot `, ` ```d2 `, ` ```dbml `, ` ```structurizr `, ` ```wardley ` | Диаграммы в синтаксисе PlantUML, Graphviz DOT, D2, DBML, Structurizr DSL и карт Уордли (также ` ```puml `, ` ```graphviz `) — тем же пакетом |
| Блоки ` ```bpmn `, ` ```dmn `, ` ```drawio ` | Модели процессов и решений (BPMN 2.0, DMN 1.1–1.5, с таблицей каждого решения) и документы draw.io — картинкой |
| Блоки ` ```asyncapi ` | Справочник по документу AsyncAPI 2.x / 3.0: каналы, операции, сообщения |
| Блоки ` ```kanban `, ` ```calendar ` | Доска карточек и календарь событий с повторами, записанные текстом, — здесь только для чтения, в WYSIWYG-редакторе правятся на месте |
| `\| a \| b \|` + `\|---\|:---:\|` | Таблицы с выравниванием колонок |
| `> цитата` | Цитата |
| `> **Note** …`, `> **Внимание** …` | Выноска; также `Tip`/`Совет`, `Important`/`Важно`, `Caution`/`Осторожно` и форма GitHub `> [!NOTE]` |
| `**жирный**`, `*курсив*`, `~~зачёркнутый~~`, код в обратных кавычках | Строчная разметка |
| `[текст](url)`, `![alt](src)` | Ссылки и картинки |
| Списки `-`/`*`/`1.`, `---` | Списки и разделители |

**Диаграммы.** Блок кода с языком `mermaid` становится диаграммой —
`flowchart` (с подграфами), `sequenceDiagram`, `stateDiagram-v2`,
`erDiagram`, `classDiagram`, C4 (`C4Context`, `C4Container`, `C4Component`,
`C4Dynamic`, `C4Deployment`), `pie`, `gantt`, `timeline`, `xychart-beta` и
`mindmap`; их рисует в SVG пакет [@dskripchenko/diagrams](https://github.com/dskripchenko/diagrams)
в цветах и теме панели. У пакета нет зависимостей, и он загружается только на
страницах, где есть диаграмма. Подписи экранируются так же, как остальной
текст, поэтому диаграмма не может нести разметку или скрипты. Блок, который
не удалось прочитать, показывается как код с причиной и номером строки.
Поддерживаемый синтаксис перечислен в документации пакета.

**API-блоки.** Блок кода с языком `openapi` содержит запрос, описанный на
YAML или JSON — `method`, `url`, `params`, `auth`, `body`, `responses` или
`requests: [...]` для нескольких, — либо целый OpenAPI-документ. Он
становится живым виджетом [@dskripchenko/openapi](https://github.com/dskripchenko/openapi):
операция с параметрами и ответами, try-it, который отправляет запрос из
браузера читателя, примеры кода, типы TypeScript и PDF операции. Пакет
загружается, только когда на странице есть такой блок. Формат описан в
документации пакета (раздел «API blocks»).

**Блоки процессов.** Блок кода с языком `flow` содержит модель процесса в JSON (см. [Flow](#flow-процесс-с-живым-мониторингом)) или исходник Mermaid `stateDiagram-v2` / `flowchart`; он становится процессом только для чтения — панорама, масштаб, подпроцессы, детали шага. Пакет загружается, только когда на странице есть такой блок.

**Медиаблоки.** Блоки кода с языком `video` или `audio` становятся плеерами UI-кита. Источник — просто адрес (по одному на строку для плейлиста или источников видео), JSON или несколько строк YAML с опциями [Video и Audio](#video-и-audio-медиаплееры): `src`, `sources`, `poster`, `tracks`, `thumbnails`, `title`, `aspectRatio`, `maxWidth`, `autoplay`, `loop` для видео; `src` или `tracks`, `title`, `artist`, `waveform`, `peaks`, `mini` для аудио. В WYSIWYG-редакторе их вставляет меню `/video` и `/audio`.

**Другие нотации и виджеты.** Тот же блок кода рисует диаграмму в другом
синтаксисе: `plantuml` (или `puml`), `dot` (или `graphviz`), `d2`, `dbml`,
`structurizr`, `wardley`. `bpmn` и `dmn` принимают XML, который сохраняет
моделер (Camunda Modeler, bpmn.io и другие), — в файле BPMN должна быть его
раскладка; блок DMN показывает диаграмму требований и под ней таблицу каждого
решения. `drawio` принимает документ draw.io (`.drawio`, сжатый или нет).
`asyncapi` — документ AsyncAPI в YAML или JSON. `kanban` и `calendar`
принимают разметку — колонки и карточки, события с повторами словами
(«every month last fri») — в формате компонентов UI-кита
[`UidKanban`](https://github.com/dskripchenko/ui/blob/main/docs/ru/kanban.md) и
[`UidEventCalendar`](https://github.com/dskripchenko/ui/blob/main/docs/ru/event-calendar.md).
В поле WYSIWYG доска и календарь правятся прямо в блоке и записывают новую
разметку обратно. Каждый пакет загружается при первом использовании.

````markdown
```video
src: /media/intro.mp4
poster: /media/intro.jpg
tracks:
  - src: /media/intro.en.vtt
    srclang: en
    label: English
```
````

Хост добавляет рендереры для других языков так же, в `onAppCreated`:
`app.provide(markdownBlockRenderersKey, { ...builtinMarkdownRenderers, chart })`
(`markdownBlockRenderersKey` — из `@dskripchenko/ui`,
`builtinMarkdownRenderers` — из `@dskripchenko/laravel-admin`).

Настройки:

| Метод | Что делает |
|---|---|
| `toc(bool $enabled = true, int $depth = 3)` | Оглавление по заголовкам уровней 2…`$depth`: справа от текста на широком экране, над ним — на узком |
| `tocLabel(string $label)` | Подпись над оглавлением; по умолчанию «На этой странице» |
| `linkBase(string $base, bool $stripExtension = true)` | Относительные ссылки разрешаются от `$base` так же, как браузер разрешает их от `<base href>`: при `/admin/screens/` ссылка `docs-menu.md#items` откроет `/admin/screens/docs-menu#items`, а при `https://example.com/docs/ru/` ссылка `concepts/menu.md` — `https://example.com/docs/ru/concepts/menu`. Расширение `.md` отбрасывается, если `$stripExtension` не false. Ссылки, начинающиеся с `/`, `#` или схемы, не трогаются |
| `imageBase(string $base)` | То же для относительных путей картинок, расширения не трогаются |
| `card(bool $card = true)` | Рисует текст в карточке |

Slug экранов плоские — экран живёт по адресу `/admin/screens/{slug}`, и
ничего вложенного под ним нет, — поэтому страница многостраничного документа
— это отдельный экран, а ссылка между страницами должна называть slug
экрана-цели. Markdown, написанный под дерево файлов (`concepts/menu.md`,
`../intro.md`), сам на это не ложится: либо перепишите его ссылки в slug до
передачи текста (`concepts/menu.md` → `docs-concepts-menu`) и возьмите базой
`/admin/screens/`, либо направьте базу туда, где дерево лежит как есть, —
в репозиторий или на сайт документации.

Ссылки ведут себя как на сайте: якоря прокручивают страницу, ссылки внутри
панели переходят без перезагрузки, внешние открываются в новой вкладке.

### Code

Подсвеченный блок кода с кнопкой копирования:

```php
Layout::code(<<<'PHP'
    Layout::rows([
        Input::make('title')->required(),
    ]);
    PHP, 'php')
    ->title('app/Admin/Resources/PostResource.php')
    ->lineNumbers(),
```

| Метод | Что делает |
|---|---|
| `title(string $title)` | Подпись над кодом, например имя файла |
| `lineNumbers(bool $on = true)` | Номера строк |
| `maxHeight(int\|string $height)` | Прокрутка после этой высоты: `400` (px) или `'50vh'` |
| `wrap(bool $wrap = true)` | Переносить длинные строки вместо горизонтальной прокрутки |

### Flow (процесс с живым мониторингом)

Процесс или машина состояний, нарисованная
[@dskripchenko/flow](https://github.com/dskripchenko/flow): панорама, масштаб,
миникарта, панель деталей, живое состояние выполняющегося экземпляра (или
счётчики по всем экземплярам), переключатели, включающие и выключающие шаги, и
переход внутрь подпроцессов. Только чтение — модель редактируется
[полем `Flow`](fields-reference.md#flow). Пакет загружается, только когда на
странице есть процесс.

Модель — JSON пакета (`{version: 1, nodes, edges, groups?, meta?}`, см. его
документацию по модели). `Layout::flow()` принимает ключ state экрана или саму
модель — массив или JSON-строку, которая проверяется при построении layout'а
(`InvalidArgumentException` на сломанной):

```php
Layout::flow('process')                 // $state['process']
Layout::flow($order->workflow)          // модель массивом
Layout::flow(file_get_contents($path))  // или JSON
```

Полный пример — машина состояний заказа: опрос снимка заказа, выбранного в
селекте, включение и выключение шагов, подпроцесс по ссылке и кнопка в панели
деталей:

```php
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Resource\ActionFailedException;

final class OrderProcessScreen extends Screen
{
    public function query(mixed ...$params): array
    {
        return [
            'order' => request('order'),
            'process' => OrderProcess::model(),   // узлы: new, payment (subgraph: 'payment'), paid, shipped…
        ];
    }

    public function layout(): array
    {
        return [
            Layout::rows([Select::make('order')->options(Order::recentOptions())]),
            Layout::flow('process')
                ->poll('orderState', seconds: 5)
                ->onToggle('toggleStep', permission: 'orders.process.manage')
                ->subgraphs('loadSubprocess')
                ->instanceFrom('order')
                ->actions([
                    Button::make('Повторить шаг')->method('retryStep')->permission('orders.process.manage'),
                ])
                ->direction('LR')
                ->height(560),
        ];
    }

    /** Снимок для уровня на экране: корня или подпроцесса. */
    public function orderState(array $context): array
    {
        // $context = ['path' => ['payment'], 'ref' => 'payment', 'instance' => '1042']
        $order = Order::find($context['instance']);

        return [
            'nodes' => [
                'new' => ['status' => 'done'],
                'payment' => ['status' => 'done'],
                'shipped' => ['status' => 'active', 'updatedAt' => $order->updated_at->toIso8601String()],
            ],
            'path' => ['start', 'new', 'payment', 'paid', 'shipped'],
            'current' => ['shipped'],
            'updatedAt' => now()->toIso8601String(),
        ];
    }

    /** ['kind' => 'node'|'edge', 'id' => 'cancelled', 'enabled' => false, 'path' => []] */
    public function toggleStep(array $change): void
    {
        if ($change['id'] === 'cancelled') {
            throw new ActionFailedException('Отмену нельзя выключить.');
        }
        OrderProcess::setEnabled($change['kind'], $change['id'], $change['enabled'], $change['path']);
    }

    public function loadSubprocess(string $ref, array $node): array
    {
        return OrderProcess::subprocess($ref);   // модель, как и корневая
    }

    /** ['kind' => 'node', 'id' => 'shipped', 'path' => [], 'instance' => '1042'] */
    public function retryStep(array $selection): array
    {
        RetryStep::dispatch($selection['instance'], $selection['id']);

        return ['message' => 'Шаг поставлен на повтор'];
    }
}
```

Методы — собственные публичные методы экрана, их вызывает его действие
`runMethod` с позиционными `parameters`: та же сессия, CSRF, слои и проверки,
что и у кнопки. Каждый названный layout'ом метод объявлен серверу:
пользователь, от которого layout скрыт (`canSee()`), получает 403 на все из
них, а `onToggle(..., permission:)` и кнопкам панели деталей нужно ещё и их
право — без него переключатели и кнопки не показываются вовсе.

| Метод | Эффект |
|---|---|
| `poll(string $method, int\|float $seconds = 5)` | Каждые `$seconds` вызывает `$method(array $context)` за [снимком мониторинга](https://github.com/dskripchenko/flow/blob/main/docs/ru/monitoring.md) — `nodes`, `edges`, `path`, `current`, `updatedAt`. В `$context` — `path` (подпроцессы, в которые вошли), `ref` и `instance`. Один запрос за раз, пауза в скрытой вкладке, отступ после ошибок; строка статуса показывает время обновления и устаревание |
| `onToggle(string $method, ?string $permission = null)` | Переключатели на узлах и переходах; `$method(array $change)` сохраняет `{kind, id, enabled, path}`. Чтобы отказать, бросьте `ActionFailedException` (или ответьте ошибкой): переключатель вернётся назад, сообщение покажется во всплывающем уведомлении |
| `toggleable(bool $on = true)` | Переключатели без метода: изменение остаётся в браузере |
| `subgraphs(string\|array $subgraphs)` | Как разрешаются строковые ссылки `subgraph`: метод `(string $ref, array $node): array`, возвращающий модель, или модели по ссылкам. Встроенным подпроцессам ничего не нужно |
| `instance(string\|int\|null $id)` / `instanceFrom(string $key)` | Экземпляр в фокусе, передаётся методу опроса и кнопкам панели деталей; с `instanceFrom()` его переключает поле экрана, и опрос идёт сразу |
| `snapshot(array\|string $snapshot)` | Фиксированный снимок или ключ state с ним; `poll()` заменяет его после первого ответа |
| `actions(array $buttons)` | `Button`'ы в панели деталей; метод получает `{kind, id, path, instance}`, `message` из ответа показывается уведомлением, затем состояние опрашивается снова |
| `direction('LR'\|'TB'\|'auto')` | Направление раскладки; по умолчанию `meta.direction` модели, иначе `auto` (по форме блока) |
| `edgeStyle('orthogonal'\|'smooth')` | Прямые углы (по умолчанию) или плавные кривые |
| `height(int\|string $height)` | Высота блока: `560` (px) или `'70vh'`; по умолчанию 480px |
| `minimap(bool)`, `details(bool)`, `statusBar(bool)` | Миникарта, панель деталей и строка статуса; все включены по умолчанию |
| `title(string $title)` | Подпись над графом |

Подписи встроенной модели (метки узлов и переходов, описания, заголовок)
проходят через JSON-переводы панели, как любые другие подписи; модель из state
показывается как хранится.

### Документы (диаграммы, BPMN, DMN, draw.io, AsyncAPI)

Пять layout'ов показывают документ, нарисованный по его исходному тексту, только для чтения:

| Фабрика | Рисует | Пакет |
|---|---|---|
| `Layout::diagram()` | Mermaid, PlantUML, Graphviz DOT, D2, DBML, Structurizr, карты Уордли | [@dskripchenko/diagrams](https://github.com/dskripchenko/diagrams) |
| `Layout::bpmn()` | Модели процессов BPMN 2.0 (Camunda Modeler, bpmn.io и другие редакторы) с панорамой и масштабом | [@dskripchenko/bpmn](https://github.com/dskripchenko/bpmn) |
| `Layout::dmn()` | Модели решений DMN; двойной щелчок по решению открывает его таблицу | [@dskripchenko/bpmn](https://github.com/dskripchenko/bpmn) |
| `Layout::drawio()` | Файлы draw.io (diagrams.net) как SVG в прокручиваемом блоке | [@dskripchenko/drawio](https://github.com/dskripchenko/drawio) |
| `Layout::asyncapi()` | Документы AsyncAPI (YAML или JSON): серверы, каналы, операции, сообщения и их содержимое | [@dskripchenko/openapi](https://github.com/dskripchenko/openapi) |

Каждый пакет загружается, только когда на странице есть такой документ; пока
он грузится, на его месте скелетон. Источник, который пакет не может прочитать,
показывается вместо картинки предупреждением с причиной (для диаграммы — со
строкой и подсказкой).

Источник у всех один и тот же — и у [Kanban и Calendar](#kanban-и-calendar):

```php
Layout::diagram('schema')                      // идентификатор: $state['schema']
Layout::diagram("flowchart LR\n  A --> B")     // всё остальное: сам исходник
Layout::bpmn('process')->url('/files/order.bpmn')   // загружает браузер
```

Строка-идентификатор (`/^[A-Za-z_][\w.-]*$/`) — это ключ state экрана: он
читается из формы (или записи infolist) и отслеживается при изменении — поле на
том же экране может переключать документ. Всё остальное — содержимое. `url()`
вместо этого загружает содержимое в браузере — по пути или URL собственного
сайта панели, с сессией пользователя; адрес другого сайта отклоняется. Каждый
адрес запрашивается на странице один раз.

```php
use Dskripchenko\LaravelAdmin\Layout\Layout;

final class ArchitectureScreen extends Screen
{
    public function query(mixed ...$params): array
    {
        return [
            'erd' => file_get_contents(database_path('schema.dbml')),
            'process' => Storage::get('bpmn/order.bpmn'),
            'rules' => Storage::get('dmn/discounts.dmn'),
            'step' => Order::find(request('order'))?->current_step,
        ];
    }

    public function layout(): array
    {
        return [
            Layout::diagram('erd')->syntax('dbml')->title('Модель данных')->height(480),
            Layout::bpmn('process')->highlight(['Task_ship']),
            Layout::dmn('rules')->decision('Decision_discount'),
            Layout::drawio('network')->url('/docs/network.drawio')->page('Rack B'),
            Layout::asyncapi('spec')->url('/docs/asyncapi.yaml'),
        ];
    }
}
```

| Метод | Layout'ы | Эффект |
|---|---|---|
| `url(string $url)` | все | Загружает содержимое в браузере по пути (`/files/a.bpmn`) или `http(s)`-URL того же сайта, с сессией пользователя; заменяет ключ state или встроенное содержимое |
| `title(string $title)` | все | Подпись над документом |
| `height(int\|string $height)` | все | Высота блока: `560` (px) или `'70vh'`. Без неё диаграммы, draw.io и AsyncAPI растут по содержимому, с ней прокручиваются внутри блока; BPMN и DMN по умолчанию 480px. Неположительное число или строка, не являющаяся CSS-длиной, бросают `InvalidArgumentException` |
| `syntax(string $syntax)` | `diagram` | `mermaid`, `plantuml`, `dot`, `d2`, `dbml`, `structurizr` или `wardley`; без него нотация определяется по началу исходника (`@startuml` — PlantUML, `digraph` — DOT, `Table` — DBML…). Всё остальное бросает `InvalidArgumentException` |
| `highlight(array $ids)` | `bpmn`, `dmn` | Id элементов, которые нужно обвести, — например, текущий шаг процесса |
| `showDiagram(string\|int $diagram)` | `bpmn` | Какую диаграмму файла показать, если их несколько: её id или индекс; по умолчанию первую |
| `decision(string $id)` | `dmn` | Открывает таблицу этого решения вместо диаграммы |
| `page(int\|string $page)` | `drawio` | Какую страницу показать: её индекс, id или имя; по умолчанию первую |

`showDiagram()` не называется `diagram()`, потому что это имя занято фабрикой
`Layout::diagram()`.

### Kanban и Calendar

Канбан-доска и календарь событий, записанные разметкой и нарисованные
`UidKanban` и `UidEventCalendar` UI-кита: карточки с тегами, исполнителями и
сроками в колонках; события на весь день и по времени, с повторами, в видах
день, неделя, месяц, год и повестка. Источник работает так же, как у
[документов](#документы-диаграммы-bpmn-dmn-drawio-asyncapi): ключ state, сама
разметка или `url()`.

По умолчанию только чтение. С `editable()` пользователь перемещает, добавляет и
меняет карточки и события (и правит разметку на вкладке исходника); после
каждого изменения layout вызывает метод экрана со всей новой разметкой:

```php
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Resource\ActionFailedException;

final class SprintScreen extends Screen
{
    public function query(mixed ...$params): array
    {
        $sprint = Sprint::current();

        return [
            'board' => $sprint->board,          // "## To do\n- Write docs #docs @anna\n## Done\n"
            'board_locked' => $sprint->closed,
            'schedule' => $sprint->calendar,
        ];
    }

    public function layout(): array
    {
        return [
            Layout::kanban('board')
                ->title('Доска спринта')
                ->editable('saveBoard', permission: 'sprints.edit')
                ->lockable('lockBoard', permission: 'sprints.close')
                ->locked('board_locked'),
            Layout::calendar('schedule')
                ->initialView('week')
                ->views(['week', 'month', 'agenda'])
                ->editable('saveSchedule', permission: 'sprints.edit'),
        ];
    }

    public function saveBoard(string $markup): void
    {
        $sprint = Sprint::current();
        if ($sprint->closed) {
            throw new ActionFailedException('Спринт закрыт.');
        }
        $sprint->update(['board' => $markup]);
    }

    public function lockBoard(bool $locked): void
    {
        Sprint::current()->update(['closed' => $locked]);
    }

    public function saveSchedule(string $markup): void
    {
        Sprint::current()->update(['calendar' => $markup]);
    }
}
```

Изменение показывается сразу. Чтобы отказать, бросьте `ActionFailedException`
(или ответьте ошибкой): доска вернётся к разметке до изменения, а сообщение
покажется во всплывающем уведомлении. `message` из ответа метода тоже
показывается уведомлением. Разметка приходит, как и другие строки запроса, —
через middleware приложения, так что `TrimStrings` убирает пробелы и переводы
строк в её начале и конце.

Методы — собственные публичные методы экрана, их вызывает его действие
`runMethod` с позиционными `parameters`: та же сессия, CSRF, слои и проверки,
что и у кнопки. Каждый названный layout'ом метод объявлен серверу:
пользователь, от которого layout скрыт (`canSee()`), получает 403 на все из
них, а методу с правом нужно ещё и это право. Пользователь без права получает
доску только для чтения (и без кнопки замка): layout сериализует `editable` /
`lockable` как false.

| Метод | Layout'ы | Эффект |
|---|---|---|
| `editable(string $method, ?string $permission = null)` | `kanban`, `calendar` | Включает правку; `$method(string $markup)` получает всю разметку после каждого изменения. Откажите `ActionFailedException`, чтобы откатить изменение |
| `lockable(string $method, ?string $permission = null)` | `kanban`, `calendar` | Кнопка замка; `$method(bool $locked)` получает новое состояние. Отказ возвращает его назад с уведомлением |
| `locked(bool\|string $locked = true)` | `kanban`, `calendar` | Только чтение, что бы ни говорил `editable()`: флаг или ключ state с ним |
| `initialView(string $view)` | `calendar` | Вид, показываемый первым: `day`, `week`, `month`, `year` или `agenda`; по умолчанию `view:` из разметки, иначе месяц |
| `date(string $date)` | `calendar` | Дата, показываемая первой, `YYYY-MM-DD`; по умолчанию `date:` из разметки, иначе сегодня |
| `views(array $views)` | `calendar` | Предлагаемые виды, в этом порядке |
| `url()`, `title()`, `height()` | `kanban`, `calendar` | Как у [документов](#документы-диаграммы-bpmn-dmn-drawio-asyncapi) |

Неизвестный вид, дата не в формате `YYYY-MM-DD` или пустой список видов
бросают `InvalidArgumentException`. `initialView()` не называется `view()`,
потому что это имя занято фабрикой `Layout::view()`. Сама разметка описана в
документации UI-кита к `UidKanban` и `UidEventCalendar`; та же разметка в блоке
` ```kanban ` или ` ```calendar ` [Markdown](#markdown) показывается только для
чтения.

### Video и Audio (медиаплееры)

Плееры UI-кита, `UidVideo` и `UidAudio`: полоса перемотки с буфером, главами и превью кадров, громкость, меню скорости, качества и субтитров, полный экран, «картинка в картинке», горячие клавиши и Media Session API. Запуск плеера ставит на паузу остальные на странице. Подписи элементов управления — на языке панели. HLS (`.m3u8`, fMP4 или MPEG-TS, AES-128, альтернативные звуковые дорожки) играет через адаптер кита, который загружается, только когда на странице есть поток.

```php
Layout::video([
    ['src' => '/media/intro-1080.mp4', 'label' => '1080p'],   // источники с label образуют меню качества
    ['src' => '/media/intro-720.mp4', 'label' => '720p'],
])
    ->title('Введение')
    ->poster('/media/intro.jpg')
    ->subtitles('/media/intro.en.vtt', 'en', 'English', default: true)
    ->subtitles('/media/intro.ru.vtt', 'ru', 'Русский')
    ->chapters('/media/intro.chapters.vtt')
    ->thumbnails('/media/intro.thumbs.vtt')   // реплики указывают на тайлы спрайта: sprite.jpg#xywh=0,0,160,90
    ->resume()                                // запоминает позицию в браузере
    ->maxWidth(760),

Layout::video('/streams/intro/master.m3u8'),          // HLS — по расширению
Layout::video('videos/intro.mp4')->disk('public'),    // путь в хранилище
Layout::video('recording'),                           // состояние экрана: $state['recording']

Layout::audio('/media/episode-12.mp3')->title('Выпуск 12')->artist('Подкаст')->waveform(),
Layout::audio([
    ['src' => '/media/a.mp3', 'title' => 'Начало', 'artist' => 'Группа', 'duration' => 64],
    ['src' => '/media/b.ogg', 'title' => 'Финал', 'peaks' => $peaks],   // заранее посчитанные пики, 0..1
])->waveform(),
Layout::audio('calls/2026-10-26.ogg')->disk('local')->mini(),
```

**Источник.** Строка — это URL, путь в хранилище или ключ состояния: медиа, если в ней есть схема или слэш либо она оканчивается медиа-расширением (`intro.mp4`); иначе это ключ состояния экрана или записи на странице просмотра (`fromState('media.url')` говорит это явно). Значение там может быть URL, путём, загруженным файлом `{url, mime, name, …}` (то, что сохраняет `FileUpload`), списком таких (плейлист или источники видео), а для видео — целым объектом `{sources, poster, tracks, thumbnails}`. Изменение значения — скажем, селектом в той же форме — переключает плеер.

**Пути в хранилище.** С `disk()` пути превращаются в URL — постеры и файлы WebVTT тоже: `Storage::url()` для диска с публичным адресом (`url` в конфиге или облачный драйвер), эндпоинт панели `uploads/serve` для локального диска без него. Этот эндпоинт отвечает на Range-запросы, без которых не работает перемотка (а Safari вообще не играет видео); диск должен быть в `admin.uploads.servable_disks`. Значения из состояния разрешаются в браузере так же. Для диска с подписанными временными ссылками кладите в состояние сами ссылки.

Оба плеера:

| Метод | Что делает |
|---|---|
| `disk(string $disk)` | Пути разрешаются через этот диск |
| `fromState(string $key)` | Медиа берётся из ключа состояния |
| `resume(?string $key = null)` | Запоминает позицию воспроизведения (localStorage); по умолчанию ключ — адрес медиа |
| `loop()`, `preload('none'\|'metadata'\|'auto')` | |
| `exclusive(false)` | Играть вместе с другими плеерами, не ставя их на паузу |
| `hotkeys(false)`, `rates([1, 1.5, 2])` | Горячие клавиши, меню скорости |
| `crossorigin('anonymous')` | Для медиа, дорожек и волновой формы с другого origin |
| `maxWidth(int\|string $width)` | Максимальная ширина: `760` (px) или `'40rem'` |
| `hlsOptions(array $options)` | Адаптер HLS: `preferNative` (`false` — адаптер даже там, где браузер сам играет HLS, с меню качества и звуковых дорожек), `startLevel`, `audioLanguage`, `maxBufferAhead`, `maxBufferBehind`, `retries`, `initialBandwidth`, `capToPlayerSize` |

Видео:

| Метод | Что делает |
|---|---|
| `source($src, $label = null, $type = null)`, `quality($label, $src)` | Добавляет источник; источники идут по предпочтению, с label образуют меню качества |
| `hls($src, $label = null)`, `mime($type)` | Поток, адрес которого не оканчивается на `.m3u8`; тип источников, заданных без него |
| `title($title)` | Подпись над плеером и его доступное имя |
| `poster($url)`, `thumbnails($vtt)` | Картинка до воспроизведения; WebVTT с тайлами спрайта над полосой перемотки |
| `subtitles($src, $lang, $label, $default)`, `captions(...)`, `chapters($src)`, `tracks([...])` | Дорожки WebVTT |
| `autoplay()` | Запуск сразу, без звука (браузеры блокируют автозапуск со звуком); `muted()`, `controls(false)` |
| `aspectRatio('16 / 9')` | Пропорции блока, пока размер видео неизвестен; `'4:3'` тоже подходит |
| `autoHide(int $ms)` | Сколько ждать без активности, прежде чем скрыть управление во время воспроизведения |

Аудио:

| Метод | Что делает |
|---|---|
| `track($src, ['title' => …, 'artist' => …, 'duration' => …, 'peaks' => […]])` | Добавляет дорожку; две и больше — плейлист с «назад/вперёд» и списком |
| `title()`, `artist()`, `mime()` | Для одной дорожки |
| `waveform()` | Волновая форма вместо полосы, считается в браузере (тот же origin или CORS; не для потоков) |
| `peaks([...])` | Заранее посчитанные пики 0..1: волновая форма без декодирования — для длинных записей |
| `mini()` | Одна строка: кнопка, короткая полоса, время |
| `compact()` | Полная строка без громкости и скорости |
| `playlist(false)`, `autoAdvance(false)` | Скрыть список; останавливаться после каждой дорожки |

`VideoEntry` и `AudioEntry` показывают файл записи на странице просмотра (см. [Infolist](#infolist-только-чтение)), `TableColumn::asAudio()` и `asVideo()` — в таблице ([Колонки](concepts/resources.md#columns-таблица)), а поле `FileUpload` показывает плеер под загруженным аудио- или видеофайлом.

### View (собственный Vue-компонент)

```php
Layout::view('my-custom-card', [
    'count' => 42,
    'label' => 'Items',
]),
```

Фронтенд:

```ts
import { registerLayout } from '@dskripchenko/laravel-admin'
import MyCustomCard from './MyCustomCard.vue'
registerLayout('my-custom-card', MyCustomCard)
```

## Видимость

```php
Layout::block('Admin only', [...])
    ->canSee(fn () => auth()->user()?->hasAccess('admin.*')),
```

## Композиция

Layout'ы вкладываются друг в друга:

```php
Tabs::make([
    'Form' => [
        Block::make('Basic', [
            Columns::make([
                Input::make('first_name'),
                Input::make('last_name'),
            ]),
        ]),
        Block::make('Settings', [
            Switcher::make('is_active'),
            Select::make('plan')->options([...]),
        ]),
    ],
    'Audit' => [
        AuditTrail::for(\App\Models\User::class),
    ],
]),
```

## Контракт toArray

Любой layout сериализуется так:

```json
{
  "id": "l-xxxxxxxx",
  "kind": "layout",
  "type": "rows",
  "items": [ ... ],
  "props": {},
  "children": [ ... ]
}
```

Props дополнительно разворачиваются на верхний уровень, а `items`
повторяет `children`. `children` рекурсивны (это `toArray` других
layout'ов или полей). Фронтендовый `LayoutRenderer` находит тип в реестре
и рекурсивно спускается дальше.

## См. также

- [Каталог полей](fields-reference.md)
- [Frontend-расширение](frontend-extension.md) — регистрация собственных layout'ов
- [Screens](concepts/screens.md)
