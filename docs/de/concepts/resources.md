---
title: Resources
audience: developer
status: stable
locale: de
translated_from: en/concepts/resources.md
translated_at: 2026-10-02
---

# Resources

Eine **Resource** bindet ein Eloquent-Modell in die Admin ein: Formular,
Tabelle, Filter, Actions, Berechtigungen.

## Minimale Resource

```php
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

final class ArticleResource extends Resource
{
    public static string $model = \App\Models\Article::class;
    public static string $icon  = 'file-text';

    public function fields(): array
    {
        return [Input::make('title')->required()];
    }

    public function columns(): array
    {
        return [TableColumn::make('id'), TableColumn::make('title')];
    }
}
```

Registrierung: `Admin::resources([ArticleResource::class])`.

## Slug, Label, Gruppe, Icon

```php
public static string $icon = 'package';        // Lucide-Name
public static ?string $group = 'Katalog';      // Abschnitt der Seitenleiste

public static function slug(): string { return 'articles'; }    // URL = /admin/r/articles
public static function label(): string { return 'Artikel'; }   // Label in der Seitenleiste
```

## Felder

Jedes Feld liefert einen Deskriptor. Gängige Muster:

```php
Input::make('email')->type('email')->required()->placeholder('user@host'),
Number::make('price')->min(0)->step(0.01),
Select::make('status')->options(['draft' => 'Entwurf', 'published' => 'Veröffentlicht'])->required(),
DatePicker::make('published_at')->withTime(),
RelationSelect::make('category_id')->relation(Category::class, 'name'),
Repeater::make('tags')->fields([Input::make('name'), Input::make('color')]),
TranslatableInput::make('title')->locales(['en', 'ru']),
Wysiwyg::make('body')->sanitize(),
```

Den vollständigen Katalog finden Sie in der [Feld-Referenz](../fields-reference.md).

### Sichtbarkeit je Modus

```php
Input::make('slug')->required()->onView(false),     // nur create + update
Password::make('password')->onUpdate(false),         // nur create
```

Jede der Methoden `onCreate()`, `onUpdate()`, `onView()` nimmt einen bool
entgegen und hat den Standardwert `true`; ein Feld ohne diese Aufrufe wird in
allen drei Modi angezeigt. Um ein Feld nur anzuzeigen, solange ein anderes
einen bestimmten Wert hat, verwenden Sie `visibleWhen('driver', 's3')`.

### Aus anderen Feldern abgeleitete Felder

```php
Slug::make('slug')->from('title'),
```

