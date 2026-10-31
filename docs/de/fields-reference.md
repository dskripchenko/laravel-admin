---
title: Felder-Referenz
audience: developer
status: stable
locale: de
translated_from: en/fields-reference.md
translated_at: 2026-10-02
---

# Felder-Referenz

Alle Felder erweitern `Dskripchenko\LaravelAdmin\Field\Field`. Gemeinsam ist ihnen:

```php
->required()           // fügt 'required' zu den Validierungsregeln hinzu
->placeholder('Text')
->help('Hinweis, der unter dem Feld angezeigt wird')
->title('Eigene Beschriftung') // Standard: der lesbar gemachte Name, `opens_at` → "Opens At"
->default('value')     // initialer Formular-State
->onCreate(false)      // im Erstellungsformular ausblenden; ebenso onUpdate(), onView()
->visibleWhen('driver', 's3')          // nur anzeigen, solange ein anderes Feld einen Wert hat
->visibleWhen('driver', ['s3', 'minio']) // ...oder einen von mehreren Werten
->span(6)              // Breite im 12-Spalten-Raster eines Rows-Layouts
->canSee(fn () => Gate::allows('manage-billing')) // serverseitige Sichtbarkeit
->readonly()
->disabled()
->rules(['min:3', 'max:255'])  // explizite Laravel-Regeln
```

`rules()` ersetzt die zuvor gesetzten expliziten Regeln; `required()` bleibt in
jedem Fall erhalten. Zusätzlich zu den expliziten Regeln fügt jedes Feld
implizite Regeln aus seinem Typ hinzu: `numeric`/`integer` und `min`/`max` für
Zahlen, `email` für `->type('email')`, `date` für Datumswerte, `array` für
Mehrfachauswahlen usw. — so werden die Grenzen nur einmal deklariert, am Feld.

Methoden, die eine Feldklasse nicht definiert, werden als Attribute gespeichert
und der SPA-Komponente als Props übergeben. So funktionieren `->placeholder()`,
`->type()` oder `->rows()`, und deshalb schlägt eine falsch geschriebene
Methode auch stillschweigend fehl: Sie wird zu einem Attribut, das niemand
liest.

## Texteingaben

### Input

```php
Input::make('title')->required(),
Input::make('email')->type('email'),
Input::make('phone')->type('tel'),
Input::make('website')->type('url'),
```

Für ein Passwort verwenden Sie `Password` (siehe unten), für eine Farbe
`ColorPicker`.

### Textarea

```php
Textarea::make('description')->rows(6),
```

### Number

```php
Number::make('price')->min(0)->max(99999)->step(0.01),
Number::make('quantity')->integer(),
```

### Slug

Ein URL-Slug, abgeleitet aus einem anderen Feld des Formulars:

```php
Slug::make('slug')->from('title')->separator('-'),
```

`from()` benennt das Quellfeld. Die SPA füllt den Slug, während sich die Quelle
ändert, bis er von Hand bearbeitet wird; wird er geleert, übernimmt wieder die
Quelle. Ein gespeicherter Slug, der nicht mehr zu seiner Quelle passt, bleibt
unangetastet. Mit `reactive(false)` wird nur ein leerer Slug gefüllt: Der Slug
eines neuen Datensatzes folgt der Quelle, bis er bearbeitet wird, der Slug eines
gespeicherten Datensatzes wird nie neu geschrieben.

Die Umwandlung ist `Slug::generate($title, $separator)`, und die SPA führt eine
Portierung davon mit derselben Transliterationstabelle aus, sodass der
vorgeschlagene Slug derjenige ist, den der Server erzeugen würde: Kyrillisch
(Russisch, Ukrainisch, Belarussisch, Serbisch, Kasachisch), Griechisch und
lateinische Buchstaben mit diakritischen Zeichen werden transliteriert
(`Щука и ёж` → `shchuka-i-yozh`), Leerzeichen, Bindestriche und `_` werden zum
Trennzeichen, `@` wird zu `at`, andere Zeichen werden entfernt. Verwenden Sie
`Slug::generate()` auf dem Server, wo kein Formular beteiligt ist — bei einem
Import, einem Seeder, einem Save-Hook.

