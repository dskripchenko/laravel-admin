---
title: Actions
audience: developer
status: stable
locale: de
translated_from: en/concepts/actions.md
translated_at: 2026-10-02
---

# Actions

Eine **Action** ist eine Schaltfläche, ein Link oder ein Dropdown, das an einen
Screen, eine Tabellenzeile oder eine Mehrfachauswahl gebunden ist. Alle Actions
rufen eine Controller-Methode auf und teilen sich eine einheitlich normalisierte
Response-Struktur.

## Action-Typen

| Klasse | `type()` | Verwendung |
|---|---|---|
| `Button` | `button` | Standard. Ein Klick → POST `{method, payload}`. |
| `Link` | `link` | Externer oder interner href, kein Controller-Aufruf. |
| `BulkAction` | `bulk` | Auf ausgewählten Zeilen; erhält `ids[]`. |
| `ModalAction` | `modal` | Öffnet zuerst ein Formular-Modal, sendet dann per POST. |
| `DropDown` | `dropdown` | Container für Unter-Actions. |
| `AsyncAction` | `async` | Langlaufend; nutzt `dskripchenko/laravel-delayed-process`. |

Jede Action kann statt eine Methode aufzurufen auch eines der Modal- oder
Drawer-Layouts des Screens öffnen: `Button::make('Edit')->opens('edit-modal')`,
wobei `edit-modal` das `withId()` des Layouts ist.

## Gemeinsame Fluent-API

```php
Button::make('Publish')
    ->method('publish')                   // aufzurufende Controller-Methode
    ->icon('check')                       // Lucide-Icon
    ->primary()                           // visuelle Variante
    ->destructive()                       // rote Variante
    ->confirm('Diesen Artikel veröffentlichen?')    // Bestätigungsabfrage
    ->permission('admin.articles.update') // erforderlich, um sie zu sehen und auszuführen
    ->position(['command_bar', 'row'])    // wo sie angezeigt wird
    ->canSee(fn () => auth()->user()?->is_publisher)
    ->withName('publish-action');         // eindeutiger Schlüssel
```

`make()` leitet den Schlüssel aus der Beschriftung ab (`'Publish'` → `publish`);
`withName()` setzt ihn explizit. `canSee()` nimmt einen Bool-Wert oder eine Closure
**ohne Argumente** entgegen, die einmal beim Serialisieren des Schemas ausgewertet
wird — es ist keine zeilenweise Bedingung.

`permission()` und `canSee()` werden auf dem Server durchgesetzt. Eine Action, für
die dem Benutzer die Berechtigung fehlt — oder deren `canSee()` false ist —, wird
aus den `actions` der Resource im Manifest sowie aus der Command-Bar und dem Layout
des Screens weggelassen, ebenso die Dropdown-Einträge, die sie abdeckt. Wird sie
trotzdem ausgeführt, wird dies mit `403` und `errorKey: action_forbidden`
abgelehnt:

- der `action`-Endpoint der Resource, für Zeilen-, Bulk-, Header-, eigenständige
  und Modal-Actions sowie für die Einträge eines `DropDown` (die Berechtigung des
  Dropdowns selbst deckt seine Einträge ab);
- `runMethod` des Screens, für eine Methode, die ein `Button` oder eine
  `ModalAction` in der Command-Bar oder im Layout aufruft — eine Methode, die keine
  Action benennt, ist nur durch `permission()` des Screens geschützt;
- `delayed/run`, für einen Handler, den eine `AsyncAction` mit einer Berechtigung
  startet (siehe unten).

## Positionen

`position(['...'])` — Array aus:

- `command_bar` — Seitenkopf (Screen / Resource-Formular / Liste)
- `row` — Tabellenzeile (pro Datensatz)
- `bulk` — erscheint in der Bulk-Toolbar (wenn 1+ Zeilen ausgewählt sind)
- `header` — Toolbar des Listen-Screens (oberhalb der Tabelle)

Der Standard ist `['command_bar']`; eine `BulkAction` hat standardmäßig `['bulk']`.
Eine Action in einer `row`- oder `bulk`-Position bezieht sich auf Datensätze und
benötigt mindestens eine ID; kennzeichnen Sie eine Action, die eigenständig läuft
(ein Import, eine Synchronisierung), mit `->standalone()` — sie wird ohne IDs
gesendet, und ihre Methode erhält eine leere Liste.

