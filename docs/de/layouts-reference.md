---
title: Layouts-Referenz
audience: developer
status: stable
locale: de
translated_from: en/layouts-reference.md
translated_at: 2026-10-02
---

# Layouts-Referenz

Layouts sind renderbare Container für Felder und andere Layouts. Sie lassen
sich beliebig tief verschachteln.

## Kurzübersicht

| Klasse | Factory | Verwendung |
|---|---|---|
| `Rows` | `Layout::rows([...])` | Vertikaler Stapel |
| `Columns` | `Layout::columns([...])` | Gleich breite Spalten |
| `Block` | `Layout::block($title, [...])` | Abschnitt mit Titel |
| `Tabs` | `Layout::tabs(['Label' => [...], ...])` | Abschnitte in Tabs |
| `Wizard` + `Step` | `Layout::wizard([Layout::step(...), ...])` | Mehrstufiges Formular |
| `Modal` | `Layout::modal($title, [...])` | Anzeige in einem modalen Dialog |
| `Drawer` | `Layout::drawer($title, [...])` | Einfahrendes Seitenpanel |
| `Wrapper` | `Layout::wrapper([...])` | Einfache `<div>`-Gruppe |
| `Accordion` | `Layout::accordion(['Section' => [...]])` | Einklappbare Abschnitte |
| `Infolist` | `Layout::infolist([...])` | Schreibgeschützte Schlüssel/Wert-Anzeige |
| `Dashboard` | `Layout::dashboard([...])` | 12-Spalten-Raster (verwendet von `DashboardScreen`) |
| `View` | `Layout::view('component-name', $props)` | Eigene Vue-Komponente |
| `Markdown` | `Layout::markdown($text)` | Gerendertes Markdown: Überschriften mit Ankern, Inhaltsverzeichnis, hervorgehobener Code, Tabellen, Hinweisboxen |
| `Code` | `Layout::code($code, 'php')` | Hervorgehobener, kopierbarer Codeblock |
| `Flow` | `Layout::flow('process')` | Prozess oder Zustandsautomat mit Live-Monitoring, Schaltern und Drill-down |
| `Diagram` | `Layout::diagram('schema')` | Diagramm aus Text: Mermaid, PlantUML, DOT, D2, DBML, Structurizr, Wardley-Map |
| `Bpmn` / `Dmn` | `Layout::bpmn('process_xml')` | BPMN-Prozess- oder DMN-Entscheidungsmodell, schreibgeschützt, mit Verschieben, Zoomen und hervorgehobenen Elementen |
| `Drawio` | `Layout::drawio('network')` | draw.io-Datei (diagrams.net), als SVG gezeichnet |
| `AsyncApi` | `Layout::asyncapi('spec')` | AsyncAPI-Referenz: Server, Kanäle, Operationen, Nachrichten |
| `Kanban` | `Layout::kanban('board')` | Kanban-Board als Markup, bearbeitbar über eine Screen-Methode |
| `Calendar` | `Layout::calendar('schedule')` | Ereigniskalender mit Wiederholungen als Markup, bearbeitbar über eine Screen-Methode |
| `Board` | `Layout::board()->endpoint('/boards/42')` | Gemeinsames Board, das mehrere Personen gleichzeitig bearbeiten; Speicherung durch `laravel-admin-board` |
| `Video` | `Layout::video('/media/intro.mp4')` | Videoplayer: Qualitäten, HLS, Untertitel, Kapitel, Vorschaubilder |
| `Audio` | `Layout::audio([...])` | Audioplayer: ein Titel oder eine Playlist, Wellenform, Mini-Variante |
| `AuditTrail` | `AuditTrail::for(User::class)` | Audit-Zeitleiste des angezeigten Datensatzes |
| `Listener` | `Layout::listener([...])->listen([...])` | Teil eines Formulars, den der Server neu rendert, wenn sich beobachtete Felder ändern |
| `ResourceTable` | `ResourceTable::for(ItemResource::class)` | Tabelle der Datensätze einer anderen Resource, die zum bearbeiteten Datensatz gehören |
| `ResourceIndex` | `Layout::resourceIndex(OrderResource::class)` | Live-Listenseite einer Resource (Tabelle, Baum, Kanban oder Kalender) auf einem Screen |

## Beispiele

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

`->ratios([2, 1])` für ungleich breite Spalten.

### Block (Abschnitt mit Titel)

```php
Block::make('Profil', [
    Input::make('name'),
    Input::make('email'),
])->description('Persönliche Daten'),
```

### Tabs

```php
Tabs::make([
    'Allgemein' => [
        Input::make('title'),
        Textarea::make('description'),
    ],
    'SEO' => [
        Input::make('meta_title'),
        Textarea::make('meta_description'),
    ],
    'Übersetzungen' => [
        TranslatableInput::make('title'),
    ],
]),
```

Der Zustand des aktiven Tabs ist lokal für die Seite; beim Wechseln geht der
Formular-State nicht verloren.

### Wizard (mehrstufiges Formular)

```php
Layout::wizard([
    Layout::step('Konto', [
        Input::make('email')->required(),
        Input::make('password')->type('password')->required(),
    ]),
    Layout::step('Profil', [
        Input::make('name'),
        DatePicker::make('birthday'),
    ]),
    Layout::step('Fertig', [
        Layout::view('summary-step', ['fields' => [...]]),
    ]),
]),
```

Das Frontend rendert einen `UidStepper`-Kopf mit Zurück-/Weiter-Buttons. Das
Vorwärtsgehen ist an die Validierung gebunden: Die Felder des Schritts werden
gegen ihre eigenen Regeln geprüft (`required`, `email`, `numeric`, `integer`,
`min`, `max`, `in`; alles andere bleibt dem Server überlassen) sowie gegen die
`->rules([...])` des Schritts.

```php
Layout::wizard([...])
    ->submit('finish')          // der Button des letzten Schritts ruft diese Screen-Methode auf
    ->freeForm()                // Schritte dürfen in beliebiger Reihenfolge besucht werden
    ->persistKey('onboarding'), // der Fortschritt übersteht ein Neuladen (localStorage)
```

Ohne `freeForm()` sind im Stepper nur bereits durchlaufene Schritte anklickbar.
Mit `persistKey()` werden der aktuelle Schritt und die eingegebenen Werte im
Browser aufbewahrt, bis der Wizard abgeschickt wird; Passwörter und Dateien
werden nie gespeichert.

### Modal / Drawer

Für Actions:

```php
ModalAction::make('Preis festlegen')
    ->method('setPrice')
    ->fields([
        Number::make('price')->required(),
    ]),
```

Oder als Layout in einem Screen, geöffnet durch eine Action, die seine ID nennt:

```php
public function layout(): array
{
    return [
        Layout::modal('Bearbeiten', [
            Input::make('title'),
        ])
            ->withId('edit-modal')
            ->size('lg')               // sm | md | lg | xl | full
            ->dismissable(false)       // kein Kreuz, kein Klick auf das Overlay, kein Escape
            ->footer([
                Button::make('Abbrechen')->withName('cancel'),
                Button::make('Speichern')->method('save')->primary(),
            ]),
    ];
}

public function commandBar(): array
{
    return [Button::make('Bearbeiten')->opens('edit-modal')];
}
```

Eine Footer-Action mit einer Methode schließt das Overlay, sobald die Methode
erfolgreich war; eine mit dem Namen `close` oder `cancel` schließt es einfach.
`Layout::drawer()` funktioniert genauso — einschließlich `->dismissable()` und
`->footer([...])` — mit `->position('left'|'right'|'top'|'bottom')` und
`->size()` (`sm`, `md`, `lg`, `xl` oder eine CSS-Länge).