### Code

Code-Editor mit Syntaxhervorhebung:

```php
Code::make('snippet')->language('javascript')->lineNumbers()->height(400),
```

`theme()` wird akzeptiert, aber ignoriert: Der Editor folgt dem Theme des
Panels.

### Markdown

Markdown-Editor mit Live-Vorschau:

```php
Markdown::make('content')->height('300px'),
Markdown::make('notes')->preview(false)->toolbar(false),
```

Die Vorschau zeichnet auch ```` ```mermaid ````-Diagramme (siehe [Markdown-Layout](layouts-reference.md#markdown)); ihre Links öffnen in einem neuen Tab, damit das Formular nicht verlassen wird.

### Wysiwyg

Standard-Editor: `@dskripchenko/wysiwyg` (ohne Abhängigkeiten).

```php
Wysiwyg::make('body'),
Wysiwyg::make('summary')->preset('minimal'),     // 'minimal' | 'default' | 'full'
Wysiwyg::make('page')->preset('full')->uploadImages(),
```

Das HTML wird beim Speichern standardmäßig bereinigt; `->sanitize(false)`
schaltet das für Inhalte ab, denen Sie vertrauen.

Im Editor wird ein Codeblock ` ```mermaid ` als Diagramm angezeigt und ein
Block ` ```openapi ` als API-Widget, das an Ort und Stelle bearbeitet wird:
Vorschau, Quelle in YAML oder JSON und ein Request-Konstruktor (Methode, URL,
Parameter, Authentifizierung, Body, Antworten). Das Slash-Menü fügt sie ein:
«Diagram» (`/diagram`) und «API request» (`/api`). Das gespeicherte HTML
behält den Codeblock, daher wird derselbe Inhalt im Markdown-Layout oder
überall, wo `renderBlocks` von @dskripchenko/openapi läuft, gleich dargestellt.

Jede Blocksprache, die das Panel zeichnet (siehe [Markdown](layouts-reference.md#markdown)),
erscheint im Editor genauso. Ein ` ```kanban `- oder ` ```calendar `-Block wird
direkt dort bearbeitet — Karten verschieben, Termine ziehen, der
Termin-Baukasten —, und das Markup des Blocks folgt. Das Slash-Menü bietet
außerdem «Process» (`/flow`), «Kanban» (`/kanban`), «Calendar» (`/calendar`),
«AsyncAPI» (`/asyncapi`), «Video» (`/video`) und «Audio» (`/audio`), jeweils mit
einem Startbeispiel. Der Sanitizer behält die Klasse `language-…` eines
Codeblocks (und `text-align` einer Tabellenzelle), sodass ein Block auch nach
dem Speichern gezeichnet wird.

Der Vollbild-Button der Toolbar breitet den Editor über das ganze Fenster aus; Escape beendet ihn.

Adapter für Quill und TinyMCE werden im npm-Paket als Subpath-Exports
mitgeliefert; der Host installiert den Editor selbst (`quill` +
`@vueup/vue-quill` oder `tinymce` + `@tinymce/tinymce-vue`) und registriert die
Komponente unter dem Typ `wysiwyg`:

```ts
import { registerField } from '@dskripchenko/laravel-admin'
import { QuillField } from '@dskripchenko/laravel-admin/quill'
// oder: import { TinymceField } from '@dskripchenko/laravel-admin/tinymce'

registerField('wysiwyg', QuillField)
```

## Auswahl

### Select

```php
Select::make('status')->options([
    'draft' => 'Entwurf',
    'published' => 'Veröffentlicht',
])->required(),
```

Optionen können auch aus einem Enum oder einem Model stammen:

```php
Select::make('status')->fromEnum(ArticleStatus::class),
Select::make('category_id')->fromModel(Category::class, 'id', 'title'),
Select::make('country')->options([...])->searchable()->clearable(),
```

### Combobox

Ein Select, das auch einen Wert außerhalb seiner Liste akzeptiert — die
Optionen sind Hinweise, keine Regel. Verwenden Sie `Select`, wo die Menge
wirklich abgeschlossen ist.

```php
Combobox::make('model')
    ->options(['claude-opus-5' => 'Claude Opus 5', 'claude-sonnet-5' => 'Claude Sonnet 5'])
    ->clearable(),
