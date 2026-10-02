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
| `AuditTrail` | `AuditTrail::for(User::class)` | Audit-Zeitleiste des angezeigten Datensatzes |
| `Listener` | `Layout::listener([...])->listen([...])` | Teil eines Formulars, den der Server neu rendert, wenn sich beobachtete Felder ändern |
| `ResourceTable` | `ResourceTable::for(ItemResource::class)` | Tabelle der Datensätze einer anderen Resource, die zum bearbeiteten Datensatz gehören |
| `ResourceIndex` | `Layout::resourceIndex(OrderResource::class)` | Live-Listenseite einer Resource (Tabelle oder Baum) auf einem Screen |

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
| `\| a \| b \|` + `\|---\|:---:\|` | Tabellen, mit Spaltenausrichtung |
| `> quote` | Blockzitat |
| `> **Note** …`, `> **Warning** …` | Hinweisbox; außerdem `Tip`, `Important`, `Caution` und die GitHub-Form `> [!NOTE]` |
| `**bold**`, `*italic*`, `~~struck~~`, Code-Spans in Backticks | Inline-Markup |
| `[text](url)`, `![alt](src)` | Links und Bilder |
| `-`/`*`/`1.`-Listen, `---` | Listen und Trennlinien |

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