### Wrapper

Einfaches Element, um CSS anzuwenden:

```php
Layout::wrapper([
    Input::make('title'),
    Input::make('slug'),
])->className('two-col-grid')->tag('section'),
```

`tag()` akzeptiert `div` (Standard), `section`, `article`, `aside`, `header`,
`footer`, `main`, `nav`, `fieldset` und `span`.

### Accordion

```php
Layout::accordion([
    'Persönlich' => [Input::make('name')],
    'Abrechnung' => [Input::make('card_last4')->readonly()],
])->multi(),
```

`->multi()` erlaubt, dass mehrere Abschnitte gleichzeitig geöffnet sind.
Abschnitte starten geschlossen; `->section('Title', [...], defaultOpen: true)`
öffnet einen Abschnitt von Anfang an.

### Infolist (schreibgeschützt)

Für den `view`-Modus (`ResourceViewPage`, Custom Screen):

```php
Layout::infolist([
    TextEntry::make('title'),
    BadgeEntry::make('status')->colors(['published' => 'success', 'draft' => 'default']),
    KeyValueEntry::make('meta'),
])->layout('rows'),  // oder 'columns', 'grid'
```

Entry-Typen: `TextEntry`, `BadgeEntry`, `IconEntry`, `KeyValueEntry`,
`ImageEntry`, `RelationEntry`, `RepeatableEntry`, `MapEntry`,
`ColorEntry`.