```

`creatable()` ist standardmäßig aktiv; `->creatable(false)` beschränkt die
Eingabe auf die Liste.

### Radio

```php
Radio::make('plan')->options([...])->inline(),
```

### Checkbox / Switch

```php
Checkbox::make('agree')->title('Ich stimme zu'),
Switcher::make('is_active')->title('Aktiv'),
Switcher::make('published')->title('Status')->labels('Veröffentlicht', 'Entwurf'),
```

`Switcher` ist ein Kippschalter. Mit `labels($on, $off)` folgt die Beschriftung
daneben dem Zustand, und der Titel des Feldes steht darüber.

## Datum / Uhrzeit

```php
DatePicker::make('start_date'),
DatePicker::make('birthday')->min('1900-01-01')->max(now()),
DatePicker::make('publish_at')->withTime(),
DateRange::make('period')->presets(['today', 'last_7_days']),
TimePicker::make('start_time')->step(15),
```

`withTime()` fügt neben dem Datum eine Zeitauswahl hinzu und stellt das
gespeicherte Format auf `Y-m-d H:i:s` um. Ein anderes `format()` bestimmt, wie
der Wert geschrieben wird: `Y-m-d H:i` lässt die Sekunden weg, `Y-m-d\TH:i:s`
verbindet mit einem `T`. Eine vor dem Datum gewählte Uhrzeit bleibt erhalten,
bis das Datum gewählt wird; eine fehlende Uhrzeit ist Mitternacht. `DateRange`
speichert `{from: 'YYYY-MM-DD', to: 'YYYY-MM-DD'}`.

## Numerisch

```php
Slider::make('volume')->min(0)->max(100)->step(5)->marks([0 => 'Aus', 100 => 'Max']),
Rating::make('quality')->count(5)->half(),
```

## Dateien

```php
FileUpload::make('contract')
    ->maxSize(5 * 1024)        // KB
    ->accept(['application/pdf', '.docx']),

FileUpload::make('avatar')->image(),   // nur Bilder, mit Vorschau

ImageCropper::make('hero')
    ->aspectRatio(16 / 9)
    ->minCrop(800, 450)
    ->outputSize(1600, 900)
    ->quality(0.85),
```

Die SPA lädt die Datei zuerst über den `uploads`-Endpoint des Panels hoch und
legt `{disk, path, url, name, size, mime}` im Formular-State ab. Disk und
Verzeichnis stammen aus `config('admin.uploads.disk')` und
`config('admin.uploads.directory')`. Eine Datei pro Feld: `multiple()` und
`maxFiles()` formen die Validierungsregeln, aber der Uploader der SPA
verarbeitet eine einzelne Datei.

Eine Audio- oder Videodatei zeigt den Player des Kits unter ihrem Namen, ein Bild seine Vorschau; die Ansichtsseite zeigt das Feld genauso, mit dem Link zur Datei. Videos auf einer lokalen Disk werden mit Range-Unterstützung gestreamt, sodass sie sich spulen lassen und auch in Safari laufen.

## Relationen

### RelationSelect

```php
RelationSelect::make('author_id')
    ->relation(User::class, 'name')   // Model, Beschriftungsspalte, Wertspalte = 'id'
    ->preload(['team']),
```

Die Optionen werden beim Serialisieren des Formulars aus dem verknüpften Model
geladen (bis zu 100 Zeilen; `->eager(500)` erhöht das Limit), daher eignet sich
das für Nachschlagetabellen. Für eine große Tabelle verwenden Sie
`ResourcePicker`.

### RelationTable

Eine schreibgeschützte Tabelle verknüpfter Datensätze im Bearbeitungsformular —
eine HasMany- oder BelongsToMany-Relation. Die Zeilen stammen aus dem Wert des
Feldes, laden Sie die Relation also mit dem Datensatz:

```php
RelationTable::make('items')
    ->relation('items')
    ->columns([
        TableColumn::make('name')->label('Name'),
        TableColumn::make('price')->asMoney('USD'),
    ]),