## Resource-Actions

```php
public function actions(): array
{
    return [
        Button::make('Publish')->method('publish')->position(['row']),

        BulkAction::make('Archive')->method('archiveBulk')
            ->confirm('Die ausgewählten Artikel archivieren?')
            ->destructive()
            ->requiresAtMost(500),
    ];
}

public function publish(array $ids, array $payload = []): int
{
    return Article::whereIn('id', $ids)->update(['status' => 'published']);
}

public function archiveBulk(array $ids, array $payload = []): int
{
    return Article::whereIn('id', $ids)->update(['status' => 'archived']);
}
```

Das Backend leitet über `ResourceController::action` weiter (POST
`/api/admin/{slug}/action`, Body `{key, ids[], payload?}`): Die Action wird über
ihren Schlüssel gefunden, und die Resource-Methode wird als
`$resource->{method}(array $ids, array $payload)` aufgerufen — eine Zeilen-Action
erhält ihre eigene Zeile als einelementige Liste. Ein ganzzahliger Rückgabewert wird
als Anzahl der betroffenen Datensätze gemeldet (andernfalls `count($ids)`).

Die Methode kann auch mit einem `string` antworten — der Nachricht für den Toast —
oder mit einem Array mit einem von beiden: `['message' => 'An 12 Abonnenten gesendet', 'affected' => 12]`.
Ohne Nachricht meldet das Panel, dass die Action angewendet wurde. Werfen Sie
`ActionFailedException('...')`, um mit einer Begründung abzulehnen (422).

## Screen-commandBar

```php
public function commandBar(): array
{
    return [
        Button::make('Send')->method('send')->primary(),
        Button::make('Reset')->method('reset')->confirm('Änderungen verwerfen?'),
    ];
}
```

Das Frontend leitet über `ScreenController::runMethod` weiter, Body
`{method, payload: state}`; die Methode erhält den State als Argument.

## Modal-Action (Formular vor dem Absenden)

```php
ModalAction::make('Set price')
    ->method('setPrice')
    ->position(['row', 'bulk'])
    ->fields([
        Number::make('price')->required()->min(0)->step(0.01),
    ]);

public function setPrice(array $ids, array $payload): int
{
    return Product::whereIn('id', $ids)->update(['price' => $payload['price']]);
}
```

Der Payload wird vor der Ausführung der Methode gegen die Regeln der Modal-Felder
(`required()`, `rules([...])`) validiert; ein 422 zeigt die Fehler neben den
Feldern an und lässt das Modal geöffnet.

## Async-Action (langlaufend)

```php
// AppServiceProvider::boot(AllowlistRegistrar $allowlist)
$allowlist->allow(\App\Jobs\ReindexSearch::class, 'handle');
// oder mit einer Berechtigung, die zum Starten erforderlich ist:
$allowlist->allow(\App\Jobs\ReindexSearch::class, 'handle', 'admin.search.reindex');

AsyncAction::make('Re-index search')
    ->handler(\App\Jobs\ReindexSearch::class, 'handle')
    ->withParams(['model' => Article::class])
    ->pollInterval(5);                   // Sekunden
```

Der Handler muss in
`Dskripchenko\LaravelAdmin\DelayedProcess\AllowlistRegistrar` als
`entity::method`-Paar erlaubt sein, sonst kann die SPA ihn nicht starten.
`delayed/run` erfordert die an `allow()` übergebene Berechtigung, und wenn der
Handler von `AsyncAction`s gestartet wird, die in `actions()` von Resources oder in
Command-Bars von Screens deklariert sind, muss dem Benutzer mindestens eine davon
erlaubt sein. Die SPA startet den Prozess über `/api/admin/delayed/run` und fragt
`/api/admin/delayed/status?uuid=...` ab, bis er abgeschlossen ist; die UI zeigt ein
Fortschritts-Modal. In einer `row`/`bulk`-Position werden die ausgewählten
Schlüssel den Parametern als `ids` hinzugefügt. `->callback($url)` setzt einen
Webhook, der den Fortschritt und das Ergebnis erhält.