`FlowEntry` zeigt ein gespeichertes Prozessmodell schreibgeschützt (die Ansicht eines [`Flow`-Felds](fields-reference.md#flow)): `FlowEntry::make('workflow')->height(420)`.

`BpmnEntry` und `DmnEntry` zeigen eine gespeicherte BPMN- oder DMN-Datei schreibgeschützt mit den Betrachtern der [BPMN- und DMN-Layouts](#dokumente-diagramme-bpmn-dmn-drawio-asyncapi) (die Ansicht der [Felder `Bpmn` und `Dmn`](fields-reference.md#bpmn-und-dmn)): `BpmnEntry::make('process_xml')->height(420)->highlight(['Task_approve'])`, `->diagram()`; `DmnEntry::make('rules_xml')->decision('Decision_discount')`. `KanbanEntry` und `CalendarEntry` zeigen gespeichertes Markup schreibgeschützt, ohne Bearbeitung und ohne Sperre (die Ansicht der [Felder `Kanban` und `Calendar`](fields-reference.md#kanban-und-calendar)): `KanbanEntry::make('board')->height(420)`, `CalendarEntry::make('schedule')->initialView('agenda')` (dazu `date()` und `views()`, geprüft wie bei [`Layout::calendar()`](#kanban-und-calendar)). `MarkdownEntry` zeichnet gespeichertes Markdown samt der Blocksprachen (die Ansicht der Felder `Markdown` und [`MarkdownEditor`](fields-reference.md#markdowneditor)): `MarkdownEntry::make('body')`. Ein leerer Wert zeigt einen Strich.

`VideoEntry` und `AudioEntry` spielen eine Datei des Datensatzes ab — eine URL, einen Speicherpfad (mit `disk()`), eine hochgeladene Datei oder eine Liste: `AudioEntry::make('call_recording')->disk('local')` (der einzeilige Mini-Player; `->full()->waveform()` für den vollen), `VideoEntry::make('clip_path')->disk('public')->posterFrom('cover_path')`. Siehe [Video und Audio](#video-und-audio-medienplayer).

In einem Custom Screen lesen die Entries den State des Screens.

### AuditTrail

```php
AuditTrail::for(\App\Models\User::class)
    ->fromState('user_id')          // der State-Schlüssel, der die ID enthält; standardmäßig 'id'
    ->limit(20)
    ->withPermission('admin.audit'),
```

Zeigt die Audit-Zeitleiste des Datensatzes (`GET /audit/timeline`); solange der
State keine ID hat, wird nichts angezeigt.

### Dashboard

```php
Layout::dashboard([
    StatsOverviewWidget::make()->title('Artikel')->size(3),
    ChartWidget::make()->title('Täglich')->size(8)->rowSpan(2),
    // ...
]),
```

(Wird von `DashboardScreen` verwendet, Sie können ein Dashboard-Layout aber in
jeden beliebigen Screen einfügen.)

### Listener (reaktiver Teil eines Formulars)

Ein Listener beobachtet einige Felder des Formulars. Ändert sich eines davon,
wartet die SPA, bis der Benutzer eine Pause macht (standardmäßig 300 ms),
sendet den aktuellen Formular-State an den Server und ersetzt die Kinder des
Listeners durch diejenigen, die der Server für diesen State rendert. Ein
optionaler Handler kann außerdem Werte zurückgeben, die in das Formular
übernommen werden.

```php
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Layout\Layout;

public function layout(): array
{
    return [
        Layout::rows([
            Select::make('country_id')->fromModel(Country::class),

            // Kinder als Closure: gerendert anhand des aktuellen States.
            Layout::listener(fn (array $state) => [
                Select::make('city_id')
                    ->title('Stadt')
                    ->fromModel(City::where('country_id', $state['country_id'] ?? null)),
            ])->listen('country_id'),

            Number::make('price'),
            Number::make('quantity'),

            // Ein Handler, der Werte berechnet: eine öffentliche Methode des Screens.
            Layout::listener([
                Number::make('total')->readonly(),
            ])->listen(['price', 'quantity'])->handler('recalculateTotal'),
        ]),
    ];
}

/** Gibt einen Patch zurück: die Schlüssel des Formular-States, die geändert werden sollen. */
public function recalculateTotal(array $state, Request $request): array
{
    return ['total' => round((float) ($state['price'] ?? 0) * (int) ($state['quantity'] ?? 0), 2)];
}
```

| Methode | Bedeutung |
|---|---|
| `Layout::listener(array\|Closure $children)` | Statische Kinder oder `fn (array $state): array` |
| `->listen(string\|array $fields)` | Die Namen der beobachteten Felder |
| `->handler(string\|Closure $handler)` | Eine öffentliche Methode des Screens/der Resource oder eine Closure; aufgerufen als `(array $state, Request $request)`, gibt einen State-Patch zurück (`array`, `Repository` oder `null`) |
| `->debounce(int $ms)` | Die Ruhezeit vor einem Request, standardmäßig 300 |
| `->withId(string $id)` | Eine explizite ID; nur nötig, wenn zwei Listener dieselben Felder mit Closure-Handlern beobachten |

Der Ablauf bei jeder Änderung: Der Handler läuft mit dem gesendeten State,
sein Patch wird in diesen State übernommen, und die Kinder werden mit dem
Ergebnis gerendert. Die Response enthält sowohl den Patch als auch die Kinder;
die SPA übernimmt den Patch in das Formular (wobei Felder übersprungen werden,
die der Benutzer bearbeitet hat, während der Request lief), tauscht die Kinder
an Ort und Stelle aus — Felder, die ihre Position behalten, behalten auch ihren
Fokus —, bricht veraltete Requests ab und zeigt Fehler als Toast an (oder, bei
einer `ValidationException`, unter den Feldern).

**In einem Resource-Formular** gehört der Listener in `formLayout()`, und ein
String-Handler ist eine öffentliche Methode der Resource:

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

Validierung und Speichern lesen weiterhin `fields()`: Ein Feld, das von einem
Listener gerendert wird, muss auch dort deklariert sein. Das Manifest wird ohne
Datensatz erstellt, daher erhält eine Closure zunächst einen leeren State; sie
sollte fehlende Schlüssel vertragen. Wenn das Bearbeitungsformular mit
ausgefüllten beobachteten Feldern geöffnet wird, bittet der Listener den
Server einmal, seine Kinder für den Datensatz zu rendern (ohne Werte zu ändern).

**Endpoint und Sicherheit.** Screens erhalten `POST /api/admin/{screen}/listener`
hinter der `permission()` des Screens; Resources erhalten
`POST /api/admin/{resource}/listener` (nur wenn ihr Formular Listener hat),
was `view` plus `create` oder `update` je nach Kontext des Formulars
erfordert. Der Body ist `{listener, state, context?, id?}` und die Antwort
`{listener, state, layouts}`. Der Request benennt einen Listener über seine ID,
niemals über eine Methode: Nur Handler, die von einem Listener im `layout()`
dieses Screens oder im `formLayout()` dieser Resource deklariert sind, können
ausgeführt werden, und reservierte Screen-Methoden (`query`, `layout`, …)
werden abgelehnt.

Das `layout()` eines Screens wird ohne `query()` aufgerufen, wenn ein
Listener-Request bedient wird; die Listener sollten daher nicht von
Eigenschaften abhängen, die `query()` setzt.

### ResourceTable (eingebettete Tabelle einer anderen Resource)

```php
use Dskripchenko\LaravelAdmin\Layout\ResourceTable;

public function formLayout(string $context): array
{
    if ($context !== 'update') {
        return [];
    }

    return [
        Layout::tabs([
            'Allgemein' => $this->fields(),
            'Einträge' => [
                ResourceTable::for(DictionaryItemResource::class)
                    ->foreignKey('dictionary_id')   // die Spalte des Kindes, die auf das Elternelement verweist
                    ->parentField('id')             // die Spalte des Elternelements, die sie enthält; standardmäßig 'id'
                    ->hideColumns(['dictionary_id'])
                    ->features(['create' => true, 'delete' => true, 'bulkDelete' => true]),
            ],
        ]),
    ];
}
```

Zeigt auf der Bearbeitungsseite des Elternelements die Datensätze der
Kind-Resource, die zum bearbeiteten Datensatz gehören. Die Tabelle übernimmt
die Spalten der Kind-Resource aus dem Manifest (abzüglich `hideColumns()`),
lädt bis zu 100 Zeilen über `POST /{child}/search` mit
`filters: {foreign_key: parent[parent_field]}` und bearbeitet Zellen inline,
wo die Spalten des Kindes `editable()` sind. `features()` schaltet Folgendes
ein, standardmäßig ist alles aus:

- `create` — eine Entwurfszeile oben, gespeichert über `POST /{child}/create`
  mit ausgefülltem Fremdschlüssel;
- `delete` — ein Lösch-Button pro Zeile (`POST /{child}/delete`);
- `bulkDelete` — Zeilenauswahl und Massenlöschung.

Jeder Request wird gegen die eigenen Berechtigungen der Kind-Resource geprüft
(`.view`, `.create`, `.update`, `.delete`).

Der Fremdschlüssel erreicht die Abfrage nur über einen Filter, den die
Kind-Resource für diese Spalte deklariert — ohne einen solchen listet die
Tabelle die Datensätze aller Elternelemente auf. Deklarieren Sie in der
Kind-Resource einen Filter auf exakte Übereinstimmung:

```php
public function filters(): array
{
    return [
        QueryFilter::for('dictionary_id')
            ->using(fn ($query, $value) => $query->where('dictionary_id', $value)),
    ];
}
```

Platzieren Sie die Tabelle in `formLayout('update')`: Ein Datensatz, der gerade
erstellt wird, hat noch keinen Schlüssel, und die Tabelle bleibt leer, bis er
gespeichert ist.

### ResourceIndex (Live-Liste einer Resource auf einem Screen)

```php
use Dskripchenko\LaravelAdmin\Layout\Layout;

public function layout(): array
{
    return [
        Layout::markdown('Was die Tabelle zeigt…'),
        Layout::resourceIndex(OrderResource::class),
    ];
}
```

Die Listenseite der Resource selbst — Suche, Filter, Sortierung, Zeilen- und
Massenaktionen, Inline-Bearbeitung, Sortierung per Drag-and-drop, Papierkorb
oder der Baum einer hierarchischen Resource — eingebettet in einen Screen. Ein
Klick auf eine Zeile öffnet den Datensatz wie auf der Listenseite. Für
Benutzer ohne `view`-Berechtigung der Resource ausgeblendet. Eine pro Screen:
der Zustand der Liste ist geteilt.

`initialView('kanban')` (oder `'calendar'`, `'list'`) öffnet es in dieser
Ansicht der Resource — siehe [Kanban- und Kalenderansicht](concepts/resources.md#kanban--und-kalenderansicht);
der Ansichtsumschalter bietet die anderen weiterhin an, und ein `?view=` in der
URL hat Vorrang. (`view()` wäre der natürliche Name, aber `Layout::view()` ist
bereits die Factory eines Blade-View-Layouts.)

```php
Layout::resourceIndex(TaskResource::class)->initialView('kanban'),
```

### Markdown

Ein Markdown-Block, gerendert vom eingebauten Renderer des Panels. Der
Quelltext wird maskiert, bevor Markup erzeugt wird, sodass rohes HTML als Text
angezeigt und niemals ausgeführt wird; Links und Bilder dürfen nur auf http(s),
`mailto:`, relative Ziele und Anker zeigen.

```php
Layout::markdown(file_get_contents(base_path('docs/en/getting-started.md')))
    ->toc()                         // Inhaltsverzeichnis aus den h2/h3-Überschriften
    ->linkBase('/admin/screens/')   // wohin relative Links führen
    ->imageBase('/docs-assets/')    // woher relative Bilder geladen werden
```

`Layout::markdown()` akzeptiert auch ein Callable, das aufgelöst wird, wenn der
Screen serialisiert wird — praktisch, wenn der Text von der Festplatte oder aus
einer Datenbank gelesen wird:

```php
Layout::markdown(fn () => Page::whereSlug($slug)->value('body'))
```

Der Text wird unverändert gesendet: Wählen Sie die Sprachversion selbst, z. B.
anhand von `app()->getLocale()`.

Was der Renderer unterstützt:

| Syntax | Ergebnis |
|---|---|
| `# Heading` … `###### Heading` | Überschriften mit Anker-IDs: `## Getting started` → `#getting-started` (Buchstaben jeder Schrift bleiben erhalten, Duplikate erhalten `-1`, `-2`) |
| Umzäunte Codeblöcke (drei Backticks und eine Sprache) | Hervorgehobener, kopierbarer Code; die Sprache kommt aus der Umzäunung (`php`, `js`, `ts`, `json`, `bash`, `sql`, `html`, `vue`, `css`, …) |
| ` ```mermaid `-Blöcke | Diagramme in Mermaid-Syntax: Flowchart, Sequence, State, ER, Klassen, C4, Pie, Gantt, Timeline, XY-Chart und Mindmap, als SVG gezeichnet von [@dskripchenko/diagrams](https://github.com/dskripchenko/diagrams) |
| ` ```openapi `-Blöcke | API-Widgets: ein in YAML oder JSON beschriebener Request (oder ein ganzes OpenAPI-Dokument) wird zur Operation mit Try-it, Codebeispielen, TypeScript-Typen und PDF, von [@dskripchenko/openapi](https://github.com/dskripchenko/openapi) |
| ` ```flow `-Blöcke | Eine schreibgeschützte Prozessansicht: ein Modell in JSON oder Mermaid-Quelltext `stateDiagram-v2` / `flowchart`, gezeichnet von [@dskripchenko/flow](https://github.com/dskripchenko/flow) |
| ` ```plantuml `-, ` ```dot `-, ` ```d2 `-, ` ```dbml `-, ` ```structurizr `-, ` ```wardley `-Blöcke | Diagramme in PlantUML-, Graphviz-DOT-, D2-, DBML-, Structurizr-DSL- und Wardley-Map-Syntax (auch ` ```puml `, ` ```graphviz `), vom selben Paket |
| ` ```bpmn `-, ` ```dmn `-, ` ```drawio `-Blöcke | Prozess- und Entscheidungsmodelle (BPMN 2.0, DMN 1.1–1.5, mit der Tabelle jeder Entscheidung) und draw.io-Dokumente als Bild |
| ` ```asyncapi `-Blöcke | Die Referenz eines AsyncAPI-2.x/3.0-Dokuments: Kanäle, Operationen, Nachrichten |
| ` ```kanban `-, ` ```calendar `-Blöcke | Ein Board mit Karten und ein Kalender mit Wiederholungen, als Text geschrieben — hier nur lesbar, im WYSIWYG-Editor direkt bearbeitbar |
| `\| a \| b \|` + `\|---\|:---:\|` | Tabellen, mit Spaltenausrichtung |
| `> quote` | Blockzitat |
| `> **Note** …`, `> **Warning** …` | Hinweisbox; außerdem `Tip`, `Important`, `Caution` und die GitHub-Form `> [!NOTE]` |
| `**bold**`, `*italic*`, `~~struck~~`, Code-Spans in Backticks | Inline-Markup |
| `[text](url)`, `![alt](src)` | Links und Bilder |
| `-`/`*`/`1.`-Listen, `---` | Listen und Trennlinien |

**Diagramme.** Ein umzäunter Block mit der Sprache `mermaid` wird zum
Diagramm — `flowchart` (mit Subgraphen), `sequenceDiagram`, `stateDiagram-v2`,
`erDiagram`, `classDiagram`, C4 (`C4Context`, `C4Container`, `C4Component`,
`C4Dynamic`, `C4Deployment`), `pie`, `gantt`, `timeline`, `xychart-beta` und
`mindmap`, als SVG gezeichnet von [@dskripchenko/diagrams](https://github.com/dskripchenko/diagrams)
in den Farben und dem Theme des Panels. Das Paket hat keine Abhängigkeiten und
wird nur geladen, wenn eine Seite ein Diagramm enthält. Beschriftungen werden
wie der übrige Text maskiert, ein Diagramm kann also weder Markup noch Skripte
tragen. Ein Block, der sich nicht lesen lässt, erscheint als Code mit dem Grund
und der Zeile. Die unterstützte Syntax steht in der Dokumentation des Pakets.

**API-Blöcke.** Ein umzäunter Block mit der Sprache `openapi` enthält einen
in YAML oder JSON beschriebenen Request — `method`, `url`, `params`, `auth`,
`body`, `responses` oder `requests: [...]` für mehrere — oder ein ganzes
OpenAPI-Dokument. Er wird zu einem Live-Widget von [@dskripchenko/openapi](https://github.com/dskripchenko/openapi):
die Operation mit Parametern und Antworten, Try-it, das aus dem Browser des
Lesers sendet, Codebeispiele, TypeScript-Typen und ein PDF der Operation. Das
Paket wird nur geladen, wenn eine Seite einen solchen Block hat. Das Format
beschreibt die Dokumentation des Pakets (API blocks).

**Prozessblöcke.** Ein umzäunter Block mit der Sprache `flow` enthält ein Prozessmodell in JSON (siehe [Flow](#flow-prozess-mit-live-monitoring)) oder Mermaid-Quelltext `stateDiagram-v2` / `flowchart`; er wird zu einer schreibgeschützten Prozessansicht — Verschieben, Zoomen, Unterprozesse, die Details eines Schritts. Das Paket wird nur geladen, wenn eine Seite einen solchen Block enthält.

**Medienblöcke.** Umzäunte Blöcke mit der Sprache `video` oder `audio` werden zu den Playern des UI-Kits. Die Quelle ist eine bloße URL (eine pro Zeile für eine Playlist oder Videoquellen), JSON oder ein paar Zeilen YAML mit den Optionen von [Video und Audio](#video-und-audio-medienplayer): `src`, `sources`, `poster`, `tracks`, `thumbnails`, `title`, `aspectRatio`, `maxWidth`, `autoplay`, `loop` für Video; `src` oder `tracks`, `title`, `artist`, `waveform`, `peaks`, `mini` für Audio. Im WYSIWYG-Editor fügt das Slash-Menü sie mit `/video` und `/audio` ein.

**Weitere Notationen und Widgets.** Derselbe umzäunte Block zeichnet ein
Diagramm in anderer Syntax: `plantuml` (oder `puml`), `dot` (oder
`graphviz`), `d2`, `dbml`, `structurizr`, `wardley`. `bpmn` und `dmn`
nehmen das XML, das ein Modeler speichert (Camunda Modeler, bpmn.io und
andere) — die BPMN-Datei muss ihr Layout enthalten; ein DMN-Block zeigt das
Anforderungsdiagramm und darunter die Tabelle jeder Entscheidung. `drawio`
nimmt ein draw.io-Dokument (`.drawio`, komprimiert oder nicht), `asyncapi`
ein AsyncAPI-Dokument in YAML oder JSON. `kanban` und `calendar` nehmen
Markup — Spalten und Karten, Termine mit Wiederholungen in Worten („every
month last fri") — im Format von
[`UidKanban`](https://github.com/dskripchenko/ui/blob/main/docs/de/kanban.md) und
[`UidEventCalendar`](https://github.com/dskripchenko/ui/blob/main/docs/de/event-calendar.md)
aus dem UI-Kit. Im WYSIWYG-Feld werden Board und Kalender direkt im Block
bearbeitet und schreiben das neue Markup zurück. Jedes Paket wird beim ersten
Gebrauch geladen.

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

Ein Host ergänzt Renderer für weitere Sprachen auf dieselbe Weise, in
`onAppCreated`:
`app.provide(markdownBlockRenderersKey, { ...builtinMarkdownRenderers, chart })`
(`markdownBlockRenderersKey` aus `@dskripchenko/ui`,
`builtinMarkdownRenderers` aus `@dskripchenko/laravel-admin`).

Optionen:

| Methode | Wirkung |
|---|---|
| `toc(bool $enabled = true, int $depth = 3)` | Ein Inhaltsverzeichnis aus Überschriften der Ebenen 2 bis `$depth`, auf breiten Bildschirmen neben dem Text, auf schmalen darüber |
| `tocLabel(string $label)` | Die Beschriftung darüber; standardmäßig „On this page“ |
| `linkBase(string $base, bool $stripExtension = true)` | Relative Links werden gegen `$base` aufgelöst, so wie ein Browser sie gegen `<base href>` auflöst: mit `/admin/screens/` öffnet `docs-menu.md#items` die Adresse `/admin/screens/docs-menu#items`, und mit `https://example.com/docs/en/` öffnet `concepts/menu.md` die Adresse `https://example.com/docs/en/concepts/menu`. Die Endung `.md` wird entfernt, sofern `$stripExtension` nicht false ist. Links, die mit `/`, `#` oder einem Schema beginnen, bleiben unverändert |
| `imageBase(string $base)` | Dasselbe für relative Bildpfade, ohne Endungen anzutasten |
| `card(bool $card = true)` | Zeichnet den Text in einer Karte |

Screen-Slugs sind flach — ein Screen liegt unter `/admin/screens/{slug}` und
darunter gibt es nichts —, daher ist eine Seite eines mehrseitigen Dokuments
ein eigener Screen, und ein Link zwischen Seiten muss den Slug des Ziel-Screens
nennen. Markdown, das für einen Dateibaum geschrieben wurde
(`concepts/menu.md`, `../intro.md`), lässt sich nicht von selbst darauf
abbilden: Schreiben Sie entweder seine Links in Slugs um, bevor Sie den Text
übergeben (`concepts/menu.md` → `docs-concepts-menu`), und verwenden Sie
`/admin/screens/` als Basis, oder richten Sie die Basis auf den Ort, an dem der
Baum unverändert liegt, etwa das Repository oder eine Dokumentationsseite.

Links verhalten sich wie auf einer Website: Anker scrollen innerhalb der Seite,
Links, die innerhalb des Panels bleiben, navigieren ohne Neuladen, und externe
Links öffnen sich in einem neuen Tab.

### Code

Ein hervorgehobener Codeblock mit Kopier-Button:

```php
Layout::code(<<<'PHP'
    Layout::rows([
        Input::make('title')->required(),
    ]);
    PHP, 'php')
    ->title('app/Admin/Resources/PostResource.php')
    ->lineNumbers(),
```

| Methode | Wirkung |
|---|---|
| `title(string $title)` | Eine Beschriftung über dem Code, zum Beispiel ein Dateiname |
| `lineNumbers(bool $on = true)` | Zeilennummern |
| `maxHeight(int\|string $height)` | Ab dieser Höhe scrollen: `400` (px) oder `'50vh'` |
| `wrap(bool $wrap = true)` | Lange Zeilen umbrechen statt seitlich zu scrollen |

### Flow (Prozess mit Live-Monitoring)

Ein Prozess oder Zustandsautomat, gezeichnet von
[@dskripchenko/flow](https://github.com/dskripchenko/flow): Verschieben,
Zoomen, Minikarte, ein Detailbereich, der Live-Zustand einer laufenden Instanz
(oder Zähler über alle Instanzen), Schalter, die Schritte ein- und
ausschalten, und Drill-down in Unterprozesse. Schreibgeschützt — das Modell
wird mit dem [`Flow`-Feld](fields-reference.md#flow) bearbeitet. Das Paket
wird nur geladen, wenn eine Seite einen Prozess enthält.

Das Modell ist das JSON des Pakets (`{version: 1, nodes, edges, groups?, meta?}`,
siehe dessen Modelldokumentation). `Layout::flow()` nimmt einen Schlüssel des
Screen-States oder das Modell selbst — ein Array oder einen JSON-String, der
beim Aufbau des Layouts geprüft wird (`InvalidArgumentException` bei einem
fehlerhaften):

```php
Layout::flow('process')                 // $state['process']
Layout::flow($order->workflow)          // das Modell als Array
Layout::flow(file_get_contents($path))  // oder als JSON
```

Ein vollständiges Beispiel — der Zustandsautomat einer Bestellung: Polling des
Snapshots der in einem Select gewählten Bestellung, Ein- und Ausschalten von
Schritten, ein per Referenz gehaltener Unterprozess und ein Button im
Detailbereich:

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
            'process' => OrderProcess::model(),   // Knoten: new, payment (subgraph: 'payment'), paid, shipped…
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
                    Button::make('Schritt wiederholen')->method('retryStep')->permission('orders.process.manage'),
                ])
                ->direction('LR')
                ->height(560),
        ];
    }

    /** Der Snapshot für die angezeigte Ebene: die Wurzel oder ein Unterprozess. */
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
            throw new ActionFailedException('Die Stornierung lässt sich nicht ausschalten.');
        }
        OrderProcess::setEnabled($change['kind'], $change['id'], $change['enabled'], $change['path']);
    }

    public function loadSubprocess(string $ref, array $node): array
    {
        return OrderProcess::subprocess($ref);   // ein Modell wie das der Wurzel
    }

    /** ['kind' => 'node', 'id' => 'shipped', 'path' => [], 'instance' => '1042'] */
    public function retryStep(array $selection): array
    {
        RetryStep::dispatch($selection['instance'], $selection['id']);

        return ['message' => 'Zur Wiederholung eingereiht'];
    }
}
```

Die Methoden sind die eigenen öffentlichen Methoden des Screens, aufgerufen
über seine Aktion `runMethod` mit positionalen `parameters` — dieselbe
Session, CSRF, Schichten und Prüfungen wie bei einem Button. Jede vom Layout
genannte Methode ist dem Server bekannt: Ein Benutzer, vor dem das Layout
verborgen ist (`canSee()`), erhält für alle ein 403, und
`onToggle(..., permission:)` sowie die Buttons des Detailbereichs verlangen
zusätzlich ihre Berechtigung — ohne sie werden Schalter und Buttons gar nicht
angezeigt.

| Methode | Wirkung |
|---|---|
| `poll(string $method, int\|float $seconds = 5)` | Ruft alle `$seconds` `$method(array $context)` für den [Monitoring-Snapshot](https://github.com/dskripchenko/flow/blob/main/docs/de/monitoring.md) auf — `nodes`, `edges`, `path`, `current`, `updatedAt`. `$context` enthält `path` (die betretenen Unterprozesse), `ref` und `instance`. Eine Anfrage zur Zeit, pausiert in einem verborgenen Tab, mit Backoff nach Fehlern; die Statuszeile zeigt die letzte Aktualisierung und Veraltung |
| `onToggle(string $method, ?string $permission = null)` | Schalter an Knoten und Kanten; `$method(array $change)` speichert `{kind, id, enabled, path}`. Zum Ablehnen `ActionFailedException` werfen (oder mit einem Fehler antworten): Der Schalter springt zurück, die Meldung erscheint in einem Toast |
| `toggleable(bool $on = true)` | Schalter ohne Methode: Die Änderung bleibt im Browser |
| `subgraphs(string\|array $subgraphs)` | Wie String-Referenzen in `subgraph` aufgelöst werden: eine Methode `(string $ref, array $node): array`, die das Modell liefert, oder die Modelle nach Referenz. Eingebettete Unterprozesse brauchen nichts |
| `instance(string\|int\|null $id)` / `instanceFrom(string $key)` | Die Instanz im Fokus, übergeben an die Poll-Methode und die Buttons des Detailbereichs; mit `instanceFrom()` wechselt ein Feld des Screens sie, und es wird sofort gepollt |
| `snapshot(array\|string $snapshot)` | Ein fester Snapshot oder der State-Schlüssel mit einem; `poll()` übernimmt nach seiner ersten Antwort |
| `actions(array $buttons)` | `Button`s im Detailbereich; die Methode erhält `{kind, id, path, instance}`, eine `message` in der Antwort erscheint als Toast, danach wird erneut gepollt |
| `direction('LR'\|'TB'\|'auto')` | Die Layoutrichtung; standardmäßig `meta.direction` des Modells, sonst `auto` (folgt der Form des Bereichs) |
| `edgeStyle('orthogonal'\|'smooth')` | Rechte Winkel (Standard) oder weiche Kurven |
| `height(int\|string $height)` | Die Höhe des Bereichs: `560` (px) oder `'70vh'`; standardmäßig 480px |
| `minimap(bool)`, `details(bool)`, `statusBar(bool)` | Minikarte, Detailbereich und Statuszeile; standardmäßig alle an |
| `title(string $title)` | Eine Beschriftung über dem Graphen |

Die Beschriftungen eines eingebetteten Modells (Knoten- und Kantenlabels,
Beschreibungen, der Titel) laufen wie alle anderen Beschriftungen durch die
JSON-Übersetzungen des Panels; ein Modell aus dem State wird so gezeigt, wie
es gespeichert ist.

### Dokumente (Diagramme, BPMN, DMN, draw.io, AsyncAPI)

Fünf Layouts zeigen ein Dokument, das aus seinem Quelltext gezeichnet wird, schreibgeschützt:

| Factory | Zeichnet | Paket |
|---|---|---|
| `Layout::diagram()` | Mermaid, PlantUML, Graphviz DOT, D2, DBML, Structurizr, Wardley-Maps | [@dskripchenko/diagrams](https://github.com/dskripchenko/diagrams) |
| `Layout::bpmn()` | BPMN-2.0-Prozessmodelle (Camunda Modeler, bpmn.io und andere Modellierer) mit Verschieben und Zoomen | [@dskripchenko/bpmn](https://github.com/dskripchenko/bpmn) |
| `Layout::dmn()` | DMN-Entscheidungsmodelle; ein Doppelklick auf eine Entscheidung öffnet ihre Tabelle | [@dskripchenko/bpmn](https://github.com/dskripchenko/bpmn) |
| `Layout::drawio()` | draw.io-Dateien (diagrams.net) als SVG in einem scrollbaren Bereich | [@dskripchenko/drawio](https://github.com/dskripchenko/drawio) |
| `Layout::asyncapi()` | AsyncAPI-Dokumente (YAML oder JSON): Server, Kanäle, Operationen, Nachrichten und ihre Payloads | [@dskripchenko/openapi](https://github.com/dskripchenko/openapi) |

Jedes Paket wird nur geladen, wenn eine Seite ein solches Dokument zeigt;
solange es lädt, steht ein Skeleton an seiner Stelle. Eine Quelle, die das
Paket nicht lesen kann, wird statt des Bildes als Hinweis mit dem Grund
angezeigt (bei einem Diagramm mit der Zeile und einem Tipp).

Die Quelle ist bei allen gleich — auch bei [Kanban und Calendar](#kanban-und-calendar):

```php
Layout::diagram('schema')                      // ein Bezeichner: $state['schema']
Layout::diagram("flowchart LR\n  A --> B")     // alles andere: die Quelle selbst
Layout::bpmn('process')->url('/files/order.bpmn')   // vom Browser geladen
```

Ein String, der ein Bezeichner ist (`/^[A-Za-z_][\w.-]*$/`), ist ein
Schlüssel des Screen-States: Er wird aus dem Formular (oder dem
Infolist-Datensatz) gelesen und bei Änderungen verfolgt — ein Feld auf
demselben Screen kann das Dokument wechseln. Alles andere ist der Inhalt.
`url()` lädt den Inhalt stattdessen im Browser, von einem Pfad oder einer URL
der eigenen Website des Panels, mit der Sitzung des Benutzers; eine Adresse
einer anderen Website wird abgelehnt. Jede Adresse wird pro Seite einmal
abgerufen.

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
            Layout::diagram('erd')->syntax('dbml')->title('Datenmodell')->height(480),
            Layout::bpmn('process')->highlight(['Task_ship']),
            Layout::dmn('rules')->decision('Decision_discount'),
            Layout::drawio('network')->url('/docs/network.drawio')->page('Rack B'),
            Layout::asyncapi('spec')->url('/docs/asyncapi.yaml'),
        ];
    }
}
```

| Methode | Layouts | Wirkung |
|---|---|---|
| `url(string $url)` | alle | Lädt den Inhalt im Browser von einem Pfad (`/files/a.bpmn`) oder einer `http(s)`-URL derselben Website, mit der Sitzung des Benutzers; ersetzt den State-Schlüssel oder den eingebetteten Inhalt |
| `title(string $title)` | alle | Eine Beschriftung über dem Dokument |
| `height(int\|string $height)` | alle | Die Höhe des Bereichs: `560` (px) oder `'70vh'`. Ohne sie wachsen Diagramme, draw.io und AsyncAPI mit ihrem Inhalt, mit ihr scrollen sie innerhalb des Bereichs; BPMN und DMN sind standardmäßig 480px hoch. Eine nicht positive Zahl oder ein String, der keine CSS-Länge ist, wirft `InvalidArgumentException` |
| `syntax(string $syntax)` | `diagram` | `mermaid`, `plantuml`, `dot`, `d2`, `dbml`, `structurizr` oder `wardley`; ohne sie wird die Notation am Anfang der Quelle erkannt (`@startuml` — PlantUML, `digraph` — DOT, `Table` — DBML…). Alles andere wirft `InvalidArgumentException` |
| `highlight(array $ids)` | `bpmn`, `dmn` | Die IDs der hervorzuhebenden Elemente — etwa der aktuelle Schritt eines Prozesses |
| `showDiagram(string\|int $diagram)` | `bpmn` | Welches Diagramm einer Datei mit mehreren: seine ID oder sein Index; standardmäßig das erste |
| `decision(string $id)` | `dmn` | Öffnet statt des Diagramms die Tabelle dieser Entscheidung |
| `page(int\|string $page)` | `drawio` | Welche Seite: ihr Index, ihre ID oder ihr Name; standardmäßig die erste |

`showDiagram()` heißt nicht `diagram()`, weil dieser Name von der Factory
`Layout::diagram()` belegt ist.

### Kanban und Calendar

Ein Kanban-Board und ein Ereigniskalender, als Markup geschrieben und von
`UidKanban` und `UidEventCalendar` des UI-Kits gezeichnet: Karten mit Tags,
Bearbeitern und Fälligkeitsdaten in Spalten; ganztägige und zeitgebundene
Ereignisse mit Wiederholungen in den Ansichten Tag, Woche, Monat, Jahr und
Agenda. Die Quelle funktioniert wie bei den
[Dokumenten](#dokumente-diagramme-bpmn-dmn-drawio-asyncapi): ein
State-Schlüssel, das Markup selbst oder `url()`.

Standardmäßig schreibgeschützt. Mit `editable()` verschiebt, ergänzt und
ändert der Benutzer Karten und Ereignisse (und bearbeitet das Markup im
Quelltext-Tab); nach jeder Änderung ruft das Layout eine Screen-Methode mit
dem ganzen neuen Markup auf:

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
                ->title('Sprint-Board')
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
            throw new ActionFailedException('Der Sprint ist abgeschlossen.');
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

Die Änderung wird sofort angezeigt. Zum Ablehnen `ActionFailedException`
werfen (oder mit einem Fehler antworten): Das Board kehrt zum Markup vor der
Änderung zurück, und die Meldung erscheint in einem Toast. Eine `message` in
der Antwort der Methode erscheint ebenfalls als Toast. Das Markup kommt wie
die anderen Strings der Anfrage an — durch die Middleware der Anwendung,
sodass `TrimStrings` Leerraum am Anfang und Ende entfernt.

Die Methoden sind die eigenen öffentlichen Methoden des Screens, aufgerufen
über seine Aktion `runMethod` mit positionalen `parameters` — dieselbe
Sitzung, CSRF, Layer und Prüfungen wie bei einem Button. Jede Methode, die das
Layout nennt, ist dem Server deklariert: Ein Benutzer, vor dem das Layout
verborgen ist (`canSee()`), erhält für alle ein 403, und eine Methode mit
Berechtigung verlangt zusätzlich diese. Ein Benutzer ohne die Berechtigung
erhält ein schreibgeschütztes Board (und keinen Sperr-Button): Das Layout
serialisiert `editable` / `lockable` als false.

| Methode | Layouts | Wirkung |
|---|---|---|
| `editable(string $method, ?string $permission = null)` | `kanban`, `calendar` | Bearbeitung an; `$method(string $markup)` erhält nach jeder Änderung das ganze Markup. Mit `ActionFailedException` ablehnen, um die Änderung zurückzunehmen |
| `lockable(string $method, ?string $permission = null)` | `kanban`, `calendar` | Der Sperr-Button; `$method(bool $locked)` erhält den neuen Zustand. Eine Ablehnung schaltet ihn mit einem Toast zurück |
| `locked(bool\|string $locked = true)` | `kanban`, `calendar` | Schreibgeschützt, was auch immer `editable()` sagt: ein Flag oder der State-Schlüssel, der es enthält |
| `initialView(string $view)` | `calendar` | Die zuerst gezeigte Ansicht: `day`, `week`, `month`, `year` oder `agenda`; standardmäßig `view:` aus dem Markup, sonst der Monat |
| `date(string $date)` | `calendar` | Das zuerst gezeigte Datum, `YYYY-MM-DD`; standardmäßig `date:` aus dem Markup, sonst heute |
| `views(array $views)` | `calendar` | Die angebotenen Ansichten, in dieser Reihenfolge |
| `url()`, `title()`, `height()` | `kanban`, `calendar` | Wie bei den [Dokumenten](#dokumente-diagramme-bpmn-dmn-drawio-asyncapi) |

Eine unbekannte Ansicht, ein Datum, das nicht `YYYY-MM-DD` ist, oder eine
leere Liste von Ansichten wirft `InvalidArgumentException`. `initialView()`
heißt nicht `view()`, weil dieser Name von der Factory `Layout::view()`
belegt ist. Das Markup selbst beschreibt die Dokumentation des UI-Kits zu
`UidKanban` und `UidEventCalendar`; dasselbe Markup in einem
` ```kanban `- oder ` ```calendar `-Block von [Markdown](#markdown) wird
schreibgeschützt angezeigt.

### Board (gemeinsam)

`Layout::board()` zeichnet ein gemeinsames Board — BoardEditor aus
[@dskripchenko/board](https://github.com/dskripchenko/board): Haftnotizen,
Formen, Verbinder, Text und Rahmen, die mehrere Personen gleichzeitig
bearbeiten, mit ihren Cursorn und Auswahlen live.

Das Layout ist nur die zeichnende Hälfte. Speicherung des Boards, Reihenfolge
der Änderungen und die Endpoints kommen aus dem Paket
[`dskripchenko/laravel-admin-board`](https://github.com/dskripchenko/laravel-admin-board),
das das Layout für ein Board selbst baut (seine Resource, das Board eines
Datensatzes); von Hand baut man es selten.

```php
Layout::board()
    ->endpoint('/boards/42')                    // die API des Boards, relativ zum Client des Panels
    ->user(['id' => '7', 'name' => 'Anna', 'color' => '#ec4899'])
    ->realtime(['ticketUrl' => '/realtime/ticket', 'channel' => 'admin-board.42'])
    ->readonly(! $canEdit)
    ->height('70vh')
```

| Methode | Wirkung |
|---|---|
| `endpoint(string $path)` | Der API-Pfad des Boards, relativ zum API-Client des Panels (`/boards/42`); Pflicht |
| `user(array $user)` | Wer bearbeitet: `id`, `name`, `color`, für die anderen neben dem Cursor sichtbar |
| `realtime(?array $realtime)` | `['ticketUrl' => …, 'channel' => …]`: der laravel-realtime-Kanal, auf dem der Server Änderungen veröffentlicht; `null` fragt ab |
| `pollInterval(int $ms)` | Wie oft ohne Realtime die Änderungen der anderen abgefragt werden, mindestens 250 ms; standardmäßig 1500 |
| `readonly(bool $readonly = true)` | Nur ansehen: nichts wird gesendet (der Server prüft das Recht trotzdem) |
| `title()`, `height()` | Eine Beschriftung und die Höhe des Kastens (standardmäßig 600px) |

Der Client lädt das Board aus `{endpoint}/snapshot`, sendet jede Änderung an
`{endpoint}/batches` (der Reihe nach, nach einem Netzwerkfehler erneut; eine
vom Server abgelehnte Änderung wird mit einer Meldung verworfen), teilt Cursor
über `{endpoint}/presence` und folgt den Änderungen der anderen:

- **mit Realtime** — über den Kanal, mit dem Browser-Client von
  laravel-realtime (`@dskripchenko/realtime`), den das Panel lädt, sobald ein
  Board einen Kanal hat. Nach einem Wiederverbinden wird das Verpasste aus
  `{endpoint}/poll` geholt. Ein Host mit eigenem Build des Clients übergibt
  ihn mit `setBoardRealtime(RealtimeClient)`.
- **ohne** — durch Abfragen von `{endpoint}/poll` alle `pollInterval` ms,
  solange der Tab sichtbar ist. Long Polling wird nicht verwendet: es hielte
  einen PHP-Worker pro offenem Board.

Ist das Board über das hinaus, was der Server noch im Log hat, lädt der Editor
es neu. Dasselbe Board auf einem Dashboard ist `Widget\BoardWidget`
([Widgets](concepts/widgets-and-dashboards.md#board)).

### Video und Audio (Medienplayer)

Die Player des UI-Kits, `UidVideo` und `UidAudio`: Fortschrittsleiste mit gepufferten Bereichen, Kapiteln und Vorschaubildern, Lautstärke, Menüs für Geschwindigkeit, Qualität und Untertitel, Vollbild, Bild-im-Bild, Tastenkürzel und die Media Session API. Startet ein Player, pausieren die anderen auf der Seite. Die Bedienelemente sprechen die Sprache des Panels. HLS (`.m3u8`, fMP4 oder MPEG-TS, AES-128, alternative Tonspuren) läuft über den Adapter des Kits, der erst geladen wird, wenn ein Stream erscheint.

```php
Layout::video([
    ['src' => '/media/intro-1080.mp4', 'label' => '1080p'],   // Quellen mit label bilden das Qualitätsmenü
    ['src' => '/media/intro-720.mp4', 'label' => '720p'],
])
    ->title('Einführung')
    ->poster('/media/intro.jpg')
    ->subtitles('/media/intro.en.vtt', 'en', 'English', default: true)
    ->subtitles('/media/intro.de.vtt', 'de', 'Deutsch')
    ->chapters('/media/intro.chapters.vtt')
    ->thumbnails('/media/intro.thumbs.vtt')   // Cues zeigen auf Sprite-Kacheln: sprite.jpg#xywh=0,0,160,90
    ->resume()                                // merkt sich die Position im Browser
    ->maxWidth(760),

Layout::video('/streams/intro/master.m3u8'),          // HLS, an der Endung erkannt
Layout::video('videos/intro.mp4')->disk('public'),    // ein Speicherpfad
Layout::video('recording'),                           // der Zustand des Screens: $state['recording']

Layout::audio('/media/episode-12.mp3')->title('Folge 12')->artist('Der Podcast')->waveform(),
Layout::audio([
    ['src' => '/media/a.mp3', 'title' => 'Intro', 'artist' => 'Band', 'duration' => 64],
    ['src' => '/media/b.ogg', 'title' => 'Outro', 'peaks' => $peaks],   // vorberechnete Spitzen, 0..1
])->waveform(),
Layout::audio('calls/2026-10-26.ogg')->disk('local')->mini(),
```

**Die Quelle.** Ein String ist eine URL, ein Speicherpfad oder ein Zustandsschlüssel: Medium, wenn er ein Schema oder einen Schrägstrich hat oder auf eine Medienendung endet (`intro.mp4`); sonst nennt er einen Schlüssel des Screen-Zustands oder des Datensatzes auf einer Ansichtsseite (`fromState('media.url')` sagt es ausdrücklich). Der Wert dort kann eine URL, ein Pfad, eine hochgeladene Datei `{url, mime, name, …}` (was ein `FileUpload` speichert), eine Liste davon (eine Playlist oder Videoquellen) oder für Video ein ganzes Objekt `{sources, poster, tracks, thumbnails}` sein. Ändert sich der Wert — etwa durch ein Select im selben Formular —, wechselt der Player.

**Speicherpfade.** Mit `disk()` werden Pfade zu URLs — auch Poster und WebVTT-Dateien: `Storage::url()` für eine Disk mit öffentlicher URL (`url` in ihrer Konfiguration oder ein Cloud-Treiber), der Streaming-Endpunkt des Panels `uploads/serve` für eine lokale Disk ohne. Dieser Endpunkt beantwortet Range-Anfragen, die das Spulen braucht (und Safari, um ein Video überhaupt abzuspielen); die Disk muss in `admin.uploads.servable_disks` stehen. Werte aus dem Zustand werden im Browser genauso aufgelöst. Für eine Disk mit signierten, temporären URLs gehören die URLs selbst in den Zustand.

Beide Player:

| Methode | Wirkung |
|---|---|
| `disk(string $disk)` | Speicherpfade über diese Disk auflösen |
| `fromState(string $key)` | Das Medium aus einem Zustandsschlüssel lesen |
| `resume(?string $key = null)` | Die Wiedergabeposition merken (localStorage); standardmäßig ist die URL des Mediums der Schlüssel |
| `loop()`, `preload('none'\|'metadata'\|'auto')` | |
| `exclusive(false)` | Neben den anderen Playern spielen, statt sie zu pausieren |
| `hotkeys(false)`, `rates([1, 1.5, 2])` | Tastenkürzel, das Geschwindigkeitsmenü |
| `crossorigin('anonymous')` | Für Medien, Spuren und Wellenformen von einem anderen Origin |
| `maxWidth(int\|string $width)` | Die größte Breite: `760` (px) oder `'40rem'` |
| `hlsOptions(array $options)` | Der HLS-Adapter: `preferNative` (`false` — der Adapter auch dort, wo der Browser HLS selbst abspielt, mit Qualitäts- und Tonspurmenü), `startLevel`, `audioLanguage`, `maxBufferAhead`, `maxBufferBehind`, `retries`, `initialBandwidth`, `capToPlayerSize` |

Video:

| Methode | Wirkung |
|---|---|
| `source($src, $label = null, $type = null)`, `quality($label, $src)` | Eine Quelle hinzufügen; Quellen in der Reihenfolge der Präferenz, solche mit label bilden das Qualitätsmenü |
| `hls($src, $label = null)`, `mime($type)` | Ein Stream, dessen Adresse nicht auf `.m3u8` endet; der Typ der Quellen ohne Typ |
| `title($title)` | Eine Beschriftung über dem Player und sein zugänglicher Name |
| `poster($url)`, `thumbnails($vtt)` | Das Bild vor der Wiedergabe; das WebVTT mit Sprite-Kacheln über der Fortschrittsleiste |
| `subtitles($src, $lang, $label, $default)`, `captions(...)`, `chapters($src)`, `tracks([...])` | WebVTT-Spuren |
| `autoplay()` | Sofort starten, stumm (Browser blockieren Autoplay mit Ton); `muted()`, `controls(false)` |
| `aspectRatio('16 / 9')` | Das Seitenverhältnis, bevor die Videogröße bekannt ist; `'4:3'` geht auch |
| `autoHide(int $ms)` | Leerlaufzeit, bevor die Bedienelemente während der Wiedergabe verschwinden |

Audio:

| Methode | Wirkung |
|---|---|
| `track($src, ['title' => …, 'artist' => …, 'duration' => …, 'peaks' => […]])` | Einen Titel hinzufügen; zwei oder mehr ergeben eine Playlist mit Zurück/Weiter und Titelliste |
| `title()`, `artist()`, `mime()` | Für einen einzelnen Titel |
| `waveform()` | Die Wellenform als Fortschrittsleiste, im Browser dekodiert (gleicher Origin oder CORS; nicht für Streams) |
| `peaks([...])` | Vorberechnete Spitzen 0..1: die Wellenform ohne Dekodieren — für lange Aufnahmen |
| `mini()` | Eine Zeile: Play, eine kurze Leiste, die Zeit |
| `compact()` | Die volle Zeile ohne Lautstärke und Geschwindigkeit |
| `playlist(false)`, `autoAdvance(false)` | Die Titelliste ausblenden; nach jedem Titel anhalten |

`VideoEntry` und `AudioEntry` zeigen eine Datei des Datensatzes auf einer Ansichtsseite (siehe [Infolist](#infolist-schreibgeschützt)), `TableColumn::asAudio()` und `asVideo()` in einer Tabelle ([Spalten](concepts/resources.md#spalten-tabelle)), und ein `FileUpload`-Feld zeigt unter einer hochgeladenen Audio- oder Videodatei einen Player.

### View (eigene Vue-Komponente)

```php
Layout::view('my-custom-card', [
    'count' => 42,
    'label' => 'Einträge',
]),
```

Frontend:

```ts
import { registerLayout } from '@dskripchenko/laravel-admin'
import MyCustomCard from './MyCustomCard.vue'
registerLayout('my-custom-card', MyCustomCard)
```

## Sichtbarkeit

```php
Layout::block('Nur für Admins', [...])
    ->canSee(fn () => auth()->user()?->hasAccess('admin.*')),
```

## Komposition

Layouts lassen sich verschachteln:

```php
Tabs::make([
    'Formular' => [
        Block::make('Grunddaten', [
            Columns::make([
                Input::make('first_name'),
                Input::make('last_name'),
            ]),
        ]),
        Block::make('Einstellungen', [
            Switcher::make('is_active'),
            Select::make('plan')->options([...]),
        ]),
    ],
    'Audit' => [
        AuditTrail::for(\App\Models\User::class),
    ],
]),
```

## toArray-Vertrag

Jedes Layout wird serialisiert zu:

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

Die Props werden außerdem auf der obersten Ebene ausgebreitet, und `items`
wiederholt `children`. `children` sind rekursiv (`toArray`s anderer Layouts
oder `toArray`s von Feldern).
Der Frontend-`LayoutRenderer` löst den Typ aus einer Registry auf und
arbeitet rekursiv weiter.

## Siehe auch

- [Felder-Referenz](fields-reference.md)
- [Frontend-Erweiterung](frontend-extension.md) — eigene Layouts registrieren
- [Screens](concepts/screens.md)