```

Die Spalten sind `TableColumn`s, mit denselben Presets wie in einer
Resource-Liste.

### ResourcePicker

Datensätze einer anderen registrierten Resource, ausgewählt in einem Dialog.
Anders als `RelationSelect`, das ein Model in ein Select einliest, geht der
Picker über die Ziel-**Resource**: Der Dialog listet, was ihr Index listet, mit
deren Suche, Filtern und Paginierung, und nur für Benutzer, die die Berechtigung
`admin.{slug}.view` des Ziels besitzen.

```php
ResourcePicker::make('cover_id')
    ->resource(MediaResource::class),       // oder ein Slug: 'media-library'

ResourcePicker::make('related_ids')
    ->resource('products')
    ->multiple()                            // eine geordnete Liste von Schlüsseln
    ->maxItems(5)
    ->filters(['status' => 'active'])       // fest, in der Toolbar ausgeblendet
    ->perPage(24)
    ->layout('list')                        // 'grid' | 'list'; Standard: grid, wenn Datensätze Vorschauen haben
    ->dialogSize('xl'),                     // 'lg' | 'xl' | 'full'
```

Der Wert ist der Schlüssel des Datensatzes oder mit `multiple()` eine Liste von
Schlüsseln in der Reihenfolge, die der Benutzer festgelegt hat; casten Sie die
Spalte eines Mehrfach-Pickers auf `array`. Beim Speichern muss jeder Schlüssel
einen Datensatz der `indexQuery()` des Ziels bezeichnen, sodass eine
Einschränkung des Index auch einschränkt, was verknüpft werden kann.

Der Dialog zeichnet jeden Datensatz aus `Resource::pickerItem()`: den Titel aus
`recordTitle()`, den Untertitel aus `recordSubtitle()` und das Vorschaubild aus
`pickerPreview()`. Überschreiben Sie diese in der Ziel-Resource:

```php
public function pickerPreview(Model $row): ?string
{
    return $row->avatar_url;
}
```

`uploadTo()` fügt dem Dialog einen Upload-Button hinzu. Die Datei wird als
Multipart-Daten an einen Pfad unterhalb der API des Panels gesendet; die
Response ist der neue Datensatz (oder enthält ihn unter `responseKey`), und er
wird ausgewählt:

```php
ResourcePicker::make('document_id')
    ->resource('documents')
    ->uploadTo('/documents/files/upload', permission: 'admin.documents.create',
        fileField: 'file', responseKey: 'document', data: ['folder' => 'contracts'],
        accept: 'application/pdf'),
```

Der Button wird Benutzern angezeigt, die `permission` besitzen — standardmäßig
die `create`-Berechtigung des Ziels. Auf der Ansichtsseite zeigt das Feld die
ausgewählten Datensätze mit ihren Vorschauen, jeweils verlinkt auf die
Ansichtsseite des Ziels.

### MorphSwitcher

Für polymorphe Relationen:

```php
MorphSwitcher::make('subject')
    ->morph('article', Article::class, 'title')
    ->morph('product', Product::class),          // Anzeigespalte standardmäßig 'name'

// oder mehrere auf einmal, Alias => Model:
MorphSwitcher::make('subject')->morphMany([
    'article' => Article::class,
    'product' => Product::class,
]),
```

Der State ist `{type, id}`. Die Datensätze jedes Typs (bis zu 100) werden als
Optionen in das Formular geladen.

## Zusammengesetzt

### Repeater

Liste von Unterformularen mit variabler Länge:

```php
Repeater::make('tags')
    ->fields([
        Input::make('name')->required(),
        Input::make('color'),
    ])
    ->minItems(0)->maxItems(10)
    ->defaultItem(['color' => '#888888']),