Das Frontend erzeugt `slug` bei jeder Änderung von `title` neu, bis der Slug
von Hand bearbeitet wird. Für alles Aufwendigere — Optionen, die von einem
anderen Feld abhängen, eine berechnete Summe — umschließen Sie die Felder mit
einem [Listener](../layouts-reference.md#listener-reaktiver-teil-eines-formulars).

## Spalten (Tabelle)

```php
TableColumn::make('id')->sort(),
TableColumn::make('title')->sort()->search(),
TableColumn::make('status')->asBadge(['published' => 'success'])->align('center'),
TableColumn::make('created_at')->asDateTime()->sort(),
TableColumn::make('price')->asMoney('USD')->align('right'),
TableColumn::make('cover')->asImage(),
```

Formatierer: `asDate()`, `asDateTime()`, `asMoney()`, `asBoolean()`,
`asBytes()`, `asBadge()`, `asLink()`, `asImage()` oder `format(callable)` für
alles andere. Zeilen-Actions rendert die Tabelle selbst.

`asBadge()` ordnet einem Wert einen Ton zu (`info`, `success`, `warning`,
`danger`, `default` oder die Farbnamen `green`, `red`, `yellow`, `blue`,
`gray`) und, wenn der gespeicherte Wert nicht das ist, was Menschen lesen
sollen, ein Label:

```php
TableColumn::make('status')->asBadge([
    'draft' => ['label' => 'Entwurf', 'tone' => 'warning'],
    'published' => ['label' => 'Veröffentlicht', 'tone' => 'success'],
]),
// oder Töne und Labels getrennt:
TableColumn::make('status')->asBadge(['draft' => 'warning'], ['draft' => 'Entwurf']),
```

Labels werden wie jede andere Beschriftung übersetzt; ein Wert ohne Label wird
unverändert angezeigt.

`format(callable)` läuft auf dem Server, während die Zeilen serialisiert
werden — Liste und Baum einer Resource, ein `TableWidget` — als
`fn ($value, array $row)`, und die Zelle zeigt, was es zurückgibt:

```php
TableColumn::make('author_id')->format(fn ($id, array $row) => $row['author']['name'] ?? '—'),
```

## Filter

```php
public function filters(): array
{
    return [
        InputFilter::for('title')->label('Titel'),               // LIKE %…%
        OptionsFilter::for('status')->options(['draft' => 'Entwurf', 'published' => 'Veröffentlicht']),
        DateRangeFilter::for('created_at'),                       // {from, to}
        SelectFromModelFilter::for('category_id')->fromModel(Category::class, 'name'),
        SwitcherFilter::for('is_featured'),
    ];
}
```

Die Filter befinden sich in `Dskripchenko\LaravelAdmin\Filter`. Für ein Modell
mit `SoftDeletes` wird automatisch ein `TrashedFilter` hinzugefügt.
`QueryFilter` nimmt eine Closure für alles entgegen, was die anderen nicht
abdecken.

## Actions

Zeilen-Actions / Bulk / Command-Bar — siehe [Actions](actions.md).

```php
public function actions(): array
{
    return [
        Button::make('Veröffentlichen')->method('publish')->position(['row']),
        BulkAction::make('Archivieren')->method('archive')->confirm('N Artikel archivieren?'),
    ];
}

public function publish(array $ids, array $payload = []): void
{
    \App\Models\Article::whereKey($ids)->update(['status' => 'published']);
}
```

Die Action wird über ihren Namen aufgerufen, und die Methode der Resource
erhält die ausgewählten IDs und den Payload der Action. Der Name wird
ausschließlich aus den lateinischen Buchstaben und Ziffern des Labels
abgeleitet — ein Label in einer anderen Schrift fällt zu `action` zusammen,
und mehrere solcher Actions werden zu einer —, setzen Sie den Namen bei
nicht-lateinischen Labels daher explizit mit `withName('publish')`.

## Soft-Delete / Wiederherstellen / Endgültig löschen

Wenn Ihr Modell `SoftDeletes` verwendet, aktiviert die Admin automatisch:
- `TrashedFilter` (active/trashed/with)
- die Zeilen-Action `Restore`
- die Zeilen-Action `ForceDelete` (abgesichert durch `admin.{slug}.force-delete`)

## Duplizieren

```php
public function replicable(): bool { return true; }

public function replicate(Model $original): Model
{
    $copy = parent::replicate($original);
    $copy->slug = $original->slug.'-copy';

    return $copy;
}
```

Standardmäßig ist `replicate()` Eloquents `Model::replicate()` plus ein
Kopie-Suffix an `name`/`title`/`slug`; überschreiben Sie die Methode, um
eindeutige Felder neu zu erzeugen.

## Umsortieren

Für Modelle mit einer Sortierspalte:

```php
public function reorderable(): bool { return true; }
public function reorderColumn(): string { return 'position'; }   // der Standardwert
```

Der Listen-Screen erhält eine Spalte mit Ziehgriff.

## Berechtigungen

Standard-Basisberechtigung: `admin.{slug}`. Automatisch abgeleitete
Unterberechtigungen: `view`, `create`, `update`, `delete`, `restore`,
`force-delete`, `replicate`, `reorder`. Überschreiben:

```php
public static function permission(): string
{
    return 'admin.articles';     // admin.articles.view, admin.articles.update, ...
}
```

## Durchsuchbar / Sortierbar

Die Freitextsuche der Liste (`?q=`) läuft über die mit `search()` markierten
Spalten; überschreiben Sie `searchableFields()`, um sie selbst festzulegen.
Ohne explizite Sortierung wird die Liste nach `defaultOrder()` sortiert — die
neuesten zuerst, nach Primärschlüssel:

```php
public function searchableFields(): array { return ['title', 'slug']; }

public function defaultOrder(): array
{
    return [['column' => 'created_at', 'direction' => 'desc']];
}
```

## Beziehungen in Feldern/Spalten

```php
RelationSelect::make('author_id')
    ->relation(User::class, 'name')
    ->searchable(['name', 'email']),

// Datensätze einer anderen Resource, ausgewählt in einem Dialog mit deren Suche und Filtern
ResourcePicker::make('cover_id')->resource(MediaResource::class),
```

Eine Listenzelle liest einen flachen Schlüssel der serialisierten Zeile. Um
einen Wert aus einer Beziehung anzuzeigen, laden Sie die Beziehung daher in
`indexQuery()` und stellen den Wert als Attribut bereit (ein Accessor, der in
`$appends` des Modells aufgeführt ist):

```php
public function indexQuery(): Builder
{
    return parent::indexQuery()->with('author');
}

public function columns(): array
{
    return [TableColumn::make('author_name')->label('Autor')];   // Article::getAuthorNameAttribute()
}
```

In Pickern wird eine Resource über `pickerItem()` dargestellt: `recordTitle()`,
`recordSubtitle()` und `pickerPreview()` (eine Bild-URL oder `null`). Siehe
[ResourcePicker](../fields-reference.md#resourcepicker).

## Siehe auch

- [Screens](screens.md) — Seiten ohne CRUD
- [Berechtigungen](permissions.md) — Details zu RBAC
- [Feld-Referenz](../fields-reference.md)
- [Layout-Referenz](../layouts-reference.md)