Die Parameter erreichen den Handler per Name: Jeder Schlüssel aus `withParams()`
(und `ids`) wird in beliebiger Reihenfolge an den gleichnamigen Handler-Parameter
gebunden, ausgelassene Parameter erhalten ihren Standardwert, und ein
Pflichtparameter mit Klassentyp kommt aus dem Container. Daher muss jeder Schlüssel
ein Parameter des Handlers sein; ist einer es nicht, erhält der Handler stattdessen
das ganze Array als einziges Argument. `entity` und `method` sind reserviert:
`delayed/run` antwortet darauf mit 422.

Der Handler meldet seinen Fortschritt selbst: Injizieren Sie
`Dskripchenko\DelayedProcess\Contracts\ProcessProgressInterface` (oder lösen Sie es
mit `app(ProcessProgressInterface::class)` innerhalb der Methode auf) und rufen Sie
`setProgress(0..100)` auf. `delayed/status` liefert den Wert zurück, und das Modal
zeichnet ihn als Balken; der Runner setzt bei Erfolg 100. Außerhalb eines
delayed-process-Laufs bewirkt der Aufruf nichts, sodass der Handler weiterhin
synchron aufgerufen werden kann.

```php
use Dskripchenko\DelayedProcess\Contracts\ProcessProgressInterface;

final class ReindexSearch
{
    public function __construct(private readonly ProcessProgressInterface $progress) {}

    public function handle(string $model): array
    {
        $chunks = $this->chunks($model);
        foreach ($chunks as $i => $chunk) {
            $this->reindex($chunk);
            $this->progress->setProgress(intdiv(($i + 1) * 100, count($chunks)));
        }

        return ['ok' => true];
    }
}
```

## Response-Payload

Eine Command-Methode gibt ein Array zurück, das normalisiert wird zu:

```json
{
  "success": true,
  "payload": {
    "state": {...},
    "layouts": {...},
    "alerts": [{"type": "success", "message": "..."}],
    "redirect_url": null,
    "refresh": true,
    "download_url": null,
    "message": "OK"
  }
}
```

Erkannte Schlüssel:

- `state` — ersetzt den Formular-State auf dem Screen.
- `message` — Toast oder Erfolgsleiste.
- `alerts` — Array aus `{type: 'info'|'success'|'warning'|'danger', message, title?, duration_ms?}`, als Toasts angezeigt (ein Alert, der `message` wiederholt, wird übersprungen).
- `redirect_url` — SPA-interne Navigation.
- `refresh` — `true` löst ein Neuladen des Screens aus.
- `download_url` — wird zum Herunterladen geöffnet.
- `message_link` — wohin die Nachricht führt, z. B. die Seite eines gestarteten Jobs:
  `['url' => …, 'label' => …]`, `['href' => …, 'text' => …]`, `[$url, $label]`
  oder eine bloße URL (beschriftet mit „Open“); siehe [Screens](screens.md#command-methoden).

Unbekannte Schlüssel werden über `extra` durchgereicht.

## Bestätigungsdialog

```php
->confirm('Diesen Datensatz löschen?')
->confirm(['title' => 'Bestätigen', 'message' => 'Kann nicht rückgängig gemacht werden.',
           'confirmLabel' => 'Löschen', 'cancelLabel' => 'Behalten'])
```

Das Frontend zeigt vor dem POST ein Modal an.

## Ablehnen für einen bestimmten Datensatz

Es gibt keine zeilenweise Sichtbarkeitsbedingung: Eine Zeilen-Action wird in jeder
Zeile angezeigt. Prüfen Sie den Datensatz in der Methode und lehnen Sie mit
`Dskripchenko\LaravelAdmin\Resource\ActionFailedException` ab — das Panel erhält
statt eines 500 einen 422 mit Ihrer Nachricht:

```php
use Dskripchenko\LaravelAdmin\Resource\ActionFailedException;

public function publish(array $ids, array $payload = []): int
{
    $articles = Article::whereIn('id', $ids)->get();
    if ($articles->contains('status', 'published')) {
        throw new ActionFailedException('Einige Artikel sind bereits veröffentlicht.');
    }

    return Article::whereIn('id', $ids)->update(['status' => 'published']);
}
```

## Siehe auch

- [Resources](resources.md)
- [Screens](screens.md)
- [Berechtigungen](permissions.md)
- [Rezept für eigene Actions](../../ru/recipes/custom-actions.md) (auf Russisch)