```

`addable()`, `removable()` und `reorderable()` schalten die Buttons um.

### Group

Verschachtelte Felder, die als ein Objekt im State gespeichert werden —
`contact.email`, `contact.phone` — und als Array validiert werden:

```php
Group::make('contact')
    ->title('Kontaktdaten')
    ->fields([
        Input::make('email'),
        Input::make('phone'),
    ])
    ->layout('columns')        // 'rows' (Standard) | 'columns' | 'inline'
    ->collapsed(),
```

Für eine rein visuelle Gruppierung verwenden Sie ein Layout wie
`Layout::block()` — siehe die [Layouts-Referenz](layouts-reference.md).

### KeyValue

Frei wählbare Schlüssel/Wert-Paare:

```php
KeyValue::make('headers')
    ->keyLabel('Header')
    ->valueLabel('Wert')
    ->allowedKeys(['Accept', 'Authorization']),  // optional
```

### TagsInput

```php
TagsInput::make('tags')
    ->suggestions(['php', 'vue', 'laravel'])
    ->maxItems(8),
```

Vorschläge schränken die Eingabe nicht ein: Jede beliebige Zeichenkette kann
hinzugefügt werden.

## Baum / Hierarchie

### TreeSelect

```php
TreeSelect::make('category_id')
    ->tree([
        ['value' => 1, 'label' => 'Elektronik', 'children' => [
            ['value' => 2, 'label' => 'Telefone'],
        ]],
    ])
    ->multiple(),

// oder aus einem selbstreferenzierenden Model:
TreeSelect::make('category_id')
    ->fromModel(Category::class, 'parent_id', 'id', 'name')
    ->selectableParents(false),   // nur Blätter
```

### Cascader

Kaskadierendes Dropdown für verschachtelte Optionen:

```php
Cascader::make('location')
    ->options([
        ['value' => 'us', 'label' => 'USA', 'children' => [...]],
    ]),
```

## Spezial

### TranslatableInput

Tabs pro Locale; der State ist `{en: '...', ru: '...'}`:

```php
TranslatableInput::make('title')->locales(['en', 'ru', 'de']),
TranslatableInput::make('body')->multiline()->locales(['en', 'ru']),
TranslatableInput::make('name')->requireAllLocales(),
```

Ohne `locales()` stammt die Liste aus `config('admin.ui.available_locales')`.
Siehe [i18n](concepts/i18n.md).

### Builder

Blockliste im Stil eines Page-Builders (für CMS-Hosts). Jeder Blocktyp wird mit
seinen eigenen Feldern deklariert; der State ist eine Liste von `{type, data}`:

```php
Builder::make('blocks')
    ->block('hero', [
        Input::make('title')->required(),
        Markdown::make('subtitle'),
    ], label: 'Hero', icon: 'image')
    ->block('gallery', [
        FileUpload::make('image')->image(),
    ])
    ->maxBlocks(20),
```

### Hidden

```php
Hidden::make('uuid'),
```

### Label

Reine Anzeige, schreibgeschützt — weder bearbeitbar noch mit abgeschickt.
Zeigt den Wert des States für seinen Namen oder einen festen `->value()`:

```php
Label::make('id')->title('Datensatz-ID'),
Label::make('note')->value('Änderungen werden nach einem Neustart wirksam.'),
```

### ColorPicker

```php
ColorPicker::make('brand_color')
    ->format('hex')                       // 'hex' | 'rgb' | 'hsl'
    ->palette(['#1e88e5', '#43a047'])
    ->withAlpha(),
```

### Password

```php
Password::make('password')->required()->confirmed()->revealable(),
```

`confirmed()` fügt die Regel `confirmed` hinzu, daher benötigt das Formular
zusätzlich ein Feld `password_confirmation`.

### Generated

Eine zufällige Zeichenkette, die im Browser erzeugt wird, wenn das
Erstellungsformular geöffnet wird — Tokens, geheime Schlüssel —, mit einem
Button „Generate“:

```php
Generated::make('api_key')->length(40)->charset('abcdef0123456789'),
```

### Flow

Ein visueller Editor für Prozesse und Zustandsautomaten — der FlowEditor von
[@dskripchenko/flow](https://github.com/dskripchenko/flow): eine Palette von
Knotentypen, Verbinden per Ziehen, Rückgängig/Wiederholen, Ausrichten und
automatisches Layout, ein Eigenschaftenbereich, eine Liste der Probleme,
Unterprozesse an Ort und Stelle bearbeitet, JSON-Export und JSON/Mermaid-Import.
Der Wert ist das Modell als JSON-Objekt — speichern Sie es in einer JSON-Spalte
mit `array`-Cast; es wird wie jedes andere Feld mit dem Formular gesendet. Das
Paket wird nur geladen, wenn ein Formular das Feld enthält.

```php
Flow::make('workflow')
    ->palette(['start', 'state', 'task', 'decision', 'end', 'note'])
    ->locked(['start'])
    ->height(560)
    ->required(),
```

| Methode | Wirkung |
|---|---|
| `palette(array $types)` | Die Knotentypen der Elementleiste, in dieser Reihenfolge; standardmäßig alle. Der Server akzeptiert dann nur diese Typen (und die gesperrten) |
| `locked(array $types)` | Knotentypen, die der Editor weder ändern noch löschen lässt |
| `readonly()` | Nur ansehen |
| `height(int\|string)` | Die Höhe des Editors; standardmäßig 520px |
| `direction('LR'\|'TB'\|'auto')`, `edgeStyle('orthogonal'\|'smooth')` | Wie bei [`Layout::flow()`](layouts-reference.md#flow-prozess-mit-live-monitoring) |
| `minimap(bool)`, `snapGrid(int)` | Die Minikarte (standardmäßig aus) und das Raster, an dem Knoten einrasten (`0` schaltet das Einrasten aus) |
| `mermaidImport(bool)` | „Mermaid importieren“ im Menü JSON/Mermaid; standardmäßig an |
| `exportName(string)` | Der Dateiname des JSON-Exports; standardmäßig `{name}.json` |

`ValidationRulesExporter` ergänzt `array` und die Regel `FlowModel`: die Form
des Modells (Knoten, Kanten und Gruppen mit eindeutigen IDs, Kanten zwischen
vorhandenen Knoten, eingebettete Unterprozesse ebenso geprüft) und, mit einer
Palette, die Knotentypen. Für einen eigenen Validator: `'workflow' =>
['required', Flow::rule()]`. Die Warnungen des Editors — unerreichbare
Schritte, Sackgassen — lassen die Validierung nicht scheitern. Gesperrte Typen
erzwingt nur der Editor; prüfen Sie sie auf dem Server, wenn es darauf ankommt.

Auf der Ansichtsseite zeigt `FlowEntry` das Feld schreibgeschützt.

### Bpmn und Dmn

Editoren für BPMN-2.0-Prozesse und DMN-Entscheidungsmodelle — `BpmnEditor`
und `DmnEditor` aus [@dskripchenko/bpmn](https://github.com/dskripchenko/bpmn):
eine Palette, Auswahl, Verschieben und Größenänderung, Verbinden, ein
Kontextmenü, Namen direkt an Ort und Stelle, ein Eigenschaftenfeld (BPMN),
Entscheidungstabellen und literale Ausdrücke einen Klick entfernt (DMN),
Rückgängig, Verschieben und Zoom. Der Wert ist das XML der Datei — dieselbe
Datei, die Camunda Modeler, bpmn.io und andere Modellierer lesen und
schreiben, ihre Erweiterungen bleiben erhalten. Speichern Sie es in einer
Textspalte (`MEDIUMTEXT` oder größer für große Modelle); es wird wie jedes
andere Feld mit dem Formular gesendet. Das Paket wird nur geladen, wenn ein
Formular das Feld enthält, und der Editor spricht die Sprache des Panels
(Englisch oder Russisch).

```php
use Dskripchenko\LaravelAdmin\Field\Bpmn;
use Dskripchenko\LaravelAdmin\Field\Dmn;

Bpmn::make('process_xml')
    ->title('Prozess')
    ->height(640)
    ->properties()
    ->required(),

Dmn::make('rules_xml')
    ->title('Rabattregeln')
    ->decision('Decision_discount')
    ->maxSize(512 * 1024),
```

| Methode | Felder | Wirkung |
|---|---|---|
| `height(int\|string)` | `Bpmn`, `Dmn` | Die Höhe des Editors; standardmäßig 560px |
| `maxSize(int $bytes)` | `Bpmn`, `Dmn` | Die größte Datei, die der Server annimmt; standardmäßig 2 MB. Halten Sie sie innerhalb der Spalte |
| `readonly()`, `disabled()` | `Bpmn`, `Dmn` | Der Editor wird zum Betrachter |
| `properties(bool)` | `Bpmn` | Das Eigenschaftenfeld ist von Anfang an offen |
| `diagram(string\|int)` | `Bpmn` | Welches Diagramm einer Datei mit mehreren: seine id oder sein Index; standardmäßig das erste |
| `decision(string $id)` | `Dmn` | Öffnet die Logik dieser Entscheidung statt des Diagramms |

`ValidationRulesExporter` fügt `string` und die Regel `BpmnModel` /
`DmnModel` hinzu. Ein leerer Wert besteht (ob er Pflicht ist, bestimmt
`required()`); alles andere muss sein:

- nicht größer als die Grenze, die vor dem Parsen des XML geprüft wird;
- XML ohne DOCTYPE, geparst ohne Netzwerk und ohne Ersetzung von Entitäten —
  so lassen sich weder externe Entitäten noch Entitätsexpansion nutzen; ein
  Parserfehler wird zur Validierungsmeldung;
- ein Dokument, dessen Wurzel `definitions` im Namensraum des BPMN-Modells
  (`http://www.omg.org/spec/BPMN/20100524/MODEL`) oder in einem Namensraum
  des DMN-Modells ist (DMN 1.1 bis 1.5 — die Namensräume, die
  `@dskripchenko/bpmn` liest).

Die Regel prüft, dass der Editor die Datei öffnen kann, nicht den Prozess
selbst: Gateways ohne ausgehende Flüsse, Entscheidungen ohne Regeln und
Ähnliches bestehen. Für einen eigenen Validator:
`'process_xml' => ['required', Bpmn::rule()]`,
`Dmn::rule(maxBytes: 1024 * 1024)`.

Auf der Ansichtsseite zeigen `BpmnEntry` und `DmnEntry` die Felder
schreibgeschützt (die Betrachter der [BPMN- und DMN-Layouts](layouts-reference.md#dokumente-diagramme-bpmn-dmn-drawio-asyncapi)).

### Kanban und Calendar

Ein Kanban-Board und ein Ereigniskalender als Markup — dasselbe Markup wie in
[`Layout::kanban()` / `Layout::calendar()`](layouts-reference.md#kanban-und-calendar)
und in ` ```kanban `- / ` ```calendar `-Blöcken —, im Formular bearbeitet mit
`UidKanban` und `UidEventCalendar` des UI-Kits. Karten werden zwischen
Spalten verschoben, hinzugefügt und geändert; Ereignisse werden gezogen, in
der Länge verändert, hinzugefügt und geändert; das Markup lässt sich im
Quelltext-Tab bearbeiten. Jede Änderung wird in den Wert des Formulars
geschrieben und mit dem Formular gespeichert. Der Wert ist das Markup, ein
String in einer Textspalte.

```php
use Dskripchenko\LaravelAdmin\Field\Calendar;
use Dskripchenko\LaravelAdmin\Field\Kanban;

Kanban::make('board')
    ->title('Board')
    ->height(480),

Calendar::make('schedule')
    ->initialView('week')
    ->date('2026-11-02')
    ->views(['week', 'month', 'agenda']),
```

| Methode | Felder | Wirkung |
|---|---|---|
| `height(int\|string)` | `Kanban`, `Calendar` | Die Höhe des Rahmens, der dann scrollt; ohne sie wächst der Rahmen mit dem Inhalt |
| `maxLength(int $characters)` | `Kanban`, `Calendar` | Das längste Markup, das der Server annimmt; standardmäßig 65 535 (was eine `TEXT`-Spalte fasst) |
| `readonly()`, `disabled()` | `Kanban`, `Calendar` | Schreibgeschützt |
| `initialView(string $view)` | `Calendar` | Die zuerst gezeigte Ansicht: `day`, `week`, `month`, `year` oder `agenda`; sonst `view:` des Markups, sonst der Monat |
| `date(string $date)` | `Calendar` | Das zuerst gezeigte Datum, `YYYY-MM-DD`; sonst `date:` des Markups, sonst heute |
| `views(array $views)` | `Calendar` | Die angebotenen Ansichten, in dieser Reihenfolge |

Ein Feld hat keinen Sperrknopf: Das eigene `readonly()` oder `disabled()` des
Felds macht Board oder Kalender schreibgeschützt. Die Kalenderoptionen werden
wie beim Layout geprüft: eine unbekannte Ansicht, ein Datum, das nicht
`YYYY-MM-DD` ist, oder eine leere Liste von Ansichten werfen
`InvalidArgumentException`.

`ValidationRulesExporter` fügt `string` und `max:` mit der Längengrenze
hinzu. Auf der Ansichtsseite zeigen `KanbanEntry` und `CalendarEntry` die
Felder schreibgeschützt.

### MarkdownEditor

Ein Markdown-Editor, der wie eine Textverarbeitung bearbeitet —
[@dskripchenko/markdown-editor](https://github.com/dskripchenko/markdown-editor):
ein visueller Modus (die WYSIWYG-Engine des Panels mit Werkzeugleiste,
Slash-Menü und Tastenkürzeln), der Markdown-Quelltext und beides
nebeneinander. Der Wert ist ein Markdown-String, der unverändert bleibt, wenn
nichts geändert wurde. Anders als bei [`Markdown`](#markdown) (ein Textfeld
mit Vorschau) muss der Benutzer die Syntax nicht kennen.

Die Blocksprachen des Panels werden im Editor gezeichnet — ` ```mermaid ` und
die anderen Diagrammnotationen, ` ```bpmn `, ` ```openapi `, ` ```kanban `,
` ```calendar ` und was eine Anwendung registriert hat (siehe
[Markdown](layouts-reference.md#markdown)). Der Editor spricht die Sprache des
Panels (Englisch oder Russisch); das Paket wird nur geladen, wenn ein
Formular das Feld enthält.

```php
use Dskripchenko\LaravelAdmin\Field\MarkdownEditor;

MarkdownEditor::make('body')
    ->modes(['visual', 'source'])
    ->placeholder('Schreiben…')
    ->minHeight(320)
    ->maxHeight('70vh'),
```

| Methode | Wirkung |
|---|---|
| `modes(array $modes)` | Die angebotenen Modi, in dieser Reihenfolge — `visual`, `source`, `split`; der erste wird zuerst gezeigt. Standardmäßig alle drei |
| `placeholder(string)` | Der Text in einem leeren Editor |
| `minHeight(int\|string)`, `maxHeight(int\|string)` | Die Höhe des Bearbeitungsbereichs: standardmäßig mindestens 240px; über dem Maximum scrollt er |
| `readonly()`, `disabled()` | Nur ansehen |

Ein unbekannter Modus oder eine leere Liste werfen `InvalidArgumentException`.
`ValidationRulesExporter` fügt `string` hinzu. Auf der Ansichtsseite wird der
Wert als Markdown gezeichnet (`MarkdownEntry`), samt der Blocksprachen.

## Siehe auch

- [Resources](concepts/resources.md)
- [Layouts-Referenz](layouts-reference.md)
- [Frontend-Erweiterung](frontend-extension.md) — eigenes Feld registrieren
